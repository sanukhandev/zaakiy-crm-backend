<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantWhatsAppIntegrationRequest;
use App\Services\TenantWhatsAppIntegrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantWhatsAppIntegrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantWhatsAppIntegrationService $integrationService,
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
            $this->integrationService->getTenantConfig($auth),
            'WhatsApp integration fetched successfully',
        );
    }

    public function update(UpdateTenantWhatsAppIntegrationRequest $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        return $this->success(
            $this->integrationService->updateTenantConfig($auth, $request->validated()),
            'WhatsApp integration updated successfully',
        );
    }
}
