<?php

namespace App\Services;

use App\Services\Webhooks\Normalizers\MetaNormalizer;
use App\Services\Webhooks\Normalizers\WhatsAppNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WebhookService
{
    public function __construct(
        protected MetaNormalizer $metaNormalizer,
        protected WhatsAppNormalizer $whatsAppNormalizer,
        protected LeadService $leadService,
        protected WhatsAppService $whatsAppService,
        protected LeadActivityService $leadActivityService,
        protected MessageService $messageService,
    ) {}

    public function ingestMeta(Request $request): array
    {
        $payload = $request->all();
        $tenantId = $this->resolveTenantId($request, $payload);

        if (!$tenantId) {
            throw new InvalidArgumentException('Unable to resolve tenant for webhook');
        }

        $normalized = $this->metaNormalizer->normalize($payload);

        if (empty($normalized->phone) && empty($normalized->email)) {
            throw new InvalidArgumentException('Meta payload missing phone/email');
        }

        return $this->leadService->createOrUpdateLeadFromWebhook(
            $tenantId,
            $normalized->toArray(),
        );
    }

    public function ingestWhatsApp(Request $request): array
    {
        $payload = $request->all();
        $tenantId = $this->resolveTenantId($request, $payload);

        if (!$tenantId) {
            throw new InvalidArgumentException('Unable to resolve tenant for webhook');
        }

        $normalizedMessage = $this->whatsAppNormalizer->normalizeInboundMessage($payload);

        return $this->whatsAppService->ingestInbound($tenantId, $normalizedMessage);
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
