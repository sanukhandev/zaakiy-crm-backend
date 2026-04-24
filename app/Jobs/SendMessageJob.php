<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Repositories\MessageRepository;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $messageId,
        protected string $tenantId,
    ) {}

    public function handle(MessageRepository $repository): void
    {
        $message = $repository->findById($this->tenantId, $this->messageId);

        if (!$message) {
            Log::error('Message not found for SendMessageJob', [
                'message_id' => $this->messageId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        try {
            // Send via adapter based on channel
            $adapter = $this->getAdapterForChannel($message->channel);
            $response = $adapter->sendMessage(
                $message->content,
                $message->lead_id,
                $this->tenantId
            );

            if ($response['success']) {
                $repository->updateStatus($this->tenantId, $this->messageId, 'sent');
                Log::info('Message sent successfully', [
                    'message_id' => $this->messageId,
                    'channel' => $message->channel,
                ]);
            } else {
                throw new \Exception($response['error'] ?? 'Send failed');
            }
        } catch (\Throwable $e) {
            Log::error('Message send failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $repository->updateStatus($this->tenantId, $this->messageId, 'failed');
                $this->fail($e);
            } else {
                throw $e; // Retry
            }
        }
    }

    private function getAdapterForChannel(string $channel)
    {
        return match($channel) {
            'whatsapp' => app(\App\Adapters\WhatsAppAdapter::class),
            'meta' => app(\App\Adapters\MetaAdapter::class),
            default => throw new \InvalidArgumentException("Unknown channel: $channel"),
        };
    }
}
