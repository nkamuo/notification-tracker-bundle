<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;
use Nkamuo\NotificationTrackerBundle\Entity\ContactGroup;
use Nkamuo\NotificationTrackerBundle\Entity\ContactActivity;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannelPreference;

abstract class BaseApiTestCase extends WebTestCase
{
    protected $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->getContainer()->get('doctrine')->getManager();
        
        // Clean database before each test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    protected function cleanDatabase(): void
    {
        // Clean in proper order to respect foreign key constraints
        $this->entityManager->createQuery('DELETE FROM ' . ContactActivity::class)->execute();
        $this->entityManager->createQuery('DELETE FROM ' . ContactChannelPreference::class)->execute();
        $this->entityManager->createQuery('DELETE FROM ' . ContactChannel::class)->execute();
        $this->entityManager->createQuery('DELETE FROM ' . ContactGroup::class)->execute();
        $this->entityManager->createQuery('DELETE FROM ' . Contact::class)->execute();
        $this->entityManager->flush();
    }

    protected function createContact(array $data = []): Contact
    {
        $contact = new Contact();
        $contact->setType($data['type'] ?? Contact::TYPE_INDIVIDUAL);
        $contact->setStatus($data['status'] ?? Contact::STATUS_ACTIVE);
        $contact->setFirstName($data['firstName'] ?? 'John');
        $contact->setLastName($data['lastName'] ?? 'Doe');
        $contact->setDisplayName($data['displayName'] ?? 'John Doe');
        
        if (isset($data['organizationName'])) {
            $contact->setOrganizationName($data['organizationName']);
        }
        if (isset($data['jobTitle'])) {
            $contact->setJobTitle($data['jobTitle']);
        }
        if (isset($data['department'])) {
            $contact->setDepartment($data['department']);
        }
        if (isset($data['language'])) {
            $contact->setLanguage($data['language']);
        }
        if (isset($data['timezone'])) {
            $contact->setTimezone($data['timezone']);
        }
        if (isset($data['tags'])) {
            $contact->setTags($data['tags']);
        }
        if (isset($data['customFields'])) {
            $contact->setCustomFields($data['customFields']);
        }
        if (isset($data['notes'])) {
            $contact->setNotes($data['notes']);
        }

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    protected function createContactChannel(Contact $contact, array $data = []): ContactChannel
    {
        $channel = new ContactChannel();
        $channel->setContact($contact);
        $channel->setType($data['type'] ?? ContactChannel::TYPE_EMAIL);
        $channel->setIdentifier($data['identifier'] ?? 'test@example.com');
        $channel->setLabel($data['label'] ?? 'Test Email');
        $channel->setIsPrimary($data['isPrimary'] ?? true);
        $channel->setIsActive($data['isActive'] ?? true);
        $channel->setIsVerified($data['isVerified'] ?? false);
        $channel->setPriority($data['priority'] ?? 0);
        
        if (isset($data['metadata'])) {
            $channel->setMetadataArray($data['metadata']);
        }
        if (isset($data['capabilities'])) {
            $channel->setCapabilities($data['capabilities']);
        }

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }

    protected function createContactGroup(array $data = []): ContactGroup
    {
        $group = new ContactGroup();
        $group->setName($data['name'] ?? 'Test Group');
        $group->setDescription($data['description'] ?? 'Test group description');
        $group->setType($data['type'] ?? ContactGroup::TYPE_STATIC);
        
        if (isset($data['parent'])) {
            $group->setParent($data['parent']);
        }
        if (isset($data['criteria'])) {
            $group->setCriteria($data['criteria']);
        }
        if (isset($data['tags'])) {
            $group->setTags($data['tags']);
        }

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $group;
    }

    protected function createContactActivity(Contact $contact, array $data = []): ContactActivity
    {
        $activity = new ContactActivity();
        $activity->setContact($contact);
        $activity->setActivityType($data['activityType'] ?? ContactActivity::TYPE_CONTACT_CREATED);
        $activity->setTitle($data['title'] ?? 'Test Activity');
        $activity->setDescription($data['description'] ?? 'Test activity description');
        $activity->setSource($data['source'] ?? 'test');
        
        if (isset($data['relatedChannelId'])) {
            $activity->setRelatedChannelId($data['relatedChannelId']);
        }
        if (isset($data['relatedGroupId'])) {
            $activity->setRelatedGroupId($data['relatedGroupId']);
        }
        if (isset($data['metadata'])) {
            $activity->setMetadataArray($data['metadata']);
        }
        if (isset($data['occurredAt'])) {
            $activity->setOccurredAt($data['occurredAt']);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    protected function assertJsonResponse(Response $response, int $expectedStatusCode = 200): array
    {
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        $this->assertJson($response->getContent());
        
        return json_decode($response->getContent(), true);
    }

    protected function makeJsonRequest(string $method, string $uri, array $data = []): Response
    {
        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));
        
        return $this->client->getResponse();
    }

    protected function getContactRepository()
    {
        return $this->entityManager->getRepository(Contact::class);
    }

    protected function getContactChannelRepository()
    {
        return $this->entityManager->getRepository(ContactChannel::class);
    }

    protected function getContactGroupRepository()
    {
        return $this->entityManager->getRepository(ContactGroup::class);
    }

    protected function getContactActivityRepository()
    {
        return $this->entityManager->getRepository(ContactActivity::class);
    }
}
