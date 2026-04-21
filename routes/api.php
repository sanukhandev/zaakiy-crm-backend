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
            'data' => $request->attributes->get('auth')
        ]);

    });
    Route::get('/session', [SessionController::class, 'getSession']);
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::patch('/leads/{id}', [LeadController::class, 'update']);
});
});

// Swagger/OpenAPI documentation endpoints
Route::get('/docs', [DocsController::class, 'swagger']);

Route::get('/swagger-spec.json', function () {
    return response()->json([
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'ZaakiyCRM API',
            'description' => 'CRM API with Supabase JWT authentication',
            'version' => '1.0.0',
        ],
        'servers' => [['url' => '/api']],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ]
            ]
        ],
        'paths' => [
            '/v1/health' => [
                'get' => ['summary' => 'Health check', 'tags' => ['Health'], 'responses' => ['200' => ['description' => 'OK']]]
            ],
            '/v1/me' => [
                'get' => ['summary' => 'Current user', 'tags' => ['Auth'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'User data'], '401' => ['description' => 'Unauthorized']]]
            ],
            '/v1/session' => [
                'get' => ['summary' => 'Session', 'tags' => ['Session'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Session data'], '401' => ['description' => 'Unauthorized']]]
            ],
            '/v1/leads' => [
                'get' => ['summary' => 'List leads', 'tags' => ['Leads'], 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Leads list']]],
                'post' => ['summary' => 'Create lead', 'tags' => ['Leads'], 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Created']]]
            ],
            '/v1/leads/{id}' => [
                'patch' => ['summary' => 'Update lead', 'tags' => ['Leads'], 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'Updated']]]
            ]
        ]
    ]);
});