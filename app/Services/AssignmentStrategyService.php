<?php

namespace App\Services;

use App\Repositories\UserAssignmentRepository;

class AssignmentStrategyService
{
    public function __construct(
        protected UserAssignmentRepository $userAssignmentRepository,
        protected TenantAutomationSettingsService $tenantAutomationSettingsService,
    ) {}

    public function resolveUserId(string $tenantId): ?string
    {
        $settings = $this->tenantAutomationSettingsService->resolve($tenantId);

        if (!$settings['auto_assignment_enabled']) {
            return null;
        }

        return match ($settings['assignment_strategy']) {
            'round_robin' => $this->resolveRoundRobinUserId($tenantId, $settings['round_robin_last_user_id'] ?? null),
            default => $this->userAssignmentRepository->findLeastLoadedSalesUserId($tenantId),
        };
    }

    private function resolveRoundRobinUserId(string $tenantId, ?string $lastUserId): ?string
    {
        $users = $this->userAssignmentRepository->listSalesUsers($tenantId);
        if ($users === []) {
            return null;
        }

        $selected = $users[0];

        if ($lastUserId) {
            $lastIndex = collect($users)->search(fn (object $user) => $user->id === $lastUserId);
            if ($lastIndex !== false) {
                $selected = $users[($lastIndex + 1) % count($users)];
            }
        }

        $this->tenantAutomationSettingsService->updateRoundRobinCursor($tenantId, $selected->id);

        return $selected->id;
    }
}
