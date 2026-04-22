<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\LeadService;
use App\Support\ApiResponse;
use App\Http\Requests\Lead\BulkLeadAssignRequest;
use App\Http\Requests\Lead\BulkLeadDeleteRequest;
use App\Http\Requests\Lead\BulkLeadUpdateRequest;
use App\Http\Requests\Lead\StoreLeadActivityRequest;

class LeadController extends Controller
{
    use ApiResponse;

    public function __construct(protected LeadService $leadService) {}

    private function resolveAuth(Request $request): ?array
    {
        $auth = $request->attributes->get('auth');

        if (
            !is_array($auth) ||
            empty($auth['tenant_id']) ||
            empty($auth['user_id'])
        ) {
            return null;
        }

        return $auth;
    }

    public function index(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $data = $this->leadService->listLeads($auth, $request->query());

        return $this->success($data->items(), 'Leads fetched successfully', [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
        ]);
    }

    public function store(
        \App\Http\Requests\StoreLeadRequest $request,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $result = $this->leadService->createLead($auth, $request->validated());

        if (isset($result['duplicate']) && $result['duplicate']) {
            return $this->failure(
                'Duplicate lead found',
                $result['data'],
                [],
                409,
            );
        }

        return $this->success($result, 'Lead created successfully', [], 201);
    }

    public function update(
        \App\Http\Requests\UpdateLeadRequest $request,
        string $id,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $this->leadService->updateLead($auth, $id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->failure('Lead not found', null, [], 404);
        }

        return $this->success(null, 'Lead updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $this->leadService->deleteLead($auth, $id);
        } catch (ModelNotFoundException) {
            return $this->failure('Lead not found', null, [], 404);
        }

        return $this->success(null, 'Lead deleted successfully');
    }

    public function storeActivity(
        StoreLeadActivityRequest $request,
        string $id,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $data = $this->leadService->addLeadActivity(
                $auth,
                $id,
                $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->failure('Lead not found', null, [], 404);
        }

        return $this->success($data, 'Lead activity added', [], 201);
    }

    public function listActivities(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $data = $this->leadService->listLeadActivities(
                $auth,
                $id,
                $request->query(),
            );
        } catch (ModelNotFoundException) {
            return $this->failure('Lead not found', null, [], 404);
        }

        return $this->success(
            $data->items(),
            'Lead activities fetched successfully',
            [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        );
    }

    public function bulkUpdate(BulkLeadUpdateRequest $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }
        $payload = $request->validated();

        if (count(array_diff(array_keys($payload), ['lead_ids'])) === 0) {
            return $this->failure(
                'No fields provided for bulk update',
                null,
                [],
                422,
            );
        }

        $affected = $this->leadService->bulkUpdateLeads($auth, $payload);

        return $this->success(
            ['affected' => $affected],
            'Leads updated successfully',
        );
    }

    public function bulkAssign(BulkLeadAssignRequest $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }
        $affected = $this->leadService->bulkAssignLeads(
            $auth,
            $request->validated(),
        );

        return $this->success(
            ['affected' => $affected],
            'Leads assigned successfully',
        );
    }

    public function bulkDelete(BulkLeadDeleteRequest $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }
        $affected = $this->leadService->bulkDeleteLeads(
            $auth,
            $request->validated(),
        );

        return $this->success(
            ['affected' => $affected],
            'Leads deleted successfully',
        );
    }
}
