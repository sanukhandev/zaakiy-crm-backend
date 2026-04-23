<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lead\MoveLeadStageRequest;
use App\Http\Requests\Pipeline\StorePipelineStageRequest;
use App\Http\Requests\Pipeline\UpdatePipelineStageRequest;
use App\Services\PipelineService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    use ApiResponse;

    public function __construct(protected PipelineService $pipelineService) {}

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

    public function storeStage(
        StorePipelineStageRequest $request,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $data = $this->pipelineService->createStage($auth, $request->validated());

        return $this->success($data, 'Pipeline stage created', [], 201);
    }

    public function updateStage(
        UpdatePipelineStageRequest $request,
        string $id,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $data = $this->pipelineService->updateStage(
                $auth,
                $id,
                $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->failure('Pipeline stage not found', null, [], 404);
        }

        return $this->success($data, 'Pipeline stage updated');
    }

    public function moveLeadStage(
        MoveLeadStageRequest $request,
        string $id,
    ): JsonResponse {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        try {
            $data = $this->pipelineService->moveLeadToStage(
                $auth,
                $id,
                $request->validated(),
            );
        } catch (ModelNotFoundException $e) {
            return $this->failure($e->getMessage(), null, [], 404);
        }

        return $this->success($data, 'Lead moved successfully');
    }
}
