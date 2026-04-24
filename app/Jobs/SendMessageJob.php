<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Repositories\MessageRepository;
use App\Services\LeadActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        protected string $messageId,
        protected string $tenantId,
    ) {}

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(MessageRepository $repository, LeadActivityService $activityService): void
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
            // Stamp each attempt attempt timestamp + increment retry counter
            DB::table('messages')
                ->where('id', $this->messageId)
                ->where('tenant_id', $this->tenantId)
                ->update([
                    'last_attempt_at' => now(),
                    'retry_count'     => DB::raw('COALESCE(retry_count, 0) + 1'),
                ]);

            // Send via adapter based on channel
            $adapter = $this->getAdapterForChannel($message->channel);
            $response = $adapter->sendMessage(
                $message->content,
                $message->lead_id,
                $this->tenantId
            );

            if ($response['success']) {
                $repository->updateMessageStatus($this->tenantId, $this->messageId, 'sent', $response);
                Log::info('Message sent successfully', [
                    'message_id' => $this->messageId,
                    'tenant_id' => $this->tenantId,
                    'channel' => $message->channel,
                ]);
            } else {
                throw new \Exception($response['error'] ?? 'Send failed');
            }
        } catch (\Throwable $e) {
            Log::error('Message send failed', [
                'message_id' => $this->messageId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $repository->updateMessageStatus($this->tenantId, $this->messageId, 'failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                ]);

                $activityService->logOutboundMessage(
                    (string) $message->lead_id,
                    $this->tenantId,
                    $this->messageId,
                    (string) $message->channel,
                    '[FAILED] ' . (string) $message->content,
                    null,
                );

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
