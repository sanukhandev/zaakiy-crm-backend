<?php

namespace App\Services;

use App\DTOs\WebhookLeadPayload;
use App\Jobs\ProcessWebhookJob;
use App\Services\Webhooks\Normalizers\MetaNormalizer;
use App\Services\Webhooks\Normalizers\WhatsAppNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookService
{
    public function __construct(
        protected MetaNormalizer $metaNormalizer,
        protected WhatsAppNormalizer $whatsAppNormalizer,
        protected LeadLinkingService $leadLinkingService,
        protected AutomationService $automationService,
        protected LeadService $leadService,
        protected WhatsAppService $whatsAppService,
        protected LeadActivityService $leadActivityService,
        protected MessageService $messageService,
        protected WebhookSignatureValidator $signatureValidator,
    ) {}

    public function queueInbound(string $provider, Request $request): array
    {
        $payload = $request->all();
        $tenantId = $this->resolveTenantId($request, $payload);

        if (!$tenantId) {
            throw new InvalidArgumentException('Unable to resolve tenant for webhook');
        }

        $this->signatureValidator->validateOrFail($provider, $request, $tenantId);

        dispatch(new ProcessWebhookJob($provider, $tenantId, $payload));

        Log::info('Webhook queued', [
            'provider' => $provider,
            'tenant_id' => $tenantId,
        ]);

        return [
            'queued' => true,
            'tenant_id' => $tenantId,
            'provider' => $provider,
        ];
    }

    public function processQueuedWebhook(string $provider, string $tenantId, array $payload): array
    {
        return match ($provider) {
            'meta' => $this->processMeta($tenantId, $payload),
            'whatsapp' => $this->processWhatsApp($tenantId, $payload),
            'tiktok' => $this->processTikTok($tenantId, $payload),
            default => throw new InvalidArgumentException('Unsupported webhook provider'),
        };
    }

    public function ingestMeta(Request $request): array
    {
        return $this->queueInbound('meta', $request);
    }

    public function ingestWhatsApp(Request $request): array
    {
        return $this->queueInbound('whatsapp', $request);
    }

    public function ingestTikTok(Request $request): array
    {
        return $this->queueInbound('tiktok', $request);
    }

    private function processMeta(string $tenantId, array $payload): array
    {
        $leadPayload = $this->metaNormalizer->normalize($payload);
        $messagePayload = $this->metaNormalizer->normalizeInboundMessage($payload);

        if (!$leadPayload->phone && !$leadPayload->email) {
            throw new InvalidArgumentException('Meta payload missing phone/email');
        }

        $linked = $this->leadLinkingService->resolveLead($tenantId, $leadPayload, $messagePayload, true);
        $lead = $linked['lead'];

        if (!$lead) {
            throw new InvalidArgumentException('Failed to resolve lead for meta webhook');
        }

        if (($linked['action'] ?? null) === 'created') {
            $this->automationService->handleLeadCreated($tenantId, $lead, ['source' => 'meta']);
        }

        if ($messagePayload && $messagePayload->content !== '') {
            $message = $this->messageService->createInboundMessage(
                $tenantId,
                (string) $lead->id,
                'meta',
                $messagePayload->content,
                $messagePayload->externalId,
                $messagePayload->metadata,
                $messagePayload->sender,
            );

            $this->leadActivityService->logInboundMessage($tenantId, (string) $lead->id, $messagePayload->content);
            $this->automationService->handleMessageReceived($tenantId, $lead, $messagePayload->content);

            return [
                'lead_id' => $lead->id,
                'message_id' => $message->id,
                'action' => $linked['action'],
            ];
        }

        return [
            'lead_id' => $lead->id,
            'action' => $linked['action'],
        ];
    }

    private function processWhatsApp(string $tenantId, array $payload): array
    {
        $leadPayload = $this->whatsAppNormalizer->normalizeLead($payload);
        $messagePayload = $this->whatsAppNormalizer->normalizeInboundMessage($payload);
        $linked = $this->leadLinkingService->resolveLead($tenantId, $leadPayload, $messagePayload, true);
        $lead = $linked['lead'];

        if (!$lead) {
            throw new InvalidArgumentException('Failed to resolve lead for WhatsApp webhook');
        }

        if (($linked['action'] ?? null) === 'created') {
            $this->automationService->handleLeadCreated($tenantId, $lead, ['source' => 'whatsapp']);
        }

        $message = $this->messageService->createInboundMessage(
            $tenantId,
            (string) $lead->id,
            'whatsapp',
            $messagePayload->content,
            $messagePayload->externalId,
            $messagePayload->metadata,
            $messagePayload->sender,
        );

        $this->leadActivityService->logInboundMessage($tenantId, (string) $lead->id, $messagePayload->content);
        $this->automationService->handleMessageReceived($tenantId, $lead, $messagePayload->content);

        return [
            'lead_id' => $lead->id,
            'message_id' => $message->id,
            'action' => $linked['action'],
        ];
    }

    private function processTikTok(string $tenantId, array $payload): array
    {
        $leadPayload = WebhookLeadPayload::fromArray([
            'name' => $payload['lead']['name'] ?? $payload['name'] ?? 'TikTok Lead',
            'phone' => $payload['lead']['phone'] ?? $payload['phone'] ?? null,
            'email' => $payload['lead']['email'] ?? $payload['email'] ?? null,
            'metadata' => $payload,
        ], 'tiktok');

        $linked = $this->leadLinkingService->resolveLead($tenantId, $leadPayload, null, true);
        $lead = $linked['lead'];

        if (!$lead) {
            throw new InvalidArgumentException('Failed to resolve lead for TikTok webhook');
        }

        if (($linked['action'] ?? null) === 'created') {
            $this->automationService->handleLeadCreated($tenantId, $lead, ['source' => 'tiktok']);
        }

        return [
            'lead_id' => $lead->id,
            'action' => $linked['action'],
            'stub' => true,
        ];
    }

    private function resolveTenantId(Request $request, array $payload): ?string
    {
        $apiKey = $request->header('X-Webhook-Key');

        if (!empty($apiKey)) {
            $row = DB::table('tenant_webhook_keys')
                ->where('key_hash', hash('sha256', (string) $apiKey))
                ->whereNull('revoked_at')
                ->latest('id')
                ->first();

            if ($row && !empty($row->tenant_id)) {
                DB::table('tenant_webhook_keys')
                    ->where('id', $row->id)
                    ->update([
                        'last_used_at' => now(),
                        'updated_at' => now(),
                    ]);

                return (string) $row->tenant_id;
            }
        }

        $apiKeyMap = array_filter(
            config('services.webhooks.api_key_map', []),
            fn ($value, $key) => !empty($key) && !empty($value),
            ARRAY_FILTER_USE_BOTH,
        );
        if ($apiKey && isset($apiKeyMap[$apiKey])) {
            return (string) $apiKeyMap[$apiKey];
        }

        $sourceKey = (string) (
            $payload['account_id']
            ?? $payload['business_account_id']
            ?? $payload['entry'][0]['id']
            ?? $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id']
            ?? $payload['entry'][0]['changes'][0]['value']['metadata']['display_phone_number']
            ?? $payload['entry'][0]['changes'][0]['value']['business_id']
            ?? $payload['tenant_hint']
            ?? ''
        );

        $sourceMap = config('services.webhooks.source_map', []);

        return $sourceKey !== '' && isset($sourceMap[$sourceKey])
            ? (string) $sourceMap[$sourceKey]
            : config('services.webhooks.default_tenant_id');
    }
}
