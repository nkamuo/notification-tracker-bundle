<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\ApiResource;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactActivity;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;

class ContactActivityApiResourceTest extends BaseApiTestCase
{
    public function testGetContactActivityCollection(): void
    {
        $contact = $this->createContact();
        
        $activity1 = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_CREATED,
            'title' => 'Contact Created'
        ]);
        
        $activity2 = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'Email Sent'
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_activities');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(2, $data['hydra:member']);
        
        $activityData = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $activityData);
        $this->assertArrayHasKey('activityType', $activityData);
        $this->assertArrayHasKey('title', $activityData);
        $this->assertArrayHasKey('description', $activityData);
        $this->assertArrayHasKey('source', $activityData);
        $this->assertArrayHasKey('occurredAt', $activityData);
    }

    public function testGetContactActivity(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'test@example.com'
        ]);

        $occurredAt = new \DateTime('2023-01-15 14:30:00');
        $activity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_DELIVERED,
            'title' => 'Email Delivered Successfully',
            'description' => 'Marketing email was delivered to user inbox',
            'source' => 'email_service',
            'relatedChannelId' => $channel->getId(),
            'metadata' => [
                'message_id' => 'msg_123',
                'campaign_id' => 'campaign_456',
                'delivery_time' => '2.3s'
            ],
            'occurredAt' => $occurredAt
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_activities/' . $activity->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($activity->getId(), $data['id']);
        $this->assertEquals(ContactActivity::TYPE_MESSAGE_DELIVERED, $data['activityType']);
        $this->assertEquals('Email Delivered Successfully', $data['title']);
        $this->assertEquals('Marketing email was delivered to user inbox', $data['description']);
        $this->assertEquals('email_service', $data['source']);
        $this->assertEquals($channel->getId(), $data['relatedChannelId']);
        $this->assertEquals([
            'message_id' => 'msg_123',
            'campaign_id' => 'campaign_456',
            'delivery_time' => '2.3s'
        ], $data['metadata']);
    }

    public function testCreateContactActivity(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);

        $activityData = [
            'contact' => '/api/contacts/' . $contact->getId(),
            'activityType' => ContactActivity::TYPE_CHANNEL_VERIFIED,
            'title' => 'Email Address Verified',
            'description' => 'User clicked verification link in email',
            'source' => 'verification_service',
            'relatedChannelId' => $channel->getId(),
            'metadata' => [
                'verification_token' => 'token_abc123',
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0'
            ],
            'occurredAt' => '2023-01-15T14:30:00Z'
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_activities', $activityData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(ContactActivity::TYPE_CHANNEL_VERIFIED, $data['activityType']);
        $this->assertEquals('Email Address Verified', $data['title']);
        $this->assertEquals('User clicked verification link in email', $data['description']);
        $this->assertEquals('verification_service', $data['source']);
        $this->assertEquals($channel->getId(), $data['relatedChannelId']);

        // Verify in database
        $activity = $this->getContactActivityRepository()->find($data['id']);
        $this->assertNotNull($activity);
        $this->assertEquals(ContactActivity::TYPE_CHANNEL_VERIFIED, $activity->getActivityType());
        $this->assertEquals($contact->getId(), $activity->getContact()->getId());
    }

    public function testUpdateContactActivity(): void
    {
        $contact = $this->createContact();
        $activity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_UPDATED,
            'title' => 'Profile Updated',
            'description' => 'User updated their profile information'
        ]);

        $updateData = [
            'description' => 'User updated their profile information and preferences',
            'metadata' => [
                'fields_changed' => ['email', 'phone', 'preferences'],
                'update_source' => 'web_interface'
            ]
        ];

        $response = $this->makeJsonRequest('PUT', '/api/contact_activities/' . $activity->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals('User updated their profile information and preferences', $data['description']);
        $this->assertEquals([
            'fields_changed' => ['email', 'phone', 'preferences'],
            'update_source' => 'web_interface'
        ], $data['metadata']);

        // Verify in database
        $this->entityManager->refresh($activity);
        $this->assertEquals('User updated their profile information and preferences', $activity->getDescription());
    }

    public function testDeleteContactActivity(): void
    {
        $contact = $this->createContact();
        $activity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CUSTOM,
            'title' => 'Temporary Activity'
        ]);

        $activityId = $activity->getId();

        $response = $this->makeJsonRequest('DELETE', '/api/contact_activities/' . $activityId);
        $this->assertEquals(204, $response->getStatusCode());

        // Verify activity is deleted
        $deletedActivity = $this->getContactActivityRepository()->find($activityId);
        $this->assertNull($deletedActivity);
    }

    public function testContactActivityValidation(): void
    {
        // Test missing required fields
        $invalidData = [
            'activityType' => 'invalid_type'
            // Missing contact, title
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_activities', $invalidData);
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testContactActivityFiltering(): void
    {
        $contact1 = $this->createContact(['firstName' => 'User1']);
        $contact2 = $this->createContact(['firstName' => 'User2']);

        $this->createContactActivity($contact1, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'Email Sent'
        ]);

        $this->createContactActivity($contact1, [
            'activityType' => ContactActivity::TYPE_MESSAGE_DELIVERED,
            'title' => 'Email Delivered'
        ]);

        $this->createContactActivity($contact2, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'SMS Sent'
        ]);

        // Test filter by activity type
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?activityType=' . ContactActivity::TYPE_MESSAGE_SENT);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);

        // Test filter by contact
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?contact=' . $contact1->getId());
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);

        // Test filter by source
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?source=test');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(3, $data['hydra:member']); // All activities use 'test' source from createContactActivity
    }

    public function testContactActivitySearch(): void
    {
        $contact = $this->createContact();

        $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'Welcome Email Sent',
            'description' => 'Sent welcome email to new user'
        ]);

        $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_ENGAGEMENT_SCORE_UPDATED,
            'title' => 'Page View Tracked',
            'description' => 'User viewed product page'
        ]);

        // Test search by title
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?search=Welcome');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test search by description
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?search=product');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testContactActivityDateFiltering(): void
    {
        $contact = $this->createContact();

        $oldActivity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_CREATED,
            'title' => 'Old Activity',
            'occurredAt' => new \DateTime('2023-01-01')
        ]);

        $recentActivity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_UPDATED,
            'title' => 'Recent Activity',
            'occurredAt' => new \DateTime('2023-06-01')
        ]);

        // Test date range filtering
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?occurredAt[after]=2023-05-01');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
        $this->assertEquals('Recent Activity', $data['hydra:member'][0]['title']);

        $response = $this->makeJsonRequest('GET', '/api/contact_activities?occurredAt[before]=2023-02-01');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
        $this->assertEquals('Old Activity', $data['hydra:member'][0]['title']);
    }

    public function testContactActivityOrdering(): void
    {
        $contact = $this->createContact();

        $activity1 = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_CREATED,
            'title' => 'First Activity',
            'occurredAt' => new \DateTime('2023-01-01')
        ]);

        $activity2 = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_CONTACT_UPDATED,
            'title' => 'Second Activity',
            'occurredAt' => new \DateTime('2023-01-02')
        ]);

        $activity3 = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'Third Activity',
            'occurredAt' => new \DateTime('2023-01-03')
        ]);

        // Test order by occurredAt desc (most recent first)
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?order[occurredAt]=desc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('Third Activity', $data['hydra:member'][0]['title']);
        $this->assertEquals('Second Activity', $data['hydra:member'][1]['title']);
        $this->assertEquals('First Activity', $data['hydra:member'][2]['title']);

        // Test order by title asc
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?order[title]=asc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('First Activity', $data['hydra:member'][0]['title']);
        $this->assertEquals('Second Activity', $data['hydra:member'][1]['title']);
        $this->assertEquals('Third Activity', $data['hydra:member'][2]['title']);
    }

    public function testContactActivityPagination(): void
    {
        $contact = $this->createContact();

        // Create 25 activities
        for ($i = 1; $i <= 25; $i++) {
            $this->createContactActivity($contact, [
                'activityType' => ContactActivity::TYPE_CUSTOM,
                'title' => "Activity {$i}",
                'occurredAt' => new \DateTime("2023-01-{$i}")
            ]);
        }

        // Test first page
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?page=1&itemsPerPage=10&order[occurredAt]=desc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertCount(10, $data['hydra:member']);
        $this->assertArrayHasKey('hydra:view', $data);
        $this->assertArrayHasKey('hydra:next', $data['hydra:view']);

        // Test second page
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?page=2&itemsPerPage=10&order[occurredAt]=desc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertCount(10, $data['hydra:member']);
        $this->assertArrayHasKey('hydra:previous', $data['hydra:view']);
    }

    public function testContactActivityByChannel(): void
    {
        $contact = $this->createContact();
        $emailChannel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'test@example.com'
        ]);
        $smsChannel = $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1234567890'
        ]);

        $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'Email Sent',
            'relatedChannelId' => $emailChannel->getId()
        ]);

        $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_SENT,
            'title' => 'SMS Sent',
            'relatedChannelId' => $smsChannel->getId()
        ]);

        // Test filter by channel
        $response = $this->makeJsonRequest('GET', '/api/contact_activities?relatedChannelId=' . $emailChannel->getId());
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
        $this->assertEquals('Email Sent', $data['hydra:member'][0]['title']);
    }

    public function testContactActivityTypes(): void
    {
        $contact = $this->createContact();

        // Test various activity types
        $activityTypes = [
            ContactActivity::TYPE_CONTACT_CREATED,
            ContactActivity::TYPE_CONTACT_UPDATED,
            ContactActivity::TYPE_CONTACT_MERGED,
            ContactActivity::TYPE_CHANNEL_ADDED,
            ContactActivity::TYPE_CHANNEL_VERIFIED,
            ContactActivity::TYPE_CHANNEL_REMOVED,
            ContactActivity::TYPE_GROUP_ADDED,
            ContactActivity::TYPE_GROUP_REMOVED,
            ContactActivity::TYPE_MESSAGE_SENT,
            ContactActivity::TYPE_MESSAGE_DELIVERED,
            ContactActivity::TYPE_MESSAGE_OPENED,
            ContactActivity::TYPE_MESSAGE_CLICKED,
            ContactActivity::TYPE_MESSAGE_BOUNCED,
            ContactActivity::TYPE_MESSAGE_COMPLAINED,
            ContactActivity::TYPE_ENGAGEMENT_SCORE_UPDATED,
            ContactActivity::TYPE_PREFERENCE_UPDATED,
            ContactActivity::TYPE_OPTED_IN,
            ContactActivity::TYPE_OPTED_OUT,
            ContactActivity::TYPE_CUSTOM
        ];

        foreach ($activityTypes as $type) {
            $this->createContactActivity($contact, [
                'activityType' => $type,
                'title' => "Test {$type}",
                'description' => "Testing activity type {$type}"
            ]);
        }

        $response = $this->makeJsonRequest('GET', '/api/contact_activities?contact=' . $contact->getId());
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(count($activityTypes), $data['hydra:member']);

        // Verify all activity types are represented
        $returnedTypes = array_column($data['hydra:member'], 'activityType');
        foreach ($activityTypes as $type) {
            $this->assertContains($type, $returnedTypes);
        }
    }

    public function testContactActivityMetadata(): void
    {
        $contact = $this->createContact();
        
        $activity = $this->createContactActivity($contact, [
            'activityType' => ContactActivity::TYPE_MESSAGE_CLICKED,
            'title' => 'Email Link Clicked',
            'metadata' => [
                'link_url' => 'https://example.com/product/123',
                'link_text' => 'View Product',
                'email_campaign' => 'summer_sale_2023',
                'click_position' => 'header',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'ip_address' => '192.168.1.100'
            ]
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_activities/' . $activity->getId());
        $data = $this->assertJsonResponse($response, 200);

        $expectedMetadata = [
            'link_url' => 'https://example.com/product/123',
            'link_text' => 'View Product',
            'email_campaign' => 'summer_sale_2023',
            'click_position' => 'header',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'ip_address' => '192.168.1.100'
        ];

        $this->assertEquals($expectedMetadata, $data['metadata']);
    }
}
