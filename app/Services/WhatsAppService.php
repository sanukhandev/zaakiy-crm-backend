<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use App\Repositories\MessageRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class WhatsAppService
{
    public function __construct(
        protected MessageRepository $messageRepository,
        protected LeadRepository $leadRepository,
        protected LeadService $leadService,
        protected TenantWhatsAppIntegrationService $tenantWhatsAppIntegrationService,
    ) {}

    public function ingestInbound(string $tenantId, array $payload): array
    {
        return DB::transaction(function () use ($tenantId, $payload) {
            $messageId = $this->messageRepository->createInbound([
                'tenant_id' => $tenantId,
                'phone' => $payload['phone'],
                'message' => $payload['message'],
                'direction' => $payload['direction'] ?? 'inbound',
                'external_id' => $payload['external_id'] ?? null,
            ]);

            $lead = $this->leadRepository->findByPhone($tenantId, $payload['phone']);

            if (!$lead) {
                $created = $this->leadService->createOrUpdateLeadFromWebhook($tenantId, [
                    'name' => $payload['phone'],
                    'phone' => $payload['phone'],
                    'email' => null,
                    'source' => 'whatsapp',
                    'metadata' => $payload['metadata'] ?? [],
                ]);

                $lead = $this->leadRepository->findByIdForTenant($created['id'], $tenantId);
            }

            $this->messageRepository->linkLead($messageId, $tenantId, $lead->id);

            $this->leadRepository->addActivity($lead->id, [
                'tenant_id' => $tenantId,
                'user_id' => null,
            ], [
                'type' => 'whatsapp',
                'content' => $payload['message'],
            ]);

            return [
                'message_id' => $messageId,
                'lead_id' => $lead->id,
            ];
        });
    }

    public function sendOutbound(array $auth, string $leadId, string $content): array
    {
        $lead = $this->leadRepository->findByIdForTenant($leadId, $auth['tenant_id']);

        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        if (empty($lead->phone)) {
            throw new InvalidArgumentException('Lead phone number is required to send WhatsApp message');
        }

        $providerResponse = $this->sendViaProvider((string) $lead->phone, $content, $auth['tenant_id'], $leadId);
        $externalId = $providerResponse['external_id'] ?? null;

        if ($externalId) {
            $existing = $this->messageRepository->findByExternalId($auth['tenant_id'], $externalId);
            if ($existing) {
                return [
                    'id' => $existing->id,
                    'tenant_id' => $existing->tenant_id,
                    'channel' => $existing->channel,
                    'sender' => $existing->sender,
                    'external_id' => $existing->external_id,
                    'lead_id' => $existing->lead_id,
                    'content' => $existing->content,
                    'direction' => $existing->direction,
                    'created_at' => $existing->created_at,
                    'duplicate' => true,
                ];
            }
        }

        return DB::transaction(function () use ($auth, $leadId, $content, $externalId) {
            $senderLabel = config('services.whatsapp.sender_label', 'crm');
            $messageId = $this->messageRepository->createOutbound([
                'tenant_id' => $auth['tenant_id'],
                'lead_id' => $leadId,
                'sender' => $senderLabel,
                'message' => $content,
                'external_id' => $externalId,
            ]);

            $this->leadRepository->addActivity($leadId, $auth, [
                'type' => 'whatsapp',
                'content' => $content,
            ]);

            Log::info('WhatsApp outbound message sent', [
                'tenant_id' => $auth['tenant_id'],
                'lead_id' => $leadId,
                'message_id' => $messageId,
                'external_id' => $externalId,
                'user_id' => $auth['user_id'] ?? null,
            ]);

            return [
                'id' => $messageId,
                'tenant_id' => $auth['tenant_id'],
                'channel' => 'whatsapp',
                'sender' => $senderLabel,
                'external_id' => $externalId,
                'lead_id' => $leadId,
                'content' => $content,
                'direction' => 'outbound',
                'created_at' => now()->toIso8601String(),
            ];
        });
    }

    private function sendViaProvider(string $phone, string $content, string $tenantId, string $leadId): array
    {
        $providerConfig = $this->tenantWhatsAppIntegrationService->resolveProviderConfig($tenantId);
        $baseUrl = rtrim((string) ($providerConfig['base_url'] ?? ''), '/');
        $apiVersion = trim((string) ($providerConfig['api_version'] ?? 'v21.0'), '/');
        $phoneNumberId = (string) ($providerConfig['phone_number_id'] ?? '');
        $accessToken = (string) ($providerConfig['access_token'] ?? '');

        if (!$providerConfig['is_active'] || $baseUrl === '' || $phoneNumberId === '' || $accessToken === '') {
            throw new RuntimeException('WhatsApp provider configuration is incomplete');
        }

        $endpoint = sprintf('%s/%s/%s/messages', $baseUrl, $apiVersion, $phoneNumberId);

        try {
            $response = Http::timeout(10)
                ->retry(2, 250)
                ->withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'body' => $content,
                    ],
                ]);
        } catch (\Throwable $error) {
            Log::error('WhatsApp outbound provider request failed', [
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'phone' => $phone,
                'error' => $error->getMessage(),
            ]);

            throw new RuntimeException('Failed to send WhatsApp message');
        }

        if (!$response->successful()) {
            Log::error('WhatsApp outbound provider response unsuccessful', [
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException('WhatsApp provider rejected the message');
        }

        $payload = $response->json();

        return [
            'external_id' => $payload['messages'][0]['id'] ?? null,
            'provider_response' => $payload,
        ];
    }
}
