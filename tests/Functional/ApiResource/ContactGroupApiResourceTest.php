<?php

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional\ApiResource;

use Nkamuo\NotificationTrackerBundle\Tests\Functional\BaseApiTestCase;
use Nkamuo\NotificationTrackerBundle\Entity\Contact;
use Nkamuo\NotificationTrackerBundle\Entity\ContactGroup;

class ContactGroupApiResourceTest extends BaseApiTestCase
{
    public function testGetContactGroupCollection(): void
    {
        $group1 = $this->createContactGroup([
            'name' => 'Developers',
            'type' => ContactGroup::TYPE_STATIC
        ]);
        
        $group2 = $this->createContactGroup([
            'name' => 'Managers',
            'type' => ContactGroup::TYPE_DYNAMIC
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_groups');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(2, $data['hydra:member']);
        
        $groupData = $data['hydra:member'][0];
        $this->assertArrayHasKey('id', $groupData);
        $this->assertArrayHasKey('name', $groupData);
        $this->assertArrayHasKey('description', $groupData);
        $this->assertArrayHasKey('type', $groupData);
        $this->assertArrayHasKey('totalContactCount', $groupData);
        $this->assertArrayHasKey('activeContactCount', $groupData);
    }

    public function testGetContactGroup(): void
    {
        $parentGroup = $this->createContactGroup([
            'name' => 'All Employees',
            'type' => ContactGroup::TYPE_STATIC
        ]);

        $group = $this->createContactGroup([
            'name' => 'Engineering Team',
            'description' => 'All engineering staff',
            'type' => ContactGroup::TYPE_DYNAMIC,
            'parent' => $parentGroup,
            'criteria' => [
                'department' => 'Engineering',
                'status' => 'active'
            ],
            'tags' => ['tech', 'development']
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($group->getId(), $data['id']);
        $this->assertEquals('Engineering Team', $data['name']);
        $this->assertEquals('All engineering staff', $data['description']);
        $this->assertEquals(ContactGroup::TYPE_DYNAMIC, $data['type']);
        $this->assertEquals(['department' => 'Engineering', 'status' => 'active'], $data['criteria']);
        $this->assertEquals(['tech', 'development'], $data['tags']);
        $this->assertArrayHasKey('parent', $data);
    }

    public function testCreateContactGroup(): void
    {
        $groupData = [
            'name' => 'VIP Customers',
            'description' => 'High-value customers requiring special attention',
            'type' => ContactGroup::TYPE_BEHAVIOR,
            'criteria' => [
                'total_purchases' => ['>=', 10000],
                'last_purchase' => ['within', '30 days']
            ],
            'tags' => ['vip', 'priority'],
            'autoAddCriteria' => [
                'engagement_score' => ['>=', 80]
            ],
            'autoRemoveCriteria' => [
                'last_activity' => ['older_than', '90 days']
            ]
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_groups', $groupData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('VIP Customers', $data['name']);
        $this->assertEquals('High-value customers requiring special attention', $data['description']);
        $this->assertEquals(ContactGroup::TYPE_BEHAVIOR, $data['type']);
        $this->assertEquals(['total_purchases' => ['>=', 10000], 'last_purchase' => ['within', '30 days']], $data['criteria']);
        $this->assertEquals(['vip', 'priority'], $data['tags']);

        // Verify in database
        $group = $this->getContactGroupRepository()->find($data['id']);
        $this->assertNotNull($group);
        $this->assertEquals('VIP Customers', $group->getName());
        $this->assertEquals(ContactGroup::TYPE_BEHAVIOR, $group->getType());
    }

    public function testUpdateContactGroup(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Beta Users',
            'description' => 'Users testing new features'
        ]);

        $updateData = [
            'description' => 'Advanced users testing beta features',
            'criteria' => [
                'beta_opt_in' => true,
                'account_type' => 'premium'
            ],
            'tags' => ['beta', 'premium', 'testing']
        ];

        $response = $this->makeJsonRequest('PUT', '/api/contact_groups/' . $group->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals('Advanced users testing beta features', $data['description']);
        $this->assertEquals(['beta_opt_in' => true, 'account_type' => 'premium'], $data['criteria']);
        $this->assertEquals(['beta', 'premium', 'testing'], $data['tags']);

        // Verify in database
        $this->entityManager->refresh($group);
        $this->assertEquals('Advanced users testing beta features', $group->getDescription());
    }

    public function testDeleteContactGroup(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Temporary Group'
        ]);

        $groupId = $group->getId();

        $response = $this->makeJsonRequest('DELETE', '/api/contact_groups/' . $groupId);
        $this->assertEquals(204, $response->getStatusCode());

        // Verify group is deleted
        $deletedGroup = $this->getContactGroupRepository()->find($groupId);
        $this->assertNull($deletedGroup);
    }

    public function testContactGroupValidation(): void
    {
        // Test missing required fields
        $invalidData = [
            'type' => 'invalid_type'
            // Missing name
        ];

        $response = $this->makeJsonRequest('POST', '/api/contact_groups', $invalidData);
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testContactGroupFiltering(): void
    {
        $this->createContactGroup([
            'name' => 'Static Group 1',
            'type' => ContactGroup::TYPE_STATIC
        ]);

        $this->createContactGroup([
            'name' => 'Dynamic Group 1',
            'type' => ContactGroup::TYPE_DYNAMIC
        ]);

        $this->createContactGroup([
            'name' => 'Behavior Group 1',
            'type' => ContactGroup::TYPE_BEHAVIOR
        ]);

        // Test type filter
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?type=' . ContactGroup::TYPE_STATIC);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        $response = $this->makeJsonRequest('GET', '/api/contact_groups?type=' . ContactGroup::TYPE_DYNAMIC);
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testContactGroupSearch(): void
    {
        $this->createContactGroup([
            'name' => 'Engineering Team',
            'description' => 'Software developers and engineers'
        ]);

        $this->createContactGroup([
            'name' => 'Marketing Team',
            'description' => 'Marketing and promotion specialists'
        ]);

        // Test search by name
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?search=Engineering');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);

        // Test search by description
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?search=developers');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testHierarchicalGroups(): void
    {
        $parentGroup = $this->createContactGroup([
            'name' => 'All Staff',
            'type' => ContactGroup::TYPE_STATIC
        ]);

        $childGroup1 = $this->createContactGroup([
            'name' => 'Engineering',
            'parent' => $parentGroup,
            'type' => ContactGroup::TYPE_STATIC
        ]);

        $childGroup2 = $this->createContactGroup([
            'name' => 'Marketing',
            'parent' => $parentGroup,
            'type' => ContactGroup::TYPE_STATIC
        ]);

        // Test parent group shows children
        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $parentGroup->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('children', $data);
        $this->assertCount(2, $data['children']);

        // Test child group shows parent
        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $childGroup1->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('parent', $data);
        $this->assertEquals($parentGroup->getId(), $data['parent']['id']);
    }

    public function testContactGroupMembership(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Test Group',
            'type' => ContactGroup::TYPE_STATIC
        ]);

        $contact1 = $this->createContact(['firstName' => 'Member1']);
        $contact2 = $this->createContact(['firstName' => 'Member2']);

        // Add contacts to group
        $group->addContact($contact1);
        $group->addContact($contact2);
        $this->entityManager->flush();

        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(2, $data['totalContactCount']);
        $this->assertEquals(2, $data['activeContactCount']);

        // Test contacts endpoint for group
        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId() . '/contacts');
        if ($response->getStatusCode() === 200) {
            $contactsData = $this->assertJsonResponse($response, 200);
            $this->assertCount(2, $contactsData['hydra:member']);
        }
    }

    public function testDynamicGroupCriteria(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Premium Users',
            'type' => ContactGroup::TYPE_DYNAMIC,
            'criteria' => [
                'subscription_type' => 'premium',
                'status' => 'active'
            ]
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(ContactGroup::TYPE_DYNAMIC, $data['type']);
        $this->assertEquals(['subscription_type' => 'premium', 'status' => 'active'], $data['criteria']);
    }

    public function testBehaviorGroupRules(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Engaged Users',
            'type' => ContactGroup::TYPE_BEHAVIOR,
            'criteria' => [
                'engagement_score' => ['>=', 70]
            ]
        ]);

        // Create auto-add and auto-remove criteria
        $updateData = [
            'autoAddCriteria' => [
                'last_login' => ['within', '7 days'],
                'page_views' => ['>=', 10]
            ],
            'autoRemoveCriteria' => [
                'last_activity' => ['older_than', '30 days']
            ]
        ];

        $response = $this->makeJsonRequest('PATCH', '/api/contact_groups/' . $group->getId(), $updateData);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(ContactGroup::TYPE_BEHAVIOR, $data['type']);
        $this->assertArrayHasKey('autoAddCriteria', $data);
        $this->assertArrayHasKey('autoRemoveCriteria', $data);
    }

