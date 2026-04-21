<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LeadService;

class LeadController extends Controller
{
    protected $leadService;

    public function __construct()
    {
        $this->leadService = new LeadService();
    }

    public function index(Request $request)
    {
        $auth = $request->attributes->get('auth');

        return response()->json([
            'success' => true,
            'data' => $this->leadService->listLeads($auth)
        ]);
    }

    public function store(Request $request)
    {
        $auth = $request->attributes->get('auth');

        $id = $this->leadService->createLead($auth, $request->all());

        return response()->json([
            'success' => true,
            'id' => $id
        ]);
    }

    public function update(Request $request, $id)
    {
        $auth = $request->attributes->get('auth');

        $this->leadService->updateLead($auth, $id, $request->all());

        return response()->json([
            'success' => true
        ]);
    }
}