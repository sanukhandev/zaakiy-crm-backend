<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SessionController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\WebhookController;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::post('/webhooks/meta', [WebhookController::class, 'meta'])
        ->middleware('throttle:webhook-ingest');
    Route::post('/webhooks/whatsapp', [WebhookController::class, 'whatsapp'])
        ->middleware('throttle:webhook-ingest');

    Route::middleware(['auth.api'])->group(function () {
        Route::get('/me', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->attributes->get('auth'),
            ]);
        });
        Route::get('/session', [SessionController::class, 'getSession']);
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

        Route::patch('/leads/{id}/move', [LeadController::class, 'move'])->middleware('throttle:lead-write');
        Route::patch('/leads/{id}/stage', [PipelineController::class, 'moveLeadStage'])
            ->middleware('throttle:lead-write');
        Route::patch('/leads/{id}', [LeadController::class, 'update']);
        Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    });
});
