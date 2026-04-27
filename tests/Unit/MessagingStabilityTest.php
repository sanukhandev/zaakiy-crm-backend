<?php

namespace Tests\Unit;

use App\DTOs\SendMessageDTO;
use App\Repositories\LeadRepository;
use App\Repositories\MessageRepository;
use App\Services\InboxService;
use App\Services\LeadActivityService;
use App\Services\MessageService;
use App\Services\SendMessageService;
use Mockery;
use Tests\TestCase;

class MessagingStabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inbound_message_increments_unread_count(): void
    {
        $messageRepository = Mockery::mock(MessageRepository::class);
        $leadRepository = Mockery::mock(LeadRepository::class);

        $messageRepository
            ->shouldReceive('createInboundMessage')
            ->once()
            ->andReturn((object) ['id' => 'm1']);

        $leadRepository->shouldReceive('incrementUnreadCount')->once()->with('tenant-1', 'lead-1');
        $leadRepository->shouldReceive('updateLeadConversationMetadata')->once()->with('tenant-1', 'lead-1', 'inbound');
        $leadRepository
            ->shouldReceive('updateActivityTimestamps')
            ->once()
            ->with(
                'tenant-1',
                'lead-1',
                Mockery::on(fn (array $payload): bool => isset($payload['last_inbound_at'], $payload['last_activity_at'])),
            );

        $service = new MessageService($messageRepository, $leadRepository);

        $service->createInboundMessage('tenant-1', 'lead-1', 'whatsapp', 'hello', 'ext-1');

        $this->assertTrue(true);
    }

    public function test_opening_conversation_marks_unread_count_zero(): void
    {
        $leadRepository = Mockery::mock(LeadRepository::class);
        $messageService = Mockery::mock(MessageService::class);

        $leadRepository
            ->shouldReceive('findById')
            ->once()
            ->with('tenant-1', 'lead-1')
            ->andReturn((object) ['id' => 'lead-1']);

        $messageService
            ->shouldReceive('markLeadMessagesAsRead')
            ->once()
            ->with('tenant-1', 'lead-1');

        $service = new InboxService($leadRepository, $messageService);

        $service->markConversationAsRead('tenant-1', 'lead-1');

        $this->assertTrue(true);
    }

    public function test_duplicate_external_id_is_ignored(): void
    {
        $messageService = Mockery::mock(MessageService::class);
        $leadRepository = Mockery::mock(LeadRepository::class);
        $activityService = Mockery::mock(LeadActivityService::class);
        $inboxService = Mockery::mock(InboxService::class);

        $existing = (object) ['id' => 'existing-message-id'];

        $leadRepository->shouldReceive('findByIdForTenant')->once()->andReturn((object) ['id' => 'lead-1']);
        $inboxService->shouldReceive('assertCanSend')->once();
        $messageService->shouldReceive('findByExternalId')->once()->andReturn($existing);
        $messageService->shouldNotReceive('createOutboundMessage');
        $activityService->shouldNotReceive('logOutboundMessage');

        $service = new SendMessageService($messageService, $leadRepository, $activityService, $inboxService);

        $dto = new SendMessageDTO(
            tenantId: 'tenant-1',
            leadId: 'lead-1',
            channel: 'whatsapp',
            content: 'hello',
            createdBy: 'user-1',
            externalId: 'external-123',
        );

        $result = $service->send($dto);

        $this->assertSame('existing-message-id', $result->id);
    }

    public function test_locked_conversation_blocks_another_agent_from_sending(): void
    {
        $leadRepository = Mockery::mock(LeadRepository::class);
        $messageService = Mockery::mock(MessageService::class);

        $leadRepository
            ->shouldReceive('canUserSendInConversation')
            ->once()
            ->with('tenant-1', 'lead-1', 'user-2')
            ->andReturn(false);

        $service = new InboxService($leadRepository, $messageService);

        $this->expectException(\RuntimeException::class);
        $service->assertCanSend('tenant-1', 'lead-1', 'user-2');
    }

    public function test_message_status_cannot_downgrade(): void
    {
        $repository = new MessageRepository();
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('isAllowedStatusTransition');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($repository, 'read', 'delivered'));
        $this->assertFalse($method->invoke($repository, 'delivered', 'sent'));
        $this->assertTrue($method->invoke($repository, 'sent', 'delivered'));
        $this->assertTrue($method->invoke($repository, 'delivered', 'read'));
    }

    public function test_stage_change_activity_uses_compatible_enum_value(): void
    {
        $repository = Mockery::mock(\App\Repositories\LeadActivityRepository::class);

        $repository
            ->shouldReceive('create')
            ->once()
            ->with(
                'tenant-1',
                'lead-1',
                'status_change',
                'Moved from Contacted to New',
                'user-1',
            )
            ->andReturn((object) ['id' => 'activity-1']);

        $service = new LeadActivityService($repository);

        $result = $service->logStageChange(
            'tenant-1',
            'lead-1',
            'New',
            'Contacted',
            'user-1',
        );

        $this->assertSame('activity-1', $result->id);
    }
}
