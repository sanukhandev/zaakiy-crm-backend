<?php

namespace App\Http\Controllers;

use App\Services\WebhookService;
use App\Services\MessageService;
use App\DTOs\MessageStatusUpdateDTO;
use App\Jobs\UpdateMessageStatusJob;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WebhookService $webhookService,
        protected MessageService $messageService,
    ) {}

    public function meta(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->ingestMeta($request);

            return $this->success($result, 'Webhook queued', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400, [
                'webhook' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process webhook', null, [], 500, [
                'webhook' => [$e->getMessage()],
            ]);
        }
    }

    public function tiktok(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->ingestTikTok($request);

            return $this->success($result, 'TikTok webhook queued', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400, [
                'webhook' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process webhook', null, [], 500, [
                'webhook' => [$e->getMessage()],
            ]);
        }
    }

    public function whatsapp(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->ingestWhatsApp($request);

            return $this->success($result, 'WhatsApp webhook queued', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400, [
                'webhook' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process webhook', null, [], 500, [
                'webhook' => [$e->getMessage()],
            ]);
        }
    }

    public function whatsappStatus(Request $request): JsonResponse
    {
        try {
            $payload = $request->json()->all();
            $auth = $request->attributes->get('auth');
            if (is_array($auth) && !empty($auth['tenant_id'])) {
                $payload['tenant_id'] = $auth['tenant_id'];
            }

            $dto = MessageStatusUpdateDTO::fromWebhook($payload);

            dispatch(new UpdateMessageStatusJob($dto->tenantId, $dto->externalId, $dto->status, $dto->payload));

            return $this->success(['queued' => true], 'Status update queued', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process status update', null, [
                'error' => [$e->getMessage()],
            ], 500);
        }
    }

    public function metaStatus(Request $request): JsonResponse
    {
        try {
            $payload = $request->json()->all();
            $auth = $request->attributes->get('auth');
            if (is_array($auth) && !empty($auth['tenant_id'])) {
                $payload['tenant_id'] = $auth['tenant_id'];
            }

            $dto = MessageStatusUpdateDTO::fromWebhook($payload);

            dispatch(new UpdateMessageStatusJob($dto->tenantId, $dto->externalId, $dto->status, $dto->payload));

            return $this->success(['queued' => true], 'Status update queued', [], 202);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage(), null, [], 400);
        } catch (\Throwable $e) {
            return $this->failure('Failed to process status update', null, [
                'error' => [$e->getMessage()],
            ], 500);
        }
    }
}
