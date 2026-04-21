<?php

return [
    'api' => [
        'title' => 'ZaakiyCRM API',
        'description' => 'CRM API with Supabase JWT authentication',
        'version' => '1.0.0',
        'host' => env('SWAGGER_HOST', 'localhost:8000'),
        'basePath' => '/api',
        'schemes' => ['http', 'https'],
        'consumes' => ['application/json'],
        'produces' => ['application/json'],
        'security_definitions' => [
            'bearerToken' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in' => 'header',
                'description' => 'Bearer token (JWT from Supabase)',
            ],
        ],
    ],
];
