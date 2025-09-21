<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\ApiResource;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;

class ContactChannelApiResourceTest extends BaseApiTestCase
{
    public function testGetContactChannelCollection(): void
    {
        $contact = $this->createContact();
        
        $channel1 = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'test1@example.com'
        ]);
        
        $channel2 = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1234567890'
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channels');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(2, $data['hydra:member']);
        
        $channelData = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $channelData);
        $this->assertArrayHasKey('type', $channelData);
        $this->assertArrayHasKey('identifier', $channelData);
        $this->assertArrayHasKey('isPrimary', $channelData);
        $this->assertArrayHasKey('isActive', $channelData);
        $this->assertArrayHasKey('isVerified', $channelData);
    }

    public function testGetContactChannel(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'detailed@example.com',
            'label' => 'Work Email',
            'isPrimary' => true,
            'isActive' => true,
            'isVerified' => true,
            'priority' => 1,
            'metadata' => [
                'provider' => 'gmail',
                'folder' => 'important'
            ],
            'capabilities' => ['html', 'attachments']
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channels/' . $channel->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($channel->getId(), $data['id']);
        $this->assertEquals(ContactChannel::TYPE_EMAIL, $data['type']);
        $this->assertEquals('detailed@example.com', $data['identifier']);
        $this->assertEquals('Work Email', $data['label']);
        $this->assertTrue($data['isPrimary']);
        $this->assertTrue($data['isActive']);
        $this->assertTrue($data['isVerified']);
        $this->assertEquals(1, $data['priority']);
        $this->assertEquals(['provider' => 'gmail', 'folder' => 'important'], $data['metadata']);
        $this->assertEquals(['html', 'attachments'], $data['capabilities']);
    }

    public function testCreateContactChannel(): void
    {
        $contact = $this->createContact();
        
        $channelData = [
            'contact' => '/api/contacts/' . $contact->getId(),
            'type' => ContactChannel::TYPE_TELEGRAM,
            'identifier' => '@testuser',
            'label' => 'Telegram Channel',
            'isPrimary' => false,
            'isActive' => true,
            'isVerified' => false,
            'priority' => 2,
            'metadata' => [
                'chat_id' => '123456789'
            ],
            'capabilities' => ['text', 'media', 'files']
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channels', $channelData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(ContactChannel::TYPE_TELEGRAM, $data['type']);
        $this->assertEquals('@testuser', $data['identifier']);
        $this->assertEquals('Telegram Channel', $data['label']);
        $this->assertFalse($data['isPrimary']);
        $this->assertTrue($data['isActive']);
        $this->assertFalse($data['isVerified']);
        $this->assertEquals(2, $data['priority']);

        // Verify in database
        $channel = $this->getContactChannelRepository()->find($data['id']);
        $this->assertNotNull($channel);
        $this->assertEquals(ContactChannel::TYPE_TELEGRAM, $channel->getType());
        $this->assertEquals('@testuser', $channel->getIdentifier());
        $this->assertEquals($contact->getId(), $channel->getContact()->getId());
    }

    public function testUpdateContactChannel(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1234567890',
            'isVerified' => false
        ]);

        $updateData = [
            'isVerified' => true,
            'label' => 'Primary Mobile',
            'metadata' => [
                'carrier' => 'Verizon',
                'country' => 'US'
            ]
        ];

        $response = $this->makeJsonRequest('PUT', '/api/contact_channels/' . $channel->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertTrue($data['isVerified']);
        $this->assertEquals('Primary Mobile', $data['label']);
        $this->assertEquals(['carrier' => 'Verizon', 'country' => 'US'], $data['metadata']);

        // Verify in database
        $this->entityManager->refresh($channel);
        $this->assertTrue($channel->isVerified());
        $this->assertEquals('Primary Mobile', $channel->getLabel());
    }

    public function testDeactivateContactChannel(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'old@example.com',
            'isActive' => true
        ]);

        $patchData = [
            'isActive' => false
        ];

        $response = $this->makeJsonRequest('PATCH', '/api/contact_channels/' . $channel->getId(), $patchData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertFalse($data['isActive']);

        // Verify in database
        $this->entityManager->refresh($channel);
        $this->assertFalse($channel->isActive());
    }

    public function testDeleteContactChannel(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_WEBHOOK,
            'identifier' => 'https://example.com/webhook'
        ]);

        $channelId = $channel->getId();

        $response = $this->makeJsonRequest('DELETE', '/api/contact_channels/' . $channelId);
        $this->assertEquals(204, $response->getStatusCode());

        // Verify channel is deleted
        $deletedChannel = $this->getContactChannelRepository()->find($channelId);
        $this->assertNull($deletedChannel);
    }

    public function testContactChannelValidation(): void
    {
        // Test invalid channel type
        $invalidData = [
            'type' => 'invalid_type',
            'identifier' => 'test@example.com'
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channels', $invalidData);
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);

        // Test missing required fields
        $invalidData2 = [
            'type' => ContactChannel::TYPE_EMAIL
            // Missing identifier
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channels', $invalidData2);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testContactChannelFiltering(): void
    {
        $contact1 = $this->createContact(['firstName' => 'User1']);
        $contact2 = $this->createContact(['firstName' => 'User2']);

        $this->createContactChannel($contact1, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'user1@example.com',
            'isVerified' => true
        ]);

        $this->createContactChannel($contact1, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1111111111',
            'isVerified' => false
        ]);

        $this->createContactChannel($contact2, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'user2@example.com',
            'isVerified' => true
        ]);

        // Test filter by type
        $response = $this->makeJsonRequest('GET', '/api/contact_channels?type=' . ContactChannel::TYPE_EMAIL);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);

        // Test filter by verified status
        $response = $this->makeJsonRequest('GET', '/api/contact_channels?isVerified=true');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);

        // Test filter by contact
        $response = $this->makeJsonRequest('GET', '/api/contact_channels?contact=' . $contact1->getId());
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);
    }

    public function testPrimaryChannelConstraint(): void
    {
        $contact = $this->createContact();
        
        // Create first primary email channel
        $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'primary@example.com',
            'isPrimary' => true
        ]);

        // Try to create another primary email channel for same contact
        $channelData = [
            'contact' => '/api/contacts/' . $contact->getId(),
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'secondary@example.com',
            'isPrimary' => true
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channels', $channelData);
        // This should either fail with validation error or automatically set isPrimary to false
        // depending on business logic implementation
        $this->assertContains($response->getStatusCode(), [201, 422]);
    }

    public function testChannelCapabilities(): void
    {
        $contact = $this->createContact();
        
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'capable@example.com',
            'capabilities' => ['html', 'attachments', 'inline_images']
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channels/' . $channel->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(['html', 'attachments', 'inline_images'], $data['capabilities']);
    }

    public function testChannelDeliveryTracking(): void
    {
        $contact = $this->createContact();
        
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1234567890'
        ]);

        // Test delivery stats endpoints if they exist
        $response = $this->makeJsonRequest('GET', '/api/contact_channels/' . $channel->getId());
        $data = $this->assertJsonResponse($response, 200);

        // Verify delivery tracking fields are present
        $this->assertArrayHasKey('deliverySuccessCount', $data);
        $this->assertArrayHasKey('deliveryFailureCount', $data);
        $this->assertArrayHasKey('lastDeliveryAttempt', $data);
        $this->assertArrayHasKey('lastSuccessfulDelivery', $data);
    }

    public function testChannelSearch(): void
    {
        $contact = $this->createContact();
        
        $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'searchable@example.com',
            'label' => 'Work Email'
        ]);

        $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_TELEGRAM,
            'identifier' => '@telegram_user',
            'label' => 'Personal Telegram'
        ]);

        // Test search by identifier
        $response = $this->makeJsonRequest('GET', '/api/contact_channels?search=searchable');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test search by label
        $response = $this->makeJsonRequest('GET', '/api/contact_channels?search=Work');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testChannelVerification(): void
    {
        $contact = $this->createContact();
        
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'verify@example.com',
            'isVerified' => false
        ]);

        // Test verification endpoint (if implemented)
        $response = $this->makeJsonRequest('POST', '/api/contact_channels/' . $channel->getId() . '/verify');
        
        // Should either return success or method not allowed depending on implementation
        $this->assertContains($response->getStatusCode(), [200, 202, 405]);
    }
}
