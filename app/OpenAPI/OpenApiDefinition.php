<?php

/**
 * @OA\OpenApi(
 *   openapi="3.0.0",
 *   @OA\Info(
 *     title="ZaakiyCRM API",
 *     description="CRM API with Supabase JWT authentication",
 *     version="1.0.0",
 *     @OA\Contact(
 *       name="Support",
 *       url="https://github.com/zaakiy"
 *     )
 *   ),
 *   @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 *   ),
 *   @OA\Server(
 *     url="https://api.zaakicrm.com/api",
 *     description="Production Server"
 *   )
 * )
 */

namespace App\OpenAPI;

class OpenApiDefinition
{
    // This file contains OpenAPI/Swagger definitions for all routes
}

/**
 * @OA\SecurityScheme(
 *   type="http",
 *   description="Bearer JWT token from Supabase Auth",
 *   name="Token",
 *   in="header",
 *   scheme="bearer",
 *   bearerFormat="JWT",
 *   securityScheme="bearerAuth",
 * )
 */
