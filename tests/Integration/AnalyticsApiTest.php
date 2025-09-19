<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsApiTest extends ApiTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDashboardAnalytics(): void
    {
        // Create test data
        $this->createTestData();

        $response = static::createClient()->request('GET', '/api/analytics/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'DashboardDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('totalMessages', $data);
        $this->assertArrayHasKey('deliveredMessages', $data);
        $this->assertArrayHasKey('failedMessages', $data);
        $this->assertArrayHasKey('deliveryRate', $data);
        $this->assertArrayHasKey('channels', $data);
        $this->assertArrayHasKey('recentTrends', $data);
    }

    public function testChannelAnalytics(): void
    {
        $this->createTestData();

        $response = static::createClient()->request('GET', '/api/analytics/channels');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'ChannelAnalyticsDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('channels', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('performance', $data);
    }

    public function testQueueStatusAnalytics(): void
    {
        $response = static::createClient()->request('GET', '/api/queue/status');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'QueueStatusDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('workers', $data);
        $this->assertArrayHasKey('queues', $data);
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('health', $data);
    }

    public function testRealtimeAnalytics(): void
    {
        $this->createTestData();

        $response = static::createClient()->request('GET', '/api/analytics/realtime');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'RealtimeAnalyticsDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('liveMetrics', $data);
        $this->assertArrayHasKey('recentActivity', $data);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertArrayHasKey('performance', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('refreshInterval', $data);
    }

    public function testEngagementAnalytics(): void
    {
        $this->createTestData();

        $response = static::createClient()->request('GET', '/api/analytics/engagement');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'EngagementAnalyticsDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('overview', $data);
        $this->assertArrayHasKey('channels', $data);
        $this->assertArrayHasKey('trends', $data);
    }

    public function testTrendsAnalytics(): void
    {
        $this->createTestData();

        $response = static::createClient()->request('GET', '/api/analytics/trends');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'TrendsAnalyticsDto',
        ]);

        $data = $response->toArray();
        
        // Verify structure
        $this->assertArrayHasKey('volume', $data);
        $this->assertArrayHasKey('delivery', $data);
        $this->assertArrayHasKey('failures', $data);
        $this->assertArrayHasKey('engagement', $data);
    }

    private function createTestData(): void
    {
        // Create a test notification
        $notification = new Notification();
        $notification->setType('test');
        $notification->setTitle('Test Notification');
        $notification->setBody('Test body');
        $notification->setCreatedAt(new \DateTime());

        $this->entityManager->persist($notification);

        // Create test messages for different channels
        $channels = ['email', 'sms', 'push', 'slack'];
        $statuses = ['sent', 'delivered', 'failed', 'pending'];

        for ($i = 0; $i < 20; $i++) {
            $message = new Message();
            $message->setType($channels[$i % count($channels)]);
            $message->setNotification($notification);
            $message->setStatus($statuses[$i % count($statuses)]);
            $message->setCreatedAt(new \DateTime("-{$i} hours"));
            
            if ($message->getStatus() === 'sent' || $message->getStatus() === 'delivered') {
                $message->setSentAt(new \DateTime("-{$i} hours +5 minutes"));
            }

            $this->entityManager->persist($message);
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM Nkamuo\NotificationTrackerBundle\Entity\Message')->execute();
        $this->entityManager->createQuery('DELETE FROM Nkamuo\NotificationTrackerBundle\Entity\Notification')->execute();
        
        parent::tearDown();
    }
}
