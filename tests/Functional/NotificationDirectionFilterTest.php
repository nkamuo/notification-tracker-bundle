<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\MessageStatus;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationDirectionFilterTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        
        // Clean up any existing test data
        $this->cleanupTestData();
    }

    public function testMessageFilterByNotificationDirection(): void
    {
        // Create test notifications with different directions
        $outboundNotification = new Notification();
        $outboundNotification->setType('test_outbound');
        $outboundNotification->setDirection(NotificationDirection::OUTBOUND);
        $outboundNotification->setStatus(NotificationStatus::SENT);
        
        $inboundNotification = new Notification();
        $inboundNotification->setType('test_inbound');
        $inboundNotification->setDirection(NotificationDirection::INBOUND);
        $inboundNotification->setStatus(NotificationStatus::SENT);
        
        $draftNotification = new Notification();
        $draftNotification->setType('test_draft');
        $draftNotification->setDirection(NotificationDirection::DRAFT);
        $draftNotification->setStatus(NotificationStatus::DRAFT);
        
        $this->entityManager->persist($outboundNotification);
        $this->entityManager->persist($inboundNotification);
        $this->entityManager->persist($draftNotification);
        
        // Create test messages for each notification
        $outboundMessage = new EmailMessage();
        $outboundMessage->setNotification($outboundNotification);
        $outboundMessage->setStatus(MessageStatus::SENT);
        $outboundMessage->setDirection(NotificationDirection::OUTBOUND);
        $outboundMessage->setSubject('Test Outbound Message');
        
        $inboundMessage = new EmailMessage();
        $inboundMessage->setNotification($inboundNotification);
        $inboundMessage->setStatus(MessageStatus::SENT);
        $inboundMessage->setDirection(NotificationDirection::INBOUND);
        $inboundMessage->setSubject('Test Inbound Message');
        
        $draftMessage = new EmailMessage();
        $draftMessage->setNotification($draftNotification);
        $draftMessage->setStatus(MessageStatus::PENDING);
        $draftMessage->setDirection(NotificationDirection::DRAFT);
        $draftMessage->setSubject('Test Draft Message');
        
        $this->entityManager->persist($outboundMessage);
        $this->entityManager->persist($inboundMessage);
        $this->entityManager->persist($draftMessage);
        $this->entityManager->flush();
        
        $client = self::createClient();
        
        // Test filtering messages by outbound direction
        $client->request('GET', '/api/notification-tracker/messages?notification.direction=outbound');
        $this->assertResponseIsSuccessful();
        $outboundResponse = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertGreaterThanOrEqual(1, count($outboundResponse));
        foreach ($outboundResponse as $message) {
            $this->assertEquals('outbound', $message['direction']);
        }
        
        // Test filtering messages by inbound direction
        $client->request('GET', '/api/notification-tracker/messages?notification.direction=inbound');
        $this->assertResponseIsSuccessful();
        $inboundResponse = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertGreaterThanOrEqual(1, count($inboundResponse));
        foreach ($inboundResponse as $message) {
            $this->assertEquals('inbound', $message['direction']);
        }
        
        // Test filtering messages by draft direction
        $client->request('GET', '/api/notification-tracker/messages?notification.direction=draft');
        $this->assertResponseIsSuccessful();
        $draftResponse = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertGreaterThanOrEqual(1, count($draftResponse));
        foreach ($draftResponse as $message) {
            $this->assertEquals('draft', $message['direction']);
        }
    }

    public function testNotificationDirectionFilter(): void
    {
        // Create test notifications
        $outboundNotification = new Notification();
        $outboundNotification->setType('test_outbound_notification');
        $outboundNotification->setDirection(NotificationDirection::OUTBOUND);
        $outboundNotification->setStatus(NotificationStatus::SENT);
        
        $inboundNotification = new Notification();
        $inboundNotification->setType('test_inbound_notification');
        $inboundNotification->setDirection(NotificationDirection::INBOUND);
        $inboundNotification->setStatus(NotificationStatus::SENT);
        
        $this->entityManager->persist($outboundNotification);
        $this->entityManager->persist($inboundNotification);
        $this->entityManager->flush();
        
        $client = self::createClient();
        
        // Test filtering notifications by outbound direction
        $client->request('GET', '/api/notification-tracker/notifications?direction=outbound');
        $this->assertResponseIsSuccessful();
        $outboundResponse = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertGreaterThanOrEqual(1, count($outboundResponse));
        foreach ($outboundResponse as $notification) {
            $this->assertEquals('outbound', $notification['direction']);
        }
        
        // Test filtering notifications by inbound direction
        $client->request('GET', '/api/notification-tracker/notifications?direction=inbound');
        $this->assertResponseIsSuccessful();
        $inboundResponse = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertGreaterThanOrEqual(1, count($inboundResponse));
        foreach ($inboundResponse as $notification) {
            $this->assertEquals('inbound', $notification['direction']);
        }
    }

    public function testInvalidDirectionValueReturnsEmptyResult(): void
    {
        $client = self::createClient();
        
        // Test with invalid direction value - should return empty result or error
        $client->request('GET', '/api/notification-tracker/messages?notification.direction=invalid');
        
        // Should either return empty result or error response
        $this->assertTrue(
            $client->getResponse()->isSuccessful() || 
            $client->getResponse()->isClientError()
        );
        
        if ($client->getResponse()->isSuccessful()) {
            $response = json_decode($client->getResponse()->getContent(), true);
            // Should return empty array for invalid enum values
            $this->assertEquals([], $response);
        }
    }

    public function testValidDirectionEnumValues(): void
    {
        // Ensure all valid direction values from the enum are supported
        $validDirections = ['inbound', 'outbound', 'draft'];
        
        $client = self::createClient();
        
        foreach ($validDirections as $direction) {
            $client->request('GET', "/api/notification-tracker/messages?notification.direction={$direction}");
            $this->assertResponseIsSuccessful();
            
            $client->request('GET', "/api/notification-tracker/notifications?direction={$direction}");
            $this->assertResponseIsSuccessful();
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM ' . EmailMessage::class . ' m WHERE m.subject LIKE :subject')
            ->setParameter('subject', '%Test%Message%')
            ->execute();
            
        $this->entityManager->createQuery('DELETE FROM ' . Notification::class . ' n WHERE n.type LIKE :type')
            ->setParameter('type', '%test_%')
            ->execute();
    }
}
