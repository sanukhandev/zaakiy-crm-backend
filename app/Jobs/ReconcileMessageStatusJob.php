<?php

namespace App\Jobs;

use App\Repositories\MessageRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles messages that have been stuck in a non-terminal status
 * (sending / sent) longer than $stalenessMinutes.
 *
 * Because the WhatsApp Cloud API and Meta Graph API push status webhooks,
 * we reconcile by marking any message that is still 'sending' after the
 * threshold as 'failed'. Messages with 'sent' status but no 'delivered'
 * webhook after a longer window are left alone (they may still arrive),
 * unless max retries have been exhausted.
 */
class ReconcileMessageStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Minutes before a 'sending' message is considered stale */
    protected int $staleSendingMinutes = 10;

    public function __construct(
        protected ?string $tenantId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(MessageRepository $repository): void
    {
        $staleThreshold = now()->subMinutes($this->staleSendingMinutes);

        $query = DB::table('messages')
            ->where('status', 'sending')
            ->where('updated_at', '<', $staleThreshold);

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        $staleMessages = $query->select(['id', 'tenant_id', 'external_id', 'retry_count'])->get();

        foreach ($staleMessages as $msg) {
            try {
                $repository->updateMessageStatus(
                    (string) $msg->tenant_id,
                    (string) $msg->id,
                    'failed',
                    ['reconciled_at' => now()->toIso8601String(), 'reason' => 'stale_sending'],
                );

                Log::info('ReconcileMessageStatusJob: marked stale message as failed', [
                    'message_id' => $msg->id,
                    'tenant_id'  => $msg->tenant_id,
                    'retry_count' => $msg->retry_count,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ReconcileMessageStatusJob: failed to update message', [
                    'message_id' => $msg->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReconcileMessageStatusJob failed', ['error' => $exception->getMessage()]);
    }
}
