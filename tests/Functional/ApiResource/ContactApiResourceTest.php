<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\ApiResource;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Symfony\Component\HttpFoundation\Response;

class ContactApiResourceTest extends BaseApiTestCase
{
    public function testGetContactCollection(): void
    {
        // Create test contacts
        $contact1 = $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'type' => Contact::TYPE_INDIVIDUAL
        ]);
        
        $contact2 = $this->createContact([
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'type' => Contact::TYPE_INDIVIDUAL
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contacts');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(2, $data['hydra:member']);
        
        $contactData = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $contactData);
        $this->assertArrayHasKey('firstName', $contactData);
        $this->assertArrayHasKey('lastName', $contactData);
        $this->assertArrayHasKey('displayName', $contactData);
        $this->assertArrayHasKey('type', $contactData);
        $this->assertArrayHasKey('status', $contactData);
    }

    public function testGetContact(): void
    {
        $contact = $this->createContact([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'organizationName' => 'Acme Corp',
            'jobTitle' => 'Developer',
            'language' => 'en',
            'timezone' => 'UTC',
            'tags' => ['developer', 'senior'],
            'customFields' => ['department' => 'IT', 'level' => 'senior'],
            'notes' => 'Important contact'
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contacts/' . $contact->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($contact->getId(), $data['id']);
        $this->assertEquals('John', $data['firstName']);
        $this->assertEquals('Doe', $data['lastName']);
        $this->assertEquals('John Doe', $data['displayName']);
        $this->assertEquals('Acme Corp', $data['organizationName']);
        $this->assertEquals('Developer', $data['jobTitle']);
        $this->assertEquals('en', $data['language']);
        $this->assertEquals('UTC', $data['timezone']);
        $this->assertEquals(['developer', 'senior'], $data['tags']);
        $this->assertEquals(['department' => 'IT', 'level' => 'senior'], $data['customFields']);
        $this->assertEquals('Important contact', $data['notes']);
    }

    public function testCreateContact(): void
    {
        $contactData = [
            'type' => Contact::TYPE_INDIVIDUAL,
            'firstName' => 'Alice',
            'lastName' => 'Johnson',
            'displayName' => 'Alice Johnson',
            'organizationName' => 'Tech Corp',
            'jobTitle' => 'Manager',
            'department' => 'Engineering',
            'language' => 'en',
            'timezone' => 'America/New_York',
            'tags' => ['manager', 'tech'],
            'customFields' => [
                'employee_id' => 'EMP001',
                'start_date' => '2023-01-15'
            ],
            'notes' => 'New team manager'
        ];

        $response = $this->makeJsonRequest('POST', '/api/contacts', $contactData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Alice', $data['firstName']);
        $this->assertEquals('Johnson', $data['lastName']);
        $this->assertEquals('Alice Johnson', $data['displayName']);
        $this->assertEquals('Tech Corp', $data['organizationName']);
        $this->assertEquals('Manager', $data['jobTitle']);
        $this->assertEquals('Engineering', $data['department']);
        $this->assertEquals(['manager', 'tech'], $data['tags']);
        $this->assertEquals(['employee_id' => 'EMP001', 'start_date' => '2023-01-15'], $data['customFields']);

        // Verify in database
        $contact = $this->getContactRepository()->find($data['id']);
        $this->assertNotNull($contact);
        $this->assertEquals('Alice', $contact->getFirstName());
        $this->assertEquals('Johnson', $contact->getLastName());
    }

    public function testUpdateContact(): void
    {
        $contact = $this->createContact([
            'firstName' => 'Bob',
            'lastName' => 'Wilson',
            'jobTitle' => 'Developer'
        ]);

        $updateData = [
            'jobTitle' => 'Senior Developer',
            'department' => 'Backend',
            'customFields' => [
                'skills' => ['PHP', 'Symfony', 'Docker']
            ]
        ];

        $response = $this->makeJsonRequest('PUT', '/api/contacts/' . $contact->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals('Senior Developer', $data['jobTitle']);
        $this->assertEquals('Backend', $data['department']);
        $this->assertEquals(['skills' => ['PHP', 'Symfony', 'Docker']], $data['customFields']);

        // Verify in database
        $this->entityManager->refresh($contact);
        $this->assertEquals('Senior Developer', $contact->getJobTitle());
        $this->assertEquals('Backend', $contact->getDepartment());
    }

    public function testPatchContact(): void
    {
        $contact = $this->createContact([
            'firstName' => 'Charlie',
            'lastName' => 'Brown',
            'tags' => ['developer']
        ]);

        $patchData = [
            'tags' => ['developer', 'senior', 'team-lead']
        ];

        $response = $this->makeJsonRequest('PATCH', '/api/contacts/' . $contact->getId(), $patchData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(['developer', 'senior', 'team-lead'], $data['tags']);

        // Verify in database
        $this->entityManager->refresh($contact);
        $this->assertEquals(['developer', 'senior', 'team-lead'], $contact->getTags());
    }

    public function testDeleteContact(): void
    {
        $contact = $this->createContact([
            'firstName' => 'David',
            'lastName' => 'Miller'
        ]);

        $contactId = $contact->getId();

        $response = $this->makeJsonRequest('DELETE', '/api/contacts/' . $contactId);
        $this->assertEquals(204, $response->getStatusCode());

        // Verify contact is deleted
        $deletedContact = $this->getContactRepository()->find($contactId);
        $this->assertNull($deletedContact);
    }

    public function testContactValidation(): void
    {
        // Test missing required fields
        $invalidData = [
            'type' => 'invalid_type'
        ];

        $response = $this->makeJsonRequest('POST', '/api/contacts', $invalidData);
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testContactFiltering(): void
    {
        // Create contacts with different attributes
        $this->createContact([
            'firstName' => 'Active',
            'lastName' => 'User',
            'status' => Contact::STATUS_ACTIVE,
            'type' => Contact::TYPE_INDIVIDUAL
        ]);

        $this->createContact([
            'firstName' => 'Inactive',
            'lastName' => 'User',
            'status' => Contact::STATUS_INACTIVE,
            'type' => Contact::TYPE_INDIVIDUAL
        ]);

        $this->createContact([
            'firstName' => 'Org',
            'lastName' => 'Contact',
            'type' => Contact::TYPE_ORGANIZATION
        ]);

        // Test status filter
        $response = $this->makeJsonRequest('GET', '/api/contacts?status=' . Contact::STATUS_ACTIVE);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test type filter
        $response = $this->makeJsonRequest('GET', '/api/contacts?type=' . Contact::TYPE_ORGANIZATION);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testContactSearch(): void
    {
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

        // Test search by name
        $response = $this->makeJsonRequest('GET', '/api/contacts?search=John');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test search by organization
        $response = $this->makeJsonRequest('GET', '/api/contacts?search=Tech');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testContactPagination(): void
    {
        // Create 15 contacts
        for ($i = 1; $i <= 15; $i++) {
            $this->createContact([
                'firstName' => "User{$i}",
                'lastName' => 'Test'
            ]);
        }

        // Test first page
        $response = $this->makeJsonRequest('GET', '/api/contacts?page=1&itemsPerPage=10');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertCount(10, $data['hydra:member']);
        $this->assertArrayHasKey('hydra:view', $data);
        $this->assertArrayHasKey('hydra:next', $data['hydra:view']);

        // Test second page
        $response = $this->makeJsonRequest('GET', '/api/contacts?page=2&itemsPerPage=10');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertCount(5, $data['hydra:member']);
        $this->assertArrayHasKey('hydra:previous', $data['hydra:view']);
    }

    public function testContactOrdering(): void
    {
        $this->createContact([
            'firstName' => 'Charlie',
            'lastName' => 'Brown',
            'createdAt' => new \DateTime('2023-01-01')
        ]);

        $this->createContact([
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'createdAt' => new \DateTime('2023-01-02')
        ]);

        $this->createContact([
            'firstName' => 'Bob',
            'lastName' => 'Johnson',
            'createdAt' => new \DateTime('2023-01-03')
        ]);

        // Test order by firstName
        $response = $this->makeJsonRequest('GET', '/api/contacts?order[firstName]=asc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('Alice', $data['hydra:member'][0]['firstName']);
        $this->assertEquals('Bob', $data['hydra:member'][1]['firstName']);
        $this->assertEquals('Charlie', $data['hydra:member'][2]['firstName']);

        // Test order by createdAt desc
        $response = $this->makeJsonRequest('GET', '/api/contacts?order[createdAt]=desc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('Bob', $data['hydra:member'][0]['firstName']);
        $this->assertEquals('Alice', $data['hydra:member'][1]['firstName']);
        $this->assertEquals('Charlie', $data['hydra:member'][2]['firstName']);
    }

    public function testContactWithChannels(): void
    {
        $contact = $this->createContact([
            'firstName' => 'Contact',
            'lastName' => 'WithChannels'
        ]);

        $this->createContactChannel($contact, [
            'type' => 'email',
            'identifier' => 'test@example.com',
            'isPrimary' => true
        ]);

        $this->createContactChannel($contact, [
            'type' => 'sms',
            'identifier' => '+1234567890',
            'isPrimary' => false
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contacts/' . $contact->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('channels', $data);
        $this->assertCount(2, $data['channels']);

        // Verify channel data
        $emailChannel = array_filter($data['channels'], fn($ch) => $ch['type'] === 'email')[0];
        $this->assertEquals('test@example.com', $emailChannel['identifier']);
        $this->assertTrue($emailChannel['isPrimary']);
    }
}
