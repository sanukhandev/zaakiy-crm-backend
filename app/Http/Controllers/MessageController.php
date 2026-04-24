<?php

namespace App\Http\Controllers;

use App\Services\SendMessageService;
use App\Services\MessageService;
use App\Services\LeadActivityService;
use App\Repositories\LeadRepository;
use App\DTOs\SendMessageDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function __construct(
        protected SendMessageService $sendMessageService,
        protected MessageService $messageService,
        protected LeadActivityService $activityService,
        protected LeadRepository $leadRepository,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $dto = SendMessageDTO::fromRequest($request->all(), $auth['tenant_id']);
            $message = $this->sendMessageService->send($dto);

            return response()->json($message, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => 'Lead not found'], 404);
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

        // Verify lead belongs to tenant
        $lead = $this->leadRepository->findByIdForTenant($leadId, $auth['tenant_id']);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $limit = min((int) $request->query('limit', 50), 100);
        $offset = max((int) $request->query('offset', 0), 0);

        $messages = $this->messageService->getConversation($auth['tenant_id'], $leadId, $limit);

        // Reset unread count when fetching messages
        $this->leadRepository->resetUnreadCount($auth['tenant_id'], $leadId);

        return response()->json([
            'data' => $messages,
            'count' => count($messages),
        ]);
    }

    public function getInbox(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $assignedTo = $request->query('assigned_to');
        $unreadOnly = (bool) $request->query('unread_only', false);
        $needsFollowUp = (bool) $request->query('needs_follow_up', false);
        $limit = min((int) $request->query('limit', 50), 100);
        $offset = max((int) $request->query('offset', 0), 0);

        $inbox = $this->messageService->getInbox(
            $auth['tenant_id'],
            $assignedTo,
            $unreadOnly,
            $needsFollowUp,
            $limit,
            $offset
        );

        return response()->json([
            'data' => $inbox,
            'count' => count($inbox),
        ]);
    }

    public function markAsRead(Request $request, string $leadId): JsonResponse
    {
        $auth = $request->attributes->get('auth');
        if (!$auth) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Verify lead belongs to tenant
        $lead = $this->leadRepository->findByIdForTenant($leadId, $auth['tenant_id']);
        if (!$lead) {
            return response()->json(['error' => 'Lead not found'], 404);
        }

        $this->leadRepository->resetUnreadCount($auth['tenant_id'], $leadId);

        return response()->json(['success' => true]);
    }
}
