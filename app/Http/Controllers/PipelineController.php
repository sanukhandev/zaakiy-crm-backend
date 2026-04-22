<?php

namespace App\Http\Controllers;

use App\Services\PipelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    use ApiResponse;

    public function __construct(protected PipelineService $pipelineService)
    {
    }

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

        $data = $this->pipelineService->getPipeline($auth);

        return $this->success($data, 'Pipeline fetched successfully');
    }
}
