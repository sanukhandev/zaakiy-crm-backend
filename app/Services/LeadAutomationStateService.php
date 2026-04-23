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
        $normalizedLead = $this->normalizeLead($lead);
        $normalizedLead->needs_follow_up = $this->needsFollowUp($normalizedLead, $settings['follow_up_threshold_minutes']);

        return $normalizedLead;
    }

    public function annotateLeadCollection(string $tenantId, array $leads): array
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return array_map(function ($lead) use ($settings) {
            $normalizedLead = $this->normalizeLead($lead);
            $normalizedLead->needs_follow_up = $this->needsFollowUp($normalizedLead, $settings['follow_up_threshold_minutes']);

            return $normalizedLead;
        }, $leads);
    }

    public function shouldAutoReply(string $tenantId, object $lead, int $inboundMessageCount): bool
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return $settings['auto_reply_enabled']
            && trim((string) $settings['auto_reply_template']) !== ''
            && empty($this->getProperty($lead, 'auto_replied_at'))
            && $inboundMessageCount === 1;
    }

    public function getAutoReplyTemplate(string $tenantId): string
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        return trim((string) ($settings['auto_reply_template'] ?? ''));
    }

    private function needsFollowUp(object $lead, int $thresholdMinutes): bool
    {
        // Safely access properties that may not exist on stdClass or incomplete objects
        $lastInboundAt = $this->getProperty($lead, 'last_inbound_at');
        if (empty($lastInboundAt)) {
            return false;
        }

        try {
            $lastInboundAt = CarbonImmutable::parse($lastInboundAt);
        } catch (\Exception) {
            return false;
        }

        $lastOutboundAtStr = $this->getProperty($lead, 'last_outbound_at');
        $lastOutboundAt = !empty($lastOutboundAtStr) ? $this->safeParse($lastOutboundAtStr) : null;

        if ($lastOutboundAt && $lastOutboundAt->greaterThanOrEqualTo($lastInboundAt)) {
            return false;
        }

        $lastActivityAtStr = $this->getProperty($lead, 'last_activity_at');
        $lastActivityAt = !empty($lastActivityAtStr) ? $this->safeParse($lastActivityAtStr) : null;
        
        $referenceAt = $lastActivityAt && $lastActivityAt->greaterThan($lastInboundAt)
            ? $lastActivityAt
            : $lastInboundAt;

        return $referenceAt->diffInMinutes(now()) >= max($thresholdMinutes, 5);
    }

    private function getProperty(object $lead, string $key): mixed
    {
        try {
            return $lead->$key ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeLead(mixed $lead): object
    {
        if (is_array($lead)) {
            return (object) $lead;
        }

        if (is_object($lead)) {
            try {
                return (object) get_object_vars($lead);
            } catch (\Throwable) {
                return (object) [];
            }
        }

        return (object) [];
    }

    private function safeParse(?string $value): ?CarbonImmutable
    {
        if (empty($value)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
