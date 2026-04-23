<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class LeadAutomationStateService
{
    public function __construct(
        protected TenantAutomationSettingsService $tenantAutomationSettingsService,
    ) {}

    public function annotateLead(string $tenantId, object $lead): object
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);
        $lead->needs_follow_up = $this->needsFollowUp($lead, $settings['follow_up_threshold_minutes']);

        return $lead;
    }

    public function annotateLeadCollection(string $tenantId, array $leads): array
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return array_map(function (object $lead) use ($settings) {
            $lead->needs_follow_up = $this->needsFollowUp($lead, $settings['follow_up_threshold_minutes']);
            return $lead;
        }, $leads);
    }

    public function shouldAutoReply(string $tenantId, object $lead, int $inboundMessageCount): bool
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return $settings['auto_reply_enabled']
            && trim((string) $settings['auto_reply_template']) !== ''
            && empty($lead->auto_replied_at)
            && $inboundMessageCount === 1;
    }

    public function getAutoReplyTemplate(string $tenantId): string
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return trim((string) ($settings['auto_reply_template'] ?? ''));
    }

    private function needsFollowUp(object $lead, int $thresholdMinutes): bool
    {
        if (empty($lead->last_inbound_at)) {
            return false;
        }

        $lastInboundAt = CarbonImmutable::parse($lead->last_inbound_at);
        $lastOutboundAt = !empty($lead->last_outbound_at) ? CarbonImmutable::parse($lead->last_outbound_at) : null;

        if ($lastOutboundAt && $lastOutboundAt->greaterThanOrEqualTo($lastInboundAt)) {
            return false;
        }

        $lastActivityAt = !empty($lead->last_activity_at) ? CarbonImmutable::parse($lead->last_activity_at) : null;
        $referenceAt = $lastActivityAt && $lastActivityAt->greaterThan($lastInboundAt)
            ? $lastActivityAt
            : $lastInboundAt;

        return $referenceAt->diffInMinutes(now()) >= max($thresholdMinutes, 5);
    }
}
