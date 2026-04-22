<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LeadService;

class LeadController extends Controller
{
    protected $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    private function getService()
    {
        return $this->leadService;
    }

    public function index(Request $request)
    {
        $auth = $request->attributes->get('auth');

        $data = $this->leadService->listLeads($auth, $request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Leads fetched successfully',
        ]);
    }

    public function store(\App\Http\Requests\StoreLeadRequest $request)
    {
        $auth = $request->attributes->get('auth');

        $result = $this->getService()->createLead($auth, $request->validated());

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

    public function update(\App\Http\Requests\UpdateLeadRequest $request, $id)
    {
        $auth = $request->attributes->get('auth');

        $this->getService()->updateLead($auth, $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $auth = $request->attributes->get('auth');

        $this->getService()->deleteLead($auth, $id);

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }
}
