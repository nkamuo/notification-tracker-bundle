<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\Repository;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactChannel;
use Nkamuo\NotificationTrackerBundle\Repository\ContactRepository;

class ContactRepositoryTest extends BaseApiTestCase
{
    private ContactRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContactRepository();
    }

    public function testFindByEmail(): void
    {
        $contact = $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);

        $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'john.doe@example.com',
            'isPrimary' => true
        ]);

        $foundContact = $this->repository->findByEmail('john.doe@example.com');
        $this->assertNotNull($foundContact);
        $this->assertEquals($contact->getId(), $foundContact->getId());

        // Test non-existent email
        $notFound = $this->repository->findByEmail('nonexistent@example.com');
        $this->assertNull($notFound);
    }

    public function testFindByPhone(): void
    {
        $contact = $this->createContact([
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]);

        $this->createContactChannel($contact, [
            'type' => ContactChannel::TYPE_SMS,
            'identifier' => '+1234567890',
            'isPrimary' => true
        ]);

        $foundContact = $this->repository->findByPhone('+1234567890');
        $this->assertNotNull($foundContact);
        $this->assertEquals($contact->getId(), $foundContact->getId());

        // Test non-existent phone
        $notFound = $this->repository->findByPhone('+9999999999');
        $this->assertNull($notFound);
    }

    public function testSearchContacts(): void
    {
        // Create test contacts
        $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Developer',
            'organizationName' => 'Tech Solutions'
        ]);

        $this->createContact([
            'firstName' => 'Jane',
            'lastName' => 'Manager',
            'organizationName' => 'Business Corp'
        ]);

        $this->createContact([
            'firstName' => 'Bob',
            'lastName' => 'Smith',
            'jobTitle' => 'Software Developer'
        ]);

        // Test search by first name
        $results = $this->repository->searchContacts('John');
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results[0]->getFirstName());

        // Test search by organization
        $results = $this->repository->searchContacts('Tech');
        $this->assertCount(1, $results);
        $this->assertEquals('Tech Solutions', $results[0]->getOrganizationName());

        // Test search by job title
        $results = $this->repository->searchContacts('Developer');
        $this->assertCount(2, $results); // Both John Developer and Bob (Software Developer)

        // Test no results
        $results = $this->repository->searchContacts('NonExistent');
        $this->assertCount(0, $results);
    }

    public function testFindByStatus(): void
    {
        $activeContact = $this->createContact([
            'firstName' => 'Active',
            'lastName' => 'User',
            'status' => Contact::STATUS_ACTIVE
        ]);

        $inactiveContact = $this->createContact([
            'firstName' => 'Inactive',
            'lastName' => 'User',
            'status' => Contact::STATUS_INACTIVE
        ]);

        $blockedContact = $this->createContact([
            'firstName' => 'Blocked',
            'lastName' => 'User',
            'status' => Contact::STATUS_BLOCKED
        ]);

        // Test find active contacts
        $activeContacts = $this->repository->findByStatus(Contact::STATUS_ACTIVE);
        $this->assertCount(1, $activeContacts);
        $this->assertEquals('Active', $activeContacts[0]->getFirstName());

        // Test find inactive contacts
        $inactiveContacts = $this->repository->findByStatus(Contact::STATUS_INACTIVE);
        $this->assertCount(1, $inactiveContacts);
        $this->assertEquals('Inactive', $inactiveContacts[0]->getFirstName());

        // Test find blocked contacts
        $blockedContacts = $this->repository->findByStatus(Contact::STATUS_BLOCKED);
        $this->assertCount(1, $blockedContacts);
        $this->assertEquals('Blocked', $blockedContacts[0]->getFirstName());
    }

    public function testFindByType(): void
    {
        $individualContact = $this->createContact([
            'firstName' => 'Individual',
            'lastName' => 'Person',
            'type' => Contact::TYPE_INDIVIDUAL
        ]);

        $organizationContact = $this->createContact([
            'firstName' => 'Organization',
            'lastName' => 'Corp',
            'type' => Contact::TYPE_ORGANIZATION
        ]);

        // Test find individuals
        $individuals = $this->repository->findByType(Contact::TYPE_INDIVIDUAL);
        $this->assertCount(1, $individuals);
        $this->assertEquals('Individual', $individuals[0]->getFirstName());

        // Test find organizations
        $organizations = $this->repository->findByType(Contact::TYPE_ORGANIZATION);
        $this->assertCount(1, $organizations);
        $this->assertEquals('Organization', $organizations[0]->getFirstName());
    }

    public function testFindWithHighEngagement(): void
    {
        $highEngagementContact = $this->createContact([
            'firstName' => 'High',
            'lastName' => 'Engagement'
        ]);
        $highEngagementContact->setEngagementScore(85);

        $lowEngagementContact = $this->createContact([
            'firstName' => 'Low',
            'lastName' => 'Engagement'
        ]);
        $lowEngagementContact->setEngagementScore(25);

        $this->entityManager->flush();

        // Test find contacts with engagement score >= 70
        $highEngagementContacts = $this->repository->findWithHighEngagement();
        $this->assertCount(1, $highEngagementContacts);
        $this->assertEquals('High', $highEngagementContacts[0]->getFirstName());
        $this->assertEquals(85, $highEngagementContacts[0]->getEngagementScore());
    }

    public function testFindRecentlyCreated(): void
    {
        // Create old contact
        $oldContact = $this->createContact([
            'firstName' => 'Old',
            'lastName' => 'Contact'
        ]);
        // Manually set created date to simulate old contact
        $reflection = new \ReflectionClass($oldContact);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($oldContact, new \DateTime('-60 days'));

        // Create recent contact
        $recentContact = $this->createContact([
            'firstName' => 'Recent',
            'lastName' => 'Contact'
        ]);

        $this->entityManager->flush();

        // Test find contacts created in last 30 days
        $recentContacts = $this->repository->findRecentlyCreated(30);
        $this->assertCount(1, $recentContacts);
        $this->assertEquals('Recent', $recentContacts[0]->getFirstName());
    }

    public function testFindByTag(): void
    {
        $taggedContact1 = $this->createContact([
            'firstName' => 'Tagged1',
            'lastName' => 'Contact',
            'tags' => ['developer', 'senior']
        ]);

        $taggedContact2 = $this->createContact([
            'firstName' => 'Tagged2',
            'lastName' => 'Contact',
            'tags' => ['developer', 'junior']
        ]);

        $untaggedContact = $this->createContact([
            'firstName' => 'Untagged',
            'lastName' => 'Contact',
            'tags' => ['manager']
        ]);

        // Test find by single tag
        $developers = $this->repository->findByTag('developer');
        $this->assertCount(2, $developers);

        $seniors = $this->repository->findByTag('senior');
        $this->assertCount(1, $seniors);
        $this->assertEquals('Tagged1', $seniors[0]->getFirstName());

        // Test find by non-existent tag
        $nonExistent = $this->repository->findByTag('nonexistent');
        $this->assertCount(0, $nonExistent);
    }

    public function testFindByTags(): void
    {
        $contact1 = $this->createContact([
            'firstName' => 'Contact1',
            'tags' => ['developer', 'senior', 'fullstack']
        ]);

        $contact2 = $this->createContact([
            'firstName' => 'Contact2',
            'tags' => ['developer', 'junior']
        ]);

        $contact3 = $this->createContact([
            'firstName' => 'Contact3',
            'tags' => ['designer', 'senior']
        ]);

        // Test find contacts with all specified tags
        $seniorDevelopers = $this->repository->findByTags(['developer', 'senior']);
        $this->assertCount(1, $seniorDevelopers);
        $this->assertEquals('Contact1', $seniorDevelopers[0]->getFirstName());

        // Test find contacts with any of the specified tags
        $developersOrDesigners = $this->repository->findByTags(['developer', 'designer'], false);
        $this->assertCount(3, $developersOrDesigners);
    }

    public function testGetEngagementStatistics(): void
    {
        // Create contacts with different engagement scores
        $contact1 = $this->createContact(['firstName' => 'Contact1']);
        $contact1->setEngagementScore(90);

        $contact2 = $this->createContact(['firstName' => 'Contact2']);
        $contact2->setEngagementScore(75);

        $contact3 = $this->createContact(['firstName' => 'Contact3']);
        $contact3->setEngagementScore(45);

        $contact4 = $this->createContact(['firstName' => 'Contact4']);
        $contact4->setEngagementScore(20);

        $this->entityManager->flush();

        $stats = $this->repository->getEngagementStatistics();

        $this->assertArrayHasKey('total_contacts', $stats);
        $this->assertArrayHasKey('average_engagement', $stats);
        $this->assertArrayHasKey('high_engagement', $stats);
        $this->assertArrayHasKey('medium_engagement', $stats);
        $this->assertArrayHasKey('low_engagement', $stats);

        $this->assertEquals(4, $stats['total_contacts']);
        $this->assertEquals(57.5, $stats['average_engagement']);
        $this->assertEquals(2, $stats['high_engagement']); // >= 70
        $this->assertEquals(1, $stats['medium_engagement']); // 30-69
        $this->assertEquals(1, $stats['low_engagement']); // < 30
    }

    public function testGetContactsByLanguage(): void
    {
        $this->createContact([
            'firstName' => 'English',
            'lastName' => 'User',
            'language' => 'en'
        ]);

        $this->createContact([
            'firstName' => 'Spanish',
            'lastName' => 'User',
            'language' => 'es'
        ]);

        $this->createContact([
            'firstName' => 'French',
            'lastName' => 'User',
            'language' => 'fr'
        ]);

        $this->createContact([
            'firstName' => 'English2',
            'lastName' => 'User',
            'language' => 'en'
        ]);

        $languageStats = $this->repository->getContactsByLanguage();

        $this->assertArrayHasKey('en', $languageStats);
        $this->assertArrayHasKey('es', $languageStats);
        $this->assertArrayHasKey('fr', $languageStats);

        $this->assertEquals(2, $languageStats['en']);
        $this->assertEquals(1, $languageStats['es']);
        $this->assertEquals(1, $languageStats['fr']);
    }

    public function testFindDuplicatesByEmail(): void
    {
        $contact1 = $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);

        $contact2 = $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Smith'
        ]);

        $contact3 = $this->createContact([
            'firstName' => 'Jane',
            'lastName' => 'Doe'
        ]);

        // Same email for different contacts
        $this->createContactChannel($contact1, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'duplicate@example.com'
        ]);

        $this->createContactChannel($contact2, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'duplicate@example.com'
        ]);

        $this->createContactChannel($contact3, [
            'type' => ContactChannel::TYPE_EMAIL,
            'identifier' => 'unique@example.com'
        ]);

        $duplicates = $this->repository->findDuplicatesByEmail();

        $this->assertCount(1, $duplicates); // One email with duplicates
        $this->assertEquals('duplicate@example.com', $duplicates[0]['email']);
        $this->assertEquals(2, $duplicates[0]['count']);
    }

    public function testFindInactiveContacts(): void
    {
        $activeContact = $this->createContact([
            'firstName' => 'Active',
            'lastName' => 'User'
        ]);

        $inactiveContact = $this->createContact([
            'firstName' => 'Inactive',
            'lastName' => 'User'
        ]);

        // Create activities for contacts to track activity
        $this->createContactActivity($activeContact, [
            'activityType' => \Nkamuo\NotificationTrackerBundle\Entity\ContactActivity::TYPE_CONTACT_UPDATED,
            'title' => 'Recent Activity',
            'occurredAt' => new \DateTime('-5 days')
        ]);

        $this->createContactActivity($inactiveContact, [
            'activityType' => \Nkamuo\NotificationTrackerBundle\Entity\ContactActivity::TYPE_CONTACT_CREATED,
            'title' => 'Old Activity',
            'occurredAt' => new \DateTime('-60 days')
        ]);

        // Find contacts inactive for more than 30 days (based on activities)
        $inactiveContacts = $this->repository->findInactiveContacts(30);

        $this->assertCount(1, $inactiveContacts);
        $this->assertEquals('Inactive', $inactiveContacts[0]->getFirstName());
    }

    public function testBulkUpdateStatus(): void
    {
        $contact1 = $this->createContact([
            'firstName' => 'Contact1',
            'status' => Contact::STATUS_ACTIVE
        ]);

        $contact2 = $this->createContact([
            'firstName' => 'Contact2',
            'status' => Contact::STATUS_ACTIVE
        ]);

        $contact3 = $this->createContact([
            'firstName' => 'Contact3',
            'status' => Contact::STATUS_INACTIVE
        ]);

        $contactIds = [$contact1->getId(), $contact2->getId()];

        // Bulk update status to blocked
        $updatedCount = $this->repository->bulkUpdateStatus($contactIds, Contact::STATUS_BLOCKED);

        $this->assertEquals(2, $updatedCount);

        // Verify updates
        $this->entityManager->refresh($contact1);
        $this->entityManager->refresh($contact2);
        $this->entityManager->refresh($contact3);

        $this->assertEquals(Contact::STATUS_BLOCKED, $contact1->getStatus());
        $this->assertEquals(Contact::STATUS_BLOCKED, $contact2->getStatus());
        $this->assertEquals(Contact::STATUS_INACTIVE, $contact3->getStatus()); // Unchanged
    }

    public function testGetContactCreationTrend(): void
    {
        // Create contacts on different dates
        $contact1 = $this->createContact(['firstName' => 'Contact1']);
        $reflection = new \ReflectionClass($contact1);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($contact1, new \DateTime('2023-01-15'));

        $contact2 = $this->createContact(['firstName' => 'Contact2']);
        $property->setValue($contact2, new \DateTime('2023-01-16'));

        $contact3 = $this->createContact(['firstName' => 'Contact3']);
        $property->setValue($contact3, new \DateTime('2023-02-01'));

        $this->entityManager->flush();

        $trend = $this->repository->getContactCreationTrend(30);

        $this->assertIsArray($trend);
        $this->assertNotEmpty($trend);

        // Verify structure
        foreach ($trend as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('count', $day);
        }
    }

    public function testFindContactsNeedingAttention(): void
    {
        // Contact with low engagement
        $needsAttention = $this->createContact([
            'firstName' => 'NeedsAttention',
            'lastName' => 'User'
        ]);
        $needsAttention->setEngagementScore(15);

        // Contact with good engagement
        $goodContact = $this->createContact([
            'firstName' => 'Good',
            'lastName' => 'User'
        ]);
        $goodContact->setEngagementScore(85);

        // Create old activity for the contact needing attention
        $this->createContactActivity($needsAttention, [
            'activityType' => \Nkamuo\NotificationTrackerBundle\Entity\ContactActivity::TYPE_CONTACT_CREATED,
            'title' => 'Old Activity',
            'occurredAt' => new \DateTime('-45 days')
        ]);

        // Create recent activity for the good contact
        $this->createContactActivity($goodContact, [
            'activityType' => \Nkamuo\NotificationTrackerBundle\Entity\ContactActivity::TYPE_CONTACT_UPDATED,
            'title' => 'Recent Activity',
            'occurredAt' => new \DateTime('-5 days')
        ]);

        $this->entityManager->flush();

        $contactsNeedingAttention = $this->repository->findContactsNeedingAttention();

        $this->assertCount(1, $contactsNeedingAttention);
        $this->assertEquals('NeedsAttention', $contactsNeedingAttention[0]->getFirstName());
    }
}
