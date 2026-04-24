<?php

namespace App\Adapters;

use Illuminate\Support\Facades\Http;

class MetaAdapter
{
    public function sendMessage(string $content, string $leadId, string $tenantId): array
    {
        $endpoint = (string) config('services.meta.messages_endpoint', '');
        $token = (string) config('services.meta.access_token', '');

        if ($endpoint === '' || $token === '') {
            return ['success' => false, 'error' => 'Meta provider configuration is incomplete'];
        }

        $response = Http::timeout(10)
            ->withToken($token)
            ->acceptJson()
            ->post($endpoint, [
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'message' => $content,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'Provider rejected message',
                'status' => $response->status(),
                'body' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'external_id' => data_get($response->json(), 'id') ?? data_get($response->json(), 'message_id'),
            'payload' => $response->json(),
        ];
    }
}
