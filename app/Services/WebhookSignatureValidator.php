<?php

namespace App\Services;

use Illuminate\Http\Request;
use InvalidArgumentException;

class WebhookSignatureValidator
{
    public function validateOrFail(string $provider, Request $request, ?string $tenantId = null): void
    {
        $secret = (string) config("services.webhooks.signatures.{$provider}_secret", '');
        $allowUnsigned = (bool) config('services.webhooks.allow_unsigned', false);

        if ($secret === '') {
            if ($allowUnsigned) {
                return;
            }

            throw new InvalidArgumentException("Missing {$provider} webhook secret configuration");
        }

        $header = (string) ($request->header('X-Hub-Signature-256') ?? $request->header('X-Webhook-Signature') ?? '');

        if ($header === '') {
            throw new InvalidArgumentException('Webhook signature header is missing');
        }

        $signature = str_contains($header, '=')
            ? substr($header, strpos($header, '=') + 1)
            : $header;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Webhook signature validation failed');
        }
    }
}