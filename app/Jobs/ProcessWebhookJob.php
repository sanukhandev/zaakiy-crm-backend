<?php

namespace App\Jobs;

use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        protected string $provider,
        protected string $tenantId,
        protected array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(WebhookService $webhookService): void
    {
        $webhookService->processQueuedWebhook($this->provider, $this->tenantId, $this->payload);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed', [
            'provider' => $this->provider,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}