<?php

namespace App\Services;

use App\Jobs\ExecuteAutomationActionJob;
use App\Repositories\LeadRepository;
use Illuminate\Support\Facades\DB;
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

        $this->evaluateRules('lead_created', [
            'lead' => (array) $lead,
            'tenant_id' => $tenantId,
        ], $tenantId);
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

        $this->evaluateRules('message_received', [
            'lead' => (array) $lead,
            'content' => $content,
            'tenant_id' => $tenantId,
        ], $tenantId);
    }

    public function handleStageChanged(string $tenantId, object $lead, ?object $previousStage, object $newStage): void
    {
        $this->evaluateRules('stage_changed', [
            'lead' => (array) $lead,
            'previous_stage' => $previousStage ? (array) $previousStage : null,
            'new_stage' => (array) $newStage,
            'tenant_id' => $tenantId,
        ], $tenantId);
    }

    // -------------------------------------------------------------------------
    // Rule engine
    // -------------------------------------------------------------------------

    /**
     * Fetch active automation rules for the given trigger type and tenant,
     * evaluate their conditions against the context, and enqueue matching actions.
     */
    public function evaluateRules(string $triggerType, array $context, string $tenantId): void
    {
        $rules = DB::table('automation_rules')
            ->where('tenant_id', $tenantId)
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            $conditions = json_decode((string) $rule->conditions, true) ?? [];

            if (!$this->conditionsMet($conditions, $context)) {
                continue;
            }

            $actions = json_decode((string) $rule->actions, true) ?? [];
            $this->executeActions($actions, $context, $tenantId);
        }
    }

    /**
     * Enqueue each action as an async job so the HTTP request is never blocked.
     */
    public function executeActions(array $actions, array $context, string $tenantId): void
    {
        foreach ($actions as $action) {
            if (empty($action['type'])) {
                continue;
            }

            ExecuteAutomationActionJob::dispatch($action, $context, $tenantId)
                ->onQueue('automation');
        }
    }

    // -------------------------------------------------------------------------
    // Condition evaluator
    // -------------------------------------------------------------------------

    private function conditionsMet(array $conditions, array $context): bool
    {
        // Empty conditions = always match
        if (empty($conditions)) {
            return true;
        }

        // Support { "operator": "AND|OR", "rules": [...] } or flat list
        $operator = strtoupper((string) ($conditions['operator'] ?? 'AND'));
        $rules    = $conditions['rules'] ?? $conditions;

        if (!is_array($rules)) {
            return true;
        }

        $results = array_map(
            fn (array $rule) => $this->evaluateSingleCondition($rule, $context),
            array_filter($rules, 'is_array'),
        );

        if (empty($results)) {
            return true;
        }

        return $operator === 'OR'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    private function evaluateSingleCondition(array $rule, array $context): bool
    {
        $field    = (string) ($rule['field'] ?? '');
        $operator = (string) ($rule['operator'] ?? 'eq');
        $expected = $rule['value'] ?? null;

        $actual = $this->resolveField($field, $context);

        return match ($operator) {
            'eq'          => $actual == $expected,
            'neq'         => $actual != $expected,
            'contains'    => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            'not_contains' => is_string($actual) && !str_contains(strtolower($actual), strtolower((string) $expected)),
            'gt'          => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'lt'          => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'is_null'     => $actual === null,
            'is_not_null' => $actual !== null,
            default       => false,
        };
    }

    private function resolveField(string $field, array $context): mixed
    {
        // Allow dot notation: "lead.source" → $context['lead']['source']
        $parts  = explode('.', $field, 2);
        $top    = $parts[0];
        $nested = $parts[1] ?? null;

        $value = $context[$top] ?? null;

        if ($nested !== null && is_array($value)) {
            return $value[$nested] ?? null;
        }

        return $value;
    }
}
