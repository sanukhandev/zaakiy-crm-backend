<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\LeadService;

class LeadController extends Controller
{
    public function __construct(protected LeadService $leadService) {}

    public function index(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');

        $data = $this->leadService->listLeads($auth, $request->query());

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Leads fetched successfully',
        ]);
    }

    public function store(
        \App\Http\Requests\StoreLeadRequest $request,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');

        $result = $this->leadService->createLead($auth, $request->validated());

        if (isset($result['duplicate']) && $result['duplicate']) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Duplicate lead found',
                    'data' => $result['data'],
                ],
                409,
            );
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Lead created successfully',
        ]);
    }

    public function update(
        \App\Http\Requests\UpdateLeadRequest $request,
        int $id,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');

        try {
            $this->leadService->updateLead($auth, $id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json(
                ['success' => false, 'message' => 'Lead not found'],
                404,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $auth = $request->attributes->get('auth');

        try {
            $this->leadService->deleteLead($auth, $id);
        } catch (ModelNotFoundException) {
            return response()->json(
                ['success' => false, 'message' => 'Lead not found'],
                404,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }
}
