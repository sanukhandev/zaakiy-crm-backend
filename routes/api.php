<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SessionController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\DocsController;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });
    Route::middleware(['auth.api'])->group(function () {
        Route::get('/me', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->attributes->get('auth'),
            ]);
        });
        Route::get('/session', [SessionController::class, 'getSession']);
        Route::get('/leads', [LeadController::class, 'index']);
        Route::post('/leads', [LeadController::class, 'store']);
        Route::patch('/leads/{id}', [LeadController::class, 'update']);
        Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
    });
});
