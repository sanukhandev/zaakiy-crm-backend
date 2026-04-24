<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationRuleController extends Controller
{
    use ApiResponse;

    private const TRIGGERS = [
        'lead_created',
        'message_received',
        'stage_changed',
    ];

    private function resolveAuth(Request $request): ?array
    {
        $auth = $request->attributes->get('auth');

        if (!is_array($auth) || empty($auth['tenant_id']) || empty($auth['user_id'])) {
            return null;
        }

        return $auth;
    }

    public function index(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $rows = DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'trigger_type' => $row->trigger_type,
                'conditions' => is_string($row->conditions) ? (json_decode($row->conditions, true) ?? []) : ($row->conditions ?? []),
                'actions' => is_string($row->actions) ? (json_decode($row->actions, true) ?? []) : ($row->actions ?? []),
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
            ])
            ->values()
            ->all();

        return $this->success($rows, 'Automation rules fetched');
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $payload = $request->validate([
            'trigger_type' => ['required', 'string', 'in:lead_created,message_received,stage_changed'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $id = (string) Str::uuid();

        DB::table('automation_rules')->insert([
            'id' => $id,
            'tenant_id' => $auth['tenant_id'],
            'trigger_type' => $payload['trigger_type'],
            'conditions' => json_encode($payload['conditions'] ?? []),
            'actions' => json_encode($payload['actions'] ?? []),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('id', $id)
            ->first();

        return $this->success([
            'id' => $created->id,
            'tenant_id' => $created->tenant_id,
            'trigger_type' => $created->trigger_type,
            'conditions' => is_string($created->conditions) ? (json_decode($created->conditions, true) ?? []) : ($created->conditions ?? []),
            'actions' => is_string($created->actions) ? (json_decode($created->actions, true) ?? []) : ($created->actions ?? []),
            'is_active' => (bool) $created->is_active,
            'created_at' => $created->created_at,
        ], 'Automation rule created', [], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $rule = DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('id', $id)
            ->first();

        if (!$rule) {
            return $this->failure('Automation rule not found', null, [], 404);
        }

        $payload = $request->validate([
            'trigger_type' => ['sometimes', 'string', 'in:lead_created,message_received,stage_changed'],
            'conditions' => ['sometimes', 'array'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updates = ['updated_at' => now()];

        if (array_key_exists('trigger_type', $payload)) {
            $updates['trigger_type'] = $payload['trigger_type'];
        }

        if (array_key_exists('conditions', $payload)) {
            $updates['conditions'] = json_encode($payload['conditions'] ?? []);
        }

        if (array_key_exists('actions', $payload)) {
            $updates['actions'] = json_encode($payload['actions'] ?? []);
        }

        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = (bool) $payload['is_active'];
        }

        DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('id', $id)
            ->update($updates);

        $updated = DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('id', $id)
            ->first();

        return $this->success([
            'id' => $updated->id,
            'tenant_id' => $updated->tenant_id,
            'trigger_type' => $updated->trigger_type,
            'conditions' => is_string($updated->conditions) ? (json_decode($updated->conditions, true) ?? []) : ($updated->conditions ?? []),
            'actions' => is_string($updated->actions) ? (json_decode($updated->actions, true) ?? []) : ($updated->actions ?? []),
            'is_active' => (bool) $updated->is_active,
            'created_at' => $updated->created_at,
        ], 'Automation rule updated');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $deleted = DB::table('automation_rules')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('id', $id)
            ->delete();

        if ($deleted === 0) {
            return $this->failure('Automation rule not found', null, [], 404);
        }

        return $this->success(null, 'Automation rule deleted');
    }
}
