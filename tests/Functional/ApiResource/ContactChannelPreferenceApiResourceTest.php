<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\ApiResource;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannelPreference;

class ContactChannelPreferenceApiResourceTest extends BaseApiTestCase
{
    public function testGetContactChannelPreferenceCollection(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        
        $preference1 = $this->createContactChannelPreference($channel, [
            'deliveryWindowStart' => '09:00',
            'deliveryWindowEnd' => '17:00'
        ]);
        
        $preference2 = $this->createContactChannelPreference($channel, [
            'deliveryWindowStart' => '10:00',
            'deliveryWindowEnd' => '18:00'
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(2, $data['hydra:member']);
        
        $preferenceData = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $preferenceData);
        $this->assertArrayHasKey('isEnabled', $preferenceData);
        $this->assertArrayHasKey('deliveryWindowStart', $preferenceData);
        $this->assertArrayHasKey('deliveryWindowEnd', $preferenceData);
        $this->assertArrayHasKey('frequency', $preferenceData);
    }

    public function testGetContactChannelPreference(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        
        $preference = $this->createContactChannelPreference($channel, [
            'isEnabled' => true,
            'frequency' => ContactChannelPreference::FREQUENCY_DAILY,
            'deliveryWindowStart' => '09:00',
            'deliveryWindowEnd' => '17:00',
            'maxDailyMessages' => 5,
            'maxWeeklyMessages' => 20,
            'maxMonthlyMessages' => 50,
            'quietHoursStart' => '22:00',
            'quietHoursEnd' => '08:00',
            'timezone' => 'America/New_York',
            'allowedDays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'customRules' => [
                'no_marketing_on_weekends' => true,
                'urgent_only_after_hours' => true
            ]
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences/' . $preference->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($preference->getId(), $data['id']);
        $this->assertTrue($data['isEnabled']);
        $this->assertEquals(ContactChannelPreference::FREQUENCY_DAILY, $data['frequency']);
        $this->assertEquals('09:00', $data['deliveryWindowStart']);
        $this->assertEquals('17:00', $data['deliveryWindowEnd']);
        $this->assertEquals(5, $data['maxDailyMessages']);
        $this->assertEquals(20, $data['maxWeeklyMessages']);
        $this->assertEquals(50, $data['maxMonthlyMessages']);
        $this->assertEquals('22:00', $data['quietHoursStart']);
        $this->assertEquals('08:00', $data['quietHoursEnd']);
        $this->assertEquals('America/New_York', $data['timezone']);
        $this->assertEquals(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $data['allowedDays']);
        $this->assertEquals([
            'no_marketing_on_weekends' => true,
            'urgent_only_after_hours' => true
        ], $data['customRules']);
    }

    public function testCreateContactChannelPreference(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        
        $preferenceData = [
            'channel' => '/api/contact_channels/' . $channel->getId(),
            'isEnabled' => true,
            'frequency' => ContactChannelPreference::FREQUENCY_WEEKLY,
            'deliveryWindowStart' => '10:00',
            'deliveryWindowEnd' => '16:00',
            'maxDailyMessages' => 3,
            'maxWeeklyMessages' => 15,
            'maxMonthlyMessages' => 40,
            'quietHoursStart' => '20:00',
            'quietHoursEnd' => '09:00',
            'timezone' => 'UTC',
            'allowedDays' => ['monday', 'wednesday', 'friday'],
            'customRules' => [
                'priority_messages_only' => true,
                'no_promotional_content' => true
            ]
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channel_preferences', $preferenceData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertTrue($data['isEnabled']);
        $this->assertEquals(ContactChannelPreference::FREQUENCY_WEEKLY, $data['frequency']);
        $this->assertEquals('10:00', $data['deliveryWindowStart']);
        $this->assertEquals('16:00', $data['deliveryWindowEnd']);
        $this->assertEquals(3, $data['maxDailyMessages']);
        $this->assertEquals(['monday', 'wednesday', 'friday'], $data['allowedDays']);

        // Verify in database
        $preference = $this->getContactChannelPreferenceRepository()->find($data['id']);
        $this->assertNotNull($preference);
        $this->assertEquals(ContactChannelPreference::FREQUENCY_WEEKLY, $preference->getFrequency());
        $this->assertEquals($channel->getId(), $preference->getChannel()->getId());
    }

    public function testUpdateContactChannelPreference(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        $preference = $this->createContactChannelPreference($channel, [
            'maxDailyMessages' => 5,
            'allowedDays' => ['monday', 'tuesday']
        ]);

        $updateData = [
            'maxDailyMessages' => 10,
            'allowedDays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'customRules' => [
                'business_hours_only' => true,
                'high_priority_allowed' => true
            ]
        ];

        $response = $this->makeJsonRequest('PUT', '/api/contact_channel_preferences/' . $preference->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(10, $data['maxDailyMessages']);
        $this->assertEquals(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $data['allowedDays']);
        $this->assertEquals([
            'business_hours_only' => true,
            'high_priority_allowed' => true
        ], $data['customRules']);

        // Verify in database
        $this->entityManager->refresh($preference);
        $this->assertEquals(10, $preference->getMaxDailyMessages());
    }

    public function testDisableContactChannelPreference(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        $preference = $this->createContactChannelPreference($channel, [
            'isEnabled' => true
        ]);

        $patchData = [
            'isEnabled' => false
        ];

        $response = $this->makeJsonRequest('PATCH', '/api/contact_channel_preferences/' . $preference->getId(), $patchData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertFalse($data['isEnabled']);

        // Verify in database
        $this->entityManager->refresh($preference);
        $this->assertFalse($preference->getIsEnabled());
    }

    public function testDeleteContactChannelPreference(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);
        $preference = $this->createContactChannelPreference($channel);

        $preferenceId = $preference->getId();

        $response = $this->makeJsonRequest('DELETE', '/api/contact_channel_preferences/' . $preferenceId);
        $this->assertEquals(204, $response->getStatusCode());

        // Verify preference is deleted
        $deletedPreference = $this->getContactChannelPreferenceRepository()->find($preferenceId);
        $this->assertNull($deletedPreference);
    }

    public function testContactChannelPreferenceValidation(): void
    {
        // Test invalid frequency
        $invalidData = [
            'frequency' => 'invalid_frequency',
            'deliveryWindowStart' => '25:00' // Invalid time
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_channel_preferences', $invalidData);
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testContactChannelPreferenceFiltering(): void
    {
        $contact1 = $this->createContact(['firstName' => 'User1']);
        $contact2 = $this->createContact(['firstName' => 'User2']);

        $channel1 = $this->createContactChannel($contact1);
        $channel2 = $this->createContactChannel($contact2);

        $this->createContactChannelPreference($channel1, [
            'isEnabled' => true,
            'frequency' => ContactChannelPreference::FREQUENCY_DAILY
        ]);

        $this->createContactChannelPreference($channel1, [
            'isEnabled' => false,
            'frequency' => ContactChannelPreference::FREQUENCY_WEEKLY
        ]);

        $this->createContactChannelPreference($channel2, [
            'isEnabled' => true,
            'frequency' => ContactChannelPreference::FREQUENCY_MONTHLY
        ]);

        // Test filter by enabled status
        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences?isEnabled=true');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);

        // Test filter by frequency
        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences?frequency=' . ContactChannelPreference::FREQUENCY_DAILY);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test filter by channel
        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences?channel=' . $channel1->getId());
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(2, $data['hydra:member']);
    }

    public function testFrequencyTypes(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);

        $frequencies = [
            ContactChannelPreference::FREQUENCY_IMMEDIATE,
            ContactChannelPreference::FREQUENCY_HOURLY,
            ContactChannelPreference::FREQUENCY_DAILY,
            ContactChannelPreference::FREQUENCY_WEEKLY,
            ContactChannelPreference::FREQUENCY_MONTHLY,
            ContactChannelPreference::FREQUENCY_NEVER
        ];

        foreach ($frequencies as $frequency) {
            $preference = $this->createContactChannelPreference($channel, [
                'frequency' => $frequency
            ]);

            $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences/' . $preference->getId());
            $data = $this->assertJsonResponse($response, 200);

            $this->assertEquals($frequency, $data['frequency']);
        }
    }

    public function testTimeZoneHandling(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);

        $timezones = [
            'UTC',
            'America/New_York',
            'Europe/London',
            'Asia/Tokyo',
            'Australia/Sydney'
        ];

        foreach ($timezones as $timezone) {
            $preference = $this->createContactChannelPreference($channel, [
                'timezone' => $timezone,
                'deliveryWindowStart' => '09:00',
                'deliveryWindowEnd' => '17:00'
            ]);

            $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences/' . $preference->getId());
            $data = $this->assertJsonResponse($response, 200);

            $this->assertEquals($timezone, $data['timezone']);
        }
    }

    public function testCustomRulesHandling(): void
    {
        $contact = $this->createContact();
        $channel = $this->createContactChannel($contact);

        $customRules = [
            'no_weekend_marketing' => true,
            'priority_messages_only' => false,
            'max_urgency_level' => 3,
            'allowed_message_types' => ['transactional', 'urgent'],
            'blackout_dates' => ['2023-12-25', '2023-01-01']
        ];

        $preference = $this->createContactChannelPreference($channel, [
            'customRules' => $customRules
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_channel_preferences/' . $preference->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($customRules, $data['customRules']);
    }

    protected function createContactChannelPreference($channel, array $data = [])
    {
        $preference = new ContactChannelPreference();
        $preference->setChannel($channel);
        $preference->setIsEnabled($data['isEnabled'] ?? true);
        $preference->setFrequency($data['frequency'] ?? ContactChannelPreference::FREQUENCY_DAILY);
        
        if (isset($data['deliveryWindowStart'])) {
            $preference->setDeliveryWindowStart(new \DateTime($data['deliveryWindowStart']));
        }
        if (isset($data['deliveryWindowEnd'])) {
            $preference->setDeliveryWindowEnd(new \DateTime($data['deliveryWindowEnd']));
        }
        if (isset($data['maxDailyMessages'])) {
            $preference->setMaxDailyMessages($data['maxDailyMessages']);
        }
        if (isset($data['maxWeeklyMessages'])) {
            $preference->setMaxWeeklyMessages($data['maxWeeklyMessages']);
        }
        if (isset($data['maxMonthlyMessages'])) {
            $preference->setMaxMonthlyMessages($data['maxMonthlyMessages']);
        }
        if (isset($data['quietHoursStart'])) {
            $preference->setQuietHoursStart(new \DateTime($data['quietHoursStart']));
        }
        if (isset($data['quietHoursEnd'])) {
            $preference->setQuietHoursEnd(new \DateTime($data['quietHoursEnd']));
        }
        if (isset($data['timezone'])) {
            $preference->setTimezone($data['timezone']);
        }
        if (isset($data['allowedDays'])) {
            $preference->setAllowedDays($data['allowedDays']);
        }
        if (isset($data['customRules'])) {
            $preference->setCustomRules($data['customRules']);
        }

        $this->entityManager->persist($preference);
        $this->entityManager->flush();

        return $preference;
    }

    protected function getContactChannelPreferenceRepository()
    {
        return $this->entityManager->getRepository(ContactChannelPreference::class);
    }
}
