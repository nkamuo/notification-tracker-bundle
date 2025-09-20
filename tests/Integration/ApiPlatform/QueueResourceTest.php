<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Integration\ApiPlatform;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\QueuedMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class QueueResourceTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        // Clear existing data
        $this->entityManager->createQuery('DELETE FROM ' . QueuedMessage::class)->execute();
        $this->entityManager->flush();
    }

    public function testGetQueueMessages(): void
    {
        // Create test messages
        $this->createTestMessages();

        $client = static::createClient();
        $client->request('GET', '/api/queue/messages');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('hydra:member', $response);
        $this->assertCount(3, $response['hydra:member']);
        
        // Verify message structure
        $message = $response['hydra:member'][0];
        $this->assertArrayHasKey('id', $message);
        $this->assertArrayHasKey('transport', $message);
        $this->assertArrayHasKey('queueName', $message);
        $this->assertArrayHasKey('status', $message);
        $this->assertArrayHasKey('priority', $message);
        $this->assertArrayHasKey('createdAt', $message);
    }

    public function testGetQueueMessageById(): void
    {
        $queuedMessage = $this->createTestMessage('Test message', 'email', 'test-campaign');

        $client = static::createClient();
        $client->request('GET', '/api/queue/messages/' . $queuedMessage->getId()->toString());

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertEquals($queuedMessage->getId()->toString(), $response['id']);
        $this->assertEquals('test_transport', $response['transport']);
        $this->assertEquals('email', $response['notificationProvider']);
        $this->assertEquals('test-campaign', $response['campaignId']);
    }

    public function testGetQueueStats(): void
    {
        // Create messages with different statuses
        $this->createTestMessage('Pending 1', 'email', null, 'queued');
        $this->createTestMessage('Pending 2', 'sms', null, 'queued');
        $this->createTestMessage('Processing', 'email', null, 'processing');
        $this->createTestMessage('Processed', 'email', null, 'processed');
        $this->createTestMessage('Failed', 'sms', null, 'failed');

        $client = static::createClient();
        $client->request('GET', '/api/queue/stats');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('totalMessages', $response);
        $this->assertArrayHasKey('statusCounts', $response);
        $this->assertArrayHasKey('providerCounts', $response);
        $this->assertArrayHasKey('averageProcessingTime', $response);
        
        $this->assertEquals(5, $response['totalMessages']);
        $this->assertEquals(2, $response['statusCounts']['queued']);
        $this->assertEquals(1, $response['statusCounts']['processing']);
        $this->assertEquals(1, $response['statusCounts']['processed']);
        $this->assertEquals(1, $response['statusCounts']['failed']);
        
        $this->assertEquals(3, $response['providerCounts']['email']);
        $this->assertEquals(2, $response['providerCounts']['sms']);
    }

    public function testGetQueueHealth(): void
    {
        // Create some test messages
        $this->createTestMessage('Recent', 'email', null, 'queued');
        $this->createTestMessage('Old pending', 'email', null, 'queued');
        
        // Make one message old by setting created date
        $oldMessage = $this->createTestMessage('Very old', 'email', null, 'queued');
        $reflection = new \ReflectionClass($oldMessage);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($oldMessage, new \DateTimeImmutable('-2 hours'));
        $this->entityManager->persist($oldMessage);
        $this->entityManager->flush();

        $client = static::createClient();
        $client->request('GET', '/api/queue/health');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('metrics', $response);
        $this->assertArrayHasKey('alerts', $response);
        
        $this->assertContains($response['status'], ['healthy', 'warning', 'critical']);
        
        $metrics = $response['metrics'];
        $this->assertArrayHasKey('pendingMessages', $metrics);
        $this->assertArrayHasKey('processingMessages', $metrics);
        $this->assertArrayHasKey('failedMessages', $metrics);
        $this->assertArrayHasKey('oldestPendingAge', $metrics);
    }

    public function testQueueMessageFiltering(): void
    {
        // Create messages with different providers and statuses
        $this->createTestMessage('Email 1', 'email', 'campaign-1', 'queued');
        $this->createTestMessage('Email 2', 'email', 'campaign-2', 'processed');
        $this->createTestMessage('SMS 1', 'sms', 'campaign-1', 'queued');
        $this->createTestMessage('SMS 2', 'sms', 'campaign-2', 'failed');

        $client = static::createClient();

        // Test provider filtering
        $client->request('GET', '/api/queue/messages?notificationProvider=email');
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['hydra:member']);

        // Test status filtering
        $client->request('GET', '/api/queue/messages?status=queued');
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['hydra:member']);

        // Test campaign filtering
        $client->request('GET', '/api/queue/messages?campaignId=campaign-1');
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['hydra:member']);

        // Test combined filtering
        $client->request('GET', '/api/queue/messages?notificationProvider=email&status=queued');
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $response['hydra:member']);
    }

    public function testQueueMessagePagination(): void
    {
        // Create more messages than the default page size
        for ($i = 0; $i < 25; $i++) {
            $this->createTestMessage("Message $i", 'email', "campaign-$i");
        }

        $client = static::createClient();
        $client->request('GET', '/api/queue/messages');

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        // Check pagination metadata
        $this->assertArrayHasKey('hydra:view', $response);
        $this->assertArrayHasKey('hydra:first', $response['hydra:view']);
        $this->assertArrayHasKey('hydra:next', $response['hydra:view']);

        // Verify page size (assuming default is 30)
        $this->assertLessThanOrEqual(30, count($response['hydra:member']));
    }

    public function testUnauthorizedAccess(): void
    {
        // This test would be relevant if you implement authentication
        // For now, we'll test that the endpoints are accessible

        $client = static::createClient();
        
        $endpoints = [
            '/api/queue/messages',
            '/api/queue/stats',
            '/api/queue/health',
        ];

        foreach ($endpoints as $endpoint) {
            $client->request('GET', $endpoint);
            $this->assertNotEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), 
                "Endpoint $endpoint should be accessible");
        }
    }

    private function createTestMessages(): void
    {
        $this->createTestMessage('Test message 1', 'email', 'campaign-1', 'queued');
        $this->createTestMessage('Test message 2', 'sms', 'campaign-2', 'processing');
        $this->createTestMessage('Test message 3', 'email', 'campaign-1', 'processed');
    }

    private function createTestMessage(
        string $body,
        string $provider,
        ?string $campaignId = null,
        string $status = 'queued'
    ): QueuedMessage {
        $message = new QueuedMessage();
        $message->setTransport('test_transport');
        $message->setQueueName('test_queue');
        $message->setBody($body);
        $message->setHeaders(['Content-Type' => 'application/json']);
        $message->setNotificationProvider($provider);
        $message->setStatus($status);
        $message->setAvailableAt(new \DateTimeImmutable());

        if ($campaignId) {
            $message->setCampaignId($campaignId);
        }

        if ($status === 'processed') {
            $message->setProcessedAt(new \DateTimeImmutable());
        } elseif ($status === 'failed') {
            $message->setErrorMessage('Test error message');
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }
}
