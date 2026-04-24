<?php

namespace App\Events;

class LeadStageChanged
{
    public function __construct(
        public readonly object $lead,
        public readonly ?object $previousStage,
        public readonly object $newStage,
        public readonly string $tenantId,
    ) {}
}
