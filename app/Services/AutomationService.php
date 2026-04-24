<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected LeadActivityService $leadActivityService,
        protected LeadAutomationStateService $leadAutomationStateService,
        protected WhatsAppService $whatsAppService,
        protected MessageService $messageService,
    ) {}

    public function handleLeadCreated(string $tenantId, object $lead, array $context = []): void
    {
        $this->leadActivityService->logLeadCreated(
            tenantId: $tenantId,
            leadId: (string) $lead->id,
            source: (string) ($lead->source ?? ($context['source'] ?? 'webhook')),
            createdBy: $context['actor'] ?? null,
        );

        if (!empty($lead->assigned_to)) {
            $this->leadActivityService->logAssignment(
                tenantId: $tenantId,
                leadId: (string) $lead->id,
                assignedToId: (string) $lead->assigned_to,
                previousAssignedToId: null,
                createdBy: $context['actor'] ?? null,
            );
        }
    }

    public function handleMessageReceived(string $tenantId, object $lead, string $content): void
    {
        $messageCount = $this->messageService->countInboundMessages($tenantId, (string) $lead->id);

        if (!$this->leadAutomationStateService->shouldAutoReply($tenantId, $lead, $messageCount)) {
            return;
        }

        $template = $this->leadAutomationStateService->getAutoReplyTemplate($tenantId);
        if ($template === '') {
            return;
        }

        try {
            $this->whatsAppService->sendOutbound([
                'tenant_id' => $tenantId,
                'user_id' => null,
            ], (string) $lead->id, $template, true);
        } catch (\Throwable $error) {
            Log::warning('Automation auto reply failed', [
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'content_preview' => mb_substr($content, 0, 120),
                'error' => $error->getMessage(),
            ]);
        }
    }
}