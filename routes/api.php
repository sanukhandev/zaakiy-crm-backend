<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SessionController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\TenantAutomationSettingsController;
use App\Http\Controllers\TenantWhatsAppIntegrationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WebhookKeyController;
use Illuminate\Support\Facades\DB;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::post('/webhooks/meta', [WebhookController::class, 'meta'])
        ->middleware('throttle:webhook-ingest');
    Route::post('/webhooks/tiktok', [WebhookController::class, 'tiktok'])
        ->middleware('throttle:webhook-ingest');
    Route::post('/webhooks/whatsapp', [WebhookController::class, 'whatsapp'])
        ->middleware('throttle:webhook-ingest');
    Route::post('/webhooks/whatsapp/status', [WebhookController::class, 'whatsappStatus'])
        ->middleware('throttle:webhook-ingest');
    Route::post('/webhooks/meta/status', [WebhookController::class, 'metaStatus'])
        ->middleware('throttle:webhook-ingest');

    Route::middleware(['auth.api'])->group(function () {
        Route::get('/me', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->attributes->get('auth'),
            ]);
        });
        Route::get('/session', [SessionController::class, 'getSession']);
        Route::get('/integrations/whatsapp', [TenantWhatsAppIntegrationController::class, 'show']);
        Route::put('/integrations/whatsapp', [TenantWhatsAppIntegrationController::class, 'update'])
            ->middleware('throttle:bulk-write');
        Route::get('/integrations/automation', [TenantAutomationSettingsController::class, 'show']);
        Route::put('/integrations/automation', [TenantAutomationSettingsController::class, 'update'])
            ->middleware('throttle:bulk-write');
        Route::get('/webhooks/whatsapp/key', [WebhookKeyController::class, 'showWhatsAppKey']);
        Route::post('/webhooks/whatsapp/key/regenerate', [WebhookKeyController::class, 'regenerateWhatsAppKey'])
            ->middleware('throttle:bulk-write');

        Route::get('/users', function (\Illuminate\Http\Request $request) {
            $auth = $request->attributes->get('auth');
            if (empty($auth['tenant_id'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            $users = DB::table('users')
                ->where('tenant_id', $auth['tenant_id'])
                ->select(['id', 'name', 'email', 'role'])
                ->orderBy('name')
                ->get();
            return response()->json(['success' => true, 'data' => $users, 'meta' => [], 'message' => 'Users fetched']);
        });

        Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);

        Route::get('/pipeline', [PipelineController::class, 'index']);
        Route::get('/pipelines', [PipelineController::class, 'index']);
        Route::post('/pipelines/stages', [PipelineController::class, 'storeStage'])
            ->middleware('throttle:bulk-write');
        Route::patch('/pipelines/stages/{id}', [PipelineController::class, 'updateStage'])
            ->middleware('throttle:bulk-write');
        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:lead-write');

        Route::patch('/leads/bulk', [LeadController::class, 'bulkUpdate'])->middleware('throttle:bulk-write');
        Route::post('/leads/bulk/assign', [
            LeadController::class,
            'bulkAssign',
        ])->middleware('throttle:bulk-write');
        Route::delete('/leads/bulk', [LeadController::class, 'bulkDelete'])->middleware('throttle:bulk-write');

        Route::get('/leads/{id}/activities', [
            LeadController::class,
            'listActivities',
        ]);
        Route::post('/leads/{id}/activities', [
            LeadController::class,
            'storeActivity',
        ]);
        Route::get('/leads/{id}/messages', [LeadController::class, 'listMessages']);
        Route::post('/leads/{id}/messages/whatsapp', [LeadController::class, 'sendWhatsAppMessage'])
            ->middleware('throttle:lead-write');

        Route::get('/inbox', [MessageController::class, 'getInbox']);
        Route::post('/inbox/{leadId}/claim', [MessageController::class, 'claim'])->middleware('throttle:lead-write');
        Route::post('/inbox/{leadId}/release', [MessageController::class, 'release'])->middleware('throttle:lead-write');
        Route::post('/inbox/{leadId}/read', [MessageController::class, 'markInboxRead'])->middleware('throttle:lead-write');
        Route::post('/messages/send', [MessageController::class, 'send'])->middleware('throttle:lead-write');
        Route::get('/messages', [MessageController::class, 'getMessages']);
        Route::post('/messages/read/{leadId}', [MessageController::class, 'markAsRead']);

        Route::patch('/leads/{id}/move', [LeadController::class, 'move'])->middleware('throttle:lead-write');
        Route::patch('/leads/{id}/stage', [PipelineController::class, 'moveLeadStage'])
            ->middleware('throttle:lead-write');
        Route::get('/leads/{id}', [LeadController::class, 'show']);
        Route::patch('/leads/{id}', [LeadController::class, 'update']);
        Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    });
});
