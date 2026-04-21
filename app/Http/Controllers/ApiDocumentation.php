<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocumentation
{
    /**
     * @OA\Get(
     *   path="/v1/health",
     *   summary="Health check",
     *   description="Check if the API is running",
     *   tags={"Health"},
     *   @OA\Response(
     *     response=200,
     *     description="API is healthy",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="string", example="ok")
     *     )
     *   )
     * )
     */
    public function health() {}

    /**
     * @OA\Get(
     *   path="/v1/me",
     *   summary="Get current user session",
     *   description="Retrieve the authenticated user's information from JWT token",
     *   tags={"Auth"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="User session data",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="user_id", type="string", example="3a0b1f1e-c6ff-4b9f-a4b3-d230609c41ce"),
     *         @OA\Property(property="email", type="string", example="test@demo.com"),
     *         @OA\Property(property="role", type="string", example="authenticated"),
     *         @OA\Property(property="tenant_id", type="string", nullable=true),
     *         @OA\Property(property="jwt", type="object", description="Full decoded JWT token")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized - Missing or invalid token"
     *   )
     * )
     */
    public function me() {}

    /**
     * @OA\Get(
     *   path="/v1/session",
     *   summary="Get current session",
     *   description="Retrieve the authenticated user's current session details",
     *   tags={"Session"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="Session data",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="user_id", type="string", example="3a0b1f1e-c6ff-4b9f-a4b3-d230609c41ce"),
     *         @OA\Property(property="email", type="string", example="test@demo.com"),
     *         @OA\Property(property="role", type="string", example="authenticated"),
     *         @OA\Property(property="tenant_id", type="string", nullable=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *   )
     * )
     */
    public function session() {}

    /**
     * @OA\Get(
     *   path="/v1/leads",
     *   summary="List all leads",
     *   description="Retrieve a list of all leads in the CRM",
     *   tags={"Leads"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="List of leads",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="name", type="string", example="John Doe"),
     *           @OA\Property(property="email", type="string", example="john@example.com"),
     *           @OA\Property(property="phone", type="string", example="+1234567890"),
     *           @OA\Property(property="status", type="string", example="new"),
     *           @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *   )
     * )
     */
    public function leads() {}

    /**
     * @OA\Post(
     *   path="/v1/leads",
     *   summary="Create a new lead",
     *   description="Add a new lead to the CRM",
     *   tags={"Leads"},
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name", "email"},
     *       @OA\Property(property="name", type="string", example="Jane Doe"),
     *       @OA\Property(property="email", type="string", example="jane@example.com"),
     *       @OA\Property(property="phone", type="string", example="+1987654321"),
     *       @OA\Property(property="status", type="string", example="new")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Lead created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=2),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="created_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error"
     *   )
     * )
     */
    public function storeLeads() {}

    /**
     * @OA\Patch(
     *   path="/v1/leads/{id}",
     *   summary="Update a lead",
     *   description="Update an existing lead's information",
     *   tags={"Leads"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Lead ID",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", example="Jane Smith"),
     *       @OA\Property(property="email", type="string", example="jane.smith@example.com"),
     *       @OA\Property(property="phone", type="string", example="+1987654321"),
     *       @OA\Property(property="status", type="string", example="qualified")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Lead updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized"
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Lead not found"
     *   )
     * )
     */
    public function updateLeads() {}
}
