<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(protected AnalyticsService $analyticsService) {}

    public function overview(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');

        if (
            !is_array($auth) ||
            empty($auth['tenant_id']) ||
            empty($auth['user_id'])
        ) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $data = $this->analyticsService->getOverview($auth['tenant_id'], [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'source' => $request->query('source'),
        ]);

        return $this->success($data, 'Analytics overview fetched successfully');
    }
}
