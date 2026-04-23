<?php

namespace App\Http\Controllers;

use App\Services\WebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    use ApiResponse;

    public function __construct(protected WebhookService $webhookService) {}

    public function meta(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->ingestMeta($request);

            return $this->success($result, 'Webhook processed', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process webhook', null, [
                'webhook' => [$e->getMessage()],
            ], 500);
        }
    }

    public function whatsapp(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->ingestWhatsApp($request);

            return $this->success($result, 'WhatsApp webhook processed', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process webhook', null, [
                'webhook' => [$e->getMessage()],
            ], 500);
        }
    }
}