    public function testContactGroupTags(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Tagged Group',
            'tags' => ['important', 'customers', 'high-value']
        ]);

        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(['important', 'customers', 'high-value'], $data['tags']);

        // Test filtering by tags
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?tags[]=important');
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['hydra:member']);
    }

    public function testContactGroupStatistics(): void
    {
        $group = $this->createContactGroup([
            'name' => 'Stats Group'
        ]);

        $activeContact = $this->createContact([
            'firstName' => 'Active',
            'status' => Contact::STATUS_ACTIVE
        ]);

        $inactiveContact = $this->createContact([
            'firstName' => 'Inactive',
            'status' => Contact::STATUS_INACTIVE
        ]);

        $group->addContact($activeContact);
        $group->addContact($inactiveContact);
        $this->entityManager->flush();

        $response = $this->makeJsonRequest('GET', '/api/contact_groups/' . $group->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals(2, $data['totalContactCount']);
        $this->assertEquals(1, $data['activeContactCount']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    public function testContactGroupOrdering(): void
    {
        $this->createContactGroup([
            'name' => 'Charlie Group',
            'createdAt' => new \DateTime('2023-01-01')
        ]);

        $this->createContactGroup([
            'name' => 'Alpha Group',
            'createdAt' => new \DateTime('2023-01-02')
        ]);

        $this->createContactGroup([
            'name' => 'Beta Group',
            'createdAt' => new \DateTime('2023-01-03')
        ]);

        // Test order by name
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?order[name]=asc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('Alpha Group', $data['hydra:member'][0]['name']);
        $this->assertEquals('Beta Group', $data['hydra:member'][1]['name']);
        $this->assertEquals('Charlie Group', $data['hydra:member'][2]['name']);

        // Test order by createdAt desc
        $response = $this->makeJsonRequest('GET', '/api/contact_groups?order[createdAt]=desc');
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertEquals('Beta Group', $data['hydra:member'][0]['name']);
        $this->assertEquals('Alpha Group', $data['hydra:member'][1]['name']);
        $this->assertEquals('Charlie Group', $data['hydra:member'][2]['name']);
    }
}
