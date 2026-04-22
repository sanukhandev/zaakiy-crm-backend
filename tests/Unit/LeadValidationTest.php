<?php

namespace Tests\Unit;

use App\Http\Requests\Lead\BulkLeadAssignRequest;
use App\Http\Requests\Lead\BulkLeadDeleteRequest;
use App\Http\Requests\Lead\BulkLeadUpdateRequest;
use App\Http\Requests\Lead\StoreLeadActivityRequest;
use App\Http\Requests\StoreLeadRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LeadValidationTest extends TestCase
{
    public function test_store_lead_request_accepts_valid_payload(): void
    {
        $request = new StoreLeadRequest();

        $validator = Validator::make(
            [
                'name' => 'John Doe',
                'email' => 'john@test.com',
                'phone' => '971500000000',
                'status' => 'new',
                'metadata' => ['campaign' => 'organic'],
            ],
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_lead_request_rejects_invalid_status(): void
    {
        $request = new StoreLeadRequest();

        $validator = Validator::make(
            [
                'name' => 'John Doe',
                'status' => 'invalid-status',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_bulk_update_request_validates_uuid_ids(): void
    {
        $request = new BulkLeadUpdateRequest();

        $validator = Validator::make(
            [
                'lead_ids' => ['not-a-uuid'],
                'status' => 'new',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
    }

    public function test_bulk_assign_request_requires_assignee(): void
    {
        $request = new BulkLeadAssignRequest();

        $validator = Validator::make(
            [
                'lead_ids' => ['f84d7f31-8c31-47c1-8f5b-f3b78f7f9386'],
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'assigned_to',
            $validator->errors()->toArray(),
        );
    }

    public function test_bulk_delete_request_accepts_valid_payload(): void
    {
        $request = new BulkLeadDeleteRequest();

        $validator = Validator::make(
            [
                'lead_ids' => ['f84d7f31-8c31-47c1-8f5b-f3b78f7f9386'],
            ],
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_activity_request_rejects_invalid_type(): void
    {
        $request = new StoreLeadActivityRequest();

        $validator = Validator::make(
            [
                'type' => 'sms',
                'content' => 'Called customer',
            ],
            $request->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }
}
