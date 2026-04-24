<?php

namespace App\Jobs;

use App\Repositories\LeadRepository;
use App\Services\MessageService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes a single automation action that was matched by AutomationService::evaluateRules().
 *
 * Supported action types:
 *  - assign_user   : assign the lead to a specific user_id
 *  - send_message  : send a WhatsApp/template message to the lead
 *  - update_status : change the lead's status field
 */
class ExecuteAutomationActionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        protected array $action,
        protected array $context,
        protected string $tenantId,
    ) {
        $this->onQueue('automation');
    }

    public function handle(LeadRepository $leadRepository, WhatsAppService $whatsAppService, MessageService $messageService): void
    {
        $type   = (string) ($this->action['type'] ?? '');
        $params = (array) ($this->action['params'] ?? []);
        $lead   = $this->context['lead'] ?? null;
        $leadId = is_array($lead) ? ($lead['id'] ?? null) : null;

        if (!$leadId) {
            Log::warning('ExecuteAutomationActionJob: missing lead id, skipping', ['action' => $type]);
            return;
        }

        switch ($type) {
            case 'assign_user':
                $this->assignUser($leadId, $params);
                break;

            case 'send_message':
                $this->sendMessage($leadId, $params, $whatsAppService);
                break;

            case 'update_status':
                $this->updateStatus($leadId, $params);
                break;

            default:
                Log::warning('ExecuteAutomationActionJob: unknown action type', ['type' => $type]);
        }
    }

    private function assignUser(string $leadId, array $params): void
    {
        $userId = (string) ($params['user_id'] ?? '');
        if ($userId === '') {
            return;
        }

        DB::table('leads')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $leadId)
            ->whereNull('deleted_at')
            ->update([
                'assigned_to' => $userId,
                'updated_at'  => now(),
            ]);
    }

    private function sendMessage(string $leadId, array $params, WhatsAppService $whatsAppService): void
    {
        $template = (string) ($params['template'] ?? $params['message'] ?? '');
        if ($template === '') {
            return;
        }

        try {
            $whatsAppService->sendOutbound(
                ['tenant_id' => $this->tenantId, 'user_id' => null],
                $leadId,
                $template,
                true,
            );
        } catch (\Throwable $e) {
            Log::warning('ExecuteAutomationActionJob: send_message failed', [
                'lead_id' => $leadId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function updateStatus(string $leadId, array $params): void
    {
        $status = (string) ($params['status'] ?? '');
        if ($status === '') {
            return;
        }

        DB::table('leads')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $leadId)
            ->whereNull('deleted_at')
            ->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteAutomationActionJob failed permanently', [
            'tenant_id' => $this->tenantId,
            'action'    => $this->action,
            'error'     => $exception->getMessage(),
        ]);
    }
}
