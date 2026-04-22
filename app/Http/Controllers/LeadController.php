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
        if (!$this->leadService) {
            $this->leadService = new LeadService();
        }
        return $this->leadService;
    }

    public function index(Request $request)
    {
        $auth = $request->attributes->get('auth');

        $data = $this->leadService->listLeads($auth, $request->all());

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $auth = $request->attributes->get('auth');

        $id = $this->getService()->createLead($auth, $request->all());

        return response()->json([
            'success' => true,
            'id' => $id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $auth = $request->attributes->get('auth');

        $this->getService()->updateLead($auth, $id, $request->all());

        return response()->json([
            'success' => true,
        ]);
    }
}
