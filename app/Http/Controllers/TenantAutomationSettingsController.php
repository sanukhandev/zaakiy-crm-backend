<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantAutomationSettingsRequest;
use App\Services\TenantAutomationSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAutomationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantAutomationSettingsService $settingsService,
    ) {}

    private function resolveAuth(Request $request): ?array
    {
        $auth = $request->attributes->get('auth');

        if (!is_array($auth) || empty($auth['tenant_id']) || empty($auth['user_id'])) {
            return null;
        }

        return $auth;
    }

    public function show(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        return $this->success(
            $this->settingsService->get($auth),
            'Automation settings fetched successfully',
        );
    }

    public function update(UpdateTenantAutomationSettingsRequest $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        return $this->success(
            $this->settingsService->update($auth, $request->validated()),
            'Automation settings updated successfully',
        );
    }
}
