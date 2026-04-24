<?php

namespace App\Http\Controllers;

use App\DTOs\ClaimConversationDTO;
use App\DTOs\ReleaseConversationDTO;
use App\Services\SendMessageService;
use App\Services\MessageService;
use App\Services\InboxService;
use App\DTOs\SendMessageDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function __construct(
        protected SendMessageService $sendMessageService,
        protected MessageService $messageService,
        protected InboxService $inboxService,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $dto = SendMessageDTO::fromRequest($request->all(), $auth['tenant_id']);
            $dto->createdBy = (string) ($auth['internal_user_id'] ?? $auth['user_id']);
            $message = $this->sendMessageService->send($dto);

            return response()->json($message, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('Message send failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    public function getMessages(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $leadId = $request->query('lead_id');
        if (!$leadId) {
            return response()->json(['error' => 'lead_id required'], 400);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $page = max((int) $request->query('page', 1), 1);

        try {
            $messages = $this->messageService->getConversation($auth['tenant_id'], $leadId, $perPage, $page);
            $this->inboxService->markConversationAsRead($auth['tenant_id'], $leadId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        return response()->json([
            'data' => $messages['data'] ?? [],
            'count' => count($messages['data'] ?? []),
            'meta' => [
                'current_page' => $messages['current_page'] ?? 1,
                'last_page' => $messages['last_page'] ?? 1,
                'per_page' => $messages['per_page'] ?? $perPage,
                'total' => $messages['total'] ?? 0,
            ],
        ]);
    }

    public function getInbox(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $assignedTo = $request->query('assigned_to');
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);
        $needsFollowUp = filter_var($request->query('needs_follow_up', false), FILTER_VALIDATE_BOOLEAN);
        $ownedByMe = filter_var($request->query('owned_by_me', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = max((int) $request->query('page', 1), 1);
        $ownerId = (string) ($auth['internal_user_id'] ?? $auth['user_id']);

        $inbox = $this->messageService->getInbox(
            $auth['tenant_id'],
            $assignedTo,
            $unreadOnly,
            $needsFollowUp,
            $ownedByMe,
            $ownerId,
            $perPage,
            $page,
        );

        return response()->json([
            'data' => $inbox['data'] ?? [],
            'count' => count($inbox['data'] ?? []),
            'meta' => [
                'current_page' => $inbox['current_page'] ?? 1,
                'last_page' => $inbox['last_page'] ?? 1,
                'per_page' => $inbox['per_page'] ?? $perPage,
                'total' => $inbox['total'] ?? 0,
            ],
        ]);
    }

    public function markAsRead(Request $request, string $leadId): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->inboxService->markConversationAsRead($auth['tenant_id'], $leadId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function claim(Request $request, string $leadId): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $dto = ClaimConversationDTO::fromAuth($leadId, $auth, $request->all());
            $lead = $this->inboxService->claimConversation($dto);

            return response()->json(['success' => true, 'data' => $lead]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function release(Request $request, string $leadId): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $dto = ReleaseConversationDTO::fromAuth($leadId, $auth);
            $this->inboxService->releaseConversation($dto);

            return response()->json(['success' => true]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function markInboxRead(Request $request, string $leadId): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->inboxService->markConversationAsRead($auth['tenant_id'], $leadId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        return response()->json(['success' => true]);
    }
}
