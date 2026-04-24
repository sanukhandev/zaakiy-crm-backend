<?php

namespace Tests\Unit;

use App\DTOs\WebhookLeadPayload;
use App\DTOs\WebhookMessagePayload;
use App\Repositories\LeadRepository;
use App\Services\LeadLinkingService;
use App\Services\LeadService;
use App\Services\MessageService;
use Mockery;
use Tests\TestCase;

class LeadLinkingServiceTest extends TestCase
{
    public function test_it_links_message_to_existing_tenant_message_before_creating_new_lead(): void
    {
        $leadRepository = Mockery::mock(LeadRepository::class);
        $leadService = Mockery::mock(LeadService::class);
        $messageService = Mockery::mock(MessageService::class);

        $service = new LeadLinkingService($leadRepository, $leadService, $messageService);

        $messageService->shouldReceive('findByExternalId')
            ->once()
            ->with('tenant-a', 'wamid.123')
            ->andReturn((object) ['lead_id' => 'lead-1']);

        $leadRepository->shouldReceive('findByIdForTenant')
            ->once()
            ->with('lead-1', 'tenant-a')
            ->andReturn((object) ['id' => 'lead-1', 'tenant_id' => 'tenant-a']);

        $leadPayload = WebhookLeadPayload::fromArray([
            'name' => 'Test User',
            'phone' => '+1234567890',
            'metadata' => [],
        ], 'whatsapp');

        $messagePayload = WebhookMessagePayload::fromArray([
            'message' => 'hello',
            'external_id' => 'wamid.123',
            'phone' => '+1234567890',
            'metadata' => [],
        ], 'whatsapp');

        $result = $service->resolveLead('tenant-a', $leadPayload, $messagePayload);

        $this->assertSame('matched_external_message', $result['action']);
        $this->assertSame('lead-1', $result['lead']->id);
    }

    public function test_it_does_not_fallback_to_other_tenants_when_no_match_exists(): void
    {
        $leadRepository = Mockery::mock(LeadRepository::class);
        $leadService = Mockery::mock(LeadService::class);
        $messageService = Mockery::mock(MessageService::class);

        $service = new LeadLinkingService($leadRepository, $leadService, $messageService);

        $messageService->shouldReceive('findByExternalId')
            ->once()
            ->with('tenant-a', 'wamid.missing')
            ->andReturn(null);

        $leadRepository->shouldReceive('findByPhoneOrEmailAndTenant')
            ->once()
            ->with('tenant-a', '+1234567890', null)
            ->andReturn(null);

        $leadPayload = WebhookLeadPayload::fromArray([
            'name' => 'Tenant A Lead',
            'phone' => '+1234567890',
            'metadata' => [],
        ], 'whatsapp');

        $messagePayload = WebhookMessagePayload::fromArray([
            'message' => 'hello',
            'external_id' => 'wamid.missing',
            'phone' => '+1234567890',
            'metadata' => [],
        ], 'whatsapp');

        $result = $service->resolveLead('tenant-a', $leadPayload, $messagePayload, false);

        $this->assertNull($result['lead']);
        $this->assertSame('not_found', $result['action']);
    }
}