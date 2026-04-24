<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Repositories\MessageRepository;
use Illuminate\Support\Facades\Log;

class UpdateMessageStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        protected string $tenantId,
        protected string $externalId,
        protected string $status,
        protected ?array $payload = null,
    ) {}

    public function handle(MessageRepository $repository): void
    {
        $message = $repository->findByExternalId($this->tenantId, $this->externalId);

        if (!$message) {
            Log::warning('Message not found for status update', [
                'external_id' => $this->externalId,
                'tenant_id' => $this->tenantId,
                'status' => $this->status,
            ]);
            return;
        }

        $updated = $repository->updateStatusByExternalId(
            $this->tenantId,
            $this->externalId,
            $this->status,
            $this->payload,
        );

        if ($updated) {
            Log::info('Message status updated', [
                'message_id' => $message->id,
                'status' => $this->status,
            ]);
        }
    }
}
