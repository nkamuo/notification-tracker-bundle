<?php

namespace Nkamuo\NotificationTrackerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;

#[ORM\Entity(repositoryClass: 'Nkamuo\NotificationTrackerBundle\Repository\ContactGroupRepository')]
#[ORM\Table(name: 'nt_contact_group')]
#[ORM\Index(columns: ['type'], name: 'idx_contact_group_type')]
#[ORM\Index(columns: ['status'], name: 'idx_contact_group_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_contact_group_created')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['contact_group:read']],
    denormalizationContext: ['groups' => ['contact_group:write']]
)]
class ContactGroup
{
    public const TYPE_STATIC = 'static'; // Manually managed membership
    public const TYPE_DYNAMIC = 'dynamic'; // Rule-based membership
    public const TYPE_SEGMENT = 'segment'; // Marketing segment
    public const TYPE_TAG = 'tag'; // Simple tag-based grouping
    public const TYPE_DEPARTMENT = 'department'; // Organizational unit
    public const TYPE_ROLE = 'role'; // Job role or function
    public const TYPE_LOCATION = 'location'; // Geographic grouping
    public const TYPE_BEHAVIOR = 'behavior'; // Behavior-based segment

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    #[Groups(['contact_group:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private string $name;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::TYPE_STATIC,
        self::TYPE_DYNAMIC,
        self::TYPE_SEGMENT,
        self::TYPE_TAG,
        self::TYPE_DEPARTMENT,
        self::TYPE_ROLE,
        self::TYPE_LOCATION,
        self::TYPE_BEHAVIOR
    ])]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private string $type = self::TYPE_STATIC;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice([
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED
    ])]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?string $color = null; // For UI display

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?string $icon = null; // For UI display

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?array $criteria = null; // For dynamic groups - rules for auto-membership

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?array $metadata = null; // Additional group metadata

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?array $tags = null; // Tags for this group

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?string $externalId = null; // ID in external system

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_group:read'])]
    private int $contactCount = 0; // Cached count for performance

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['contact_group:read'])]
    private int $activeContactCount = 0; // Active contacts only

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_group:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['contact_group:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['contact_group:read'])]
    private ?\DateTimeImmutable $lastSyncAt = null; // For dynamic groups

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?string $createdBy = null; // User who created this group

    // Relationships
    #[ORM\ManyToMany(targetEntity: Contact::class, mappedBy: 'groups')]
    #[Groups(['contact_group:read'])]
    private Collection $contacts;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[Groups(['contact_group:read', 'contact_group:write'])]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[Groups(['contact_group:read'])]
    private Collection $children;

    public function __construct()
    {
        $this->id = $this->generateUlid();
        $this->contacts = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateUlid(): string
    {
        // Simple ULID-like generation - in production, use proper ULID library
        return sprintf(
            '%08x%04x%04x%04x%12x',
            time(),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateContactCounts(): void
    {
        $this->contactCount = $this->contacts->count();
        $this->activeContactCount = $this->contacts->filter(function (Contact $contact) {
            return $contact->getStatus() === Contact::STATUS_ACTIVE;
        })->count();
    }

    public function addContact(Contact $contact): self
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts[] = $contact;
            $contact->addGroup($this);
            $this->updateContactCounts();
        }
        return $this;
    }

    public function removeContact(Contact $contact): self
    {
        if ($this->contacts->removeElement($contact)) {
            $contact->removeGroup($this);
            $this->updateContactCounts();
        }
        return $this;
    }

    public function hasContact(Contact $contact): bool
    {
        return $this->contacts->contains($contact);
    }

    public function getActiveContacts(): Collection
    {
        return $this->contacts->filter(function (Contact $contact) {
            return $contact->getStatus() === Contact::STATUS_ACTIVE;
        });
    }

    public function getContactsByChannel(string $channelType): Collection
    {
        return $this->contacts->filter(function (Contact $contact) use ($channelType) {
            return $contact->getActiveChannels($channelType)->count() > 0;
        });
    }

    public function evaluateDynamicCriteria(Contact $contact): bool
    {
        if ($this->type !== self::TYPE_DYNAMIC || !$this->criteria) {
            return false;
        }

        // Basic criteria evaluation - extend as needed
        foreach ($this->criteria as $criterion) {
            if (!$this->evaluateCriterion($contact, $criterion)) {
                return false; // AND logic - all criteria must match
            }
        }

        return true;
    }

    private function evaluateCriterion(Contact $contact, array $criterion): bool
    {
        $field = $criterion['field'] ?? '';
        $operator = $criterion['operator'] ?? '=';
        $value = $criterion['value'] ?? null;

        switch ($field) {
            case 'status':
                return $this->compareValues($contact->getStatus(), $operator, $value);
            case 'type':
                return $this->compareValues($contact->getType(), $operator, $value);
            case 'tags':
                return $this->evaluateTagCriterion($contact->getTags() ?? [], $operator, $value);
            case 'engagement_score':
                return $this->compareValues($contact->getEngagementScore(), $operator, (int)$value);
            case 'created_at':
                return $this->compareDates($contact->getCreatedAt(), $operator, $value);
            case 'last_contacted_at':
                return $this->compareDates($contact->getLastContactedAt(), $operator, $value);
            case 'has_channel':
                return $contact->getActiveChannels($value)->count() > 0;
            default:
                // Check custom fields
                if (str_starts_with($field, 'custom.')) {
                    $customField = substr($field, 7);
                    $customValue = $contact->getCustomField($customField);
                    return $this->compareValues($customValue, $operator, $value);
                }
                return false;
        }
    }

    private function compareValues($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case '=':
            case 'equals':
                return $actual == $expected;
            case '!=':
            case 'not_equals':
                return $actual != $expected;
            case '>':
            case 'greater_than':
                return $actual > $expected;
            case '>=':
            case 'greater_than_or_equal':
                return $actual >= $expected;
            case '<':
            case 'less_than':
                return $actual < $expected;
            case '<=':
            case 'less_than_or_equal':
                return $actual <= $expected;
            case 'contains':
                return str_contains((string)$actual, (string)$expected);
            case 'starts_with':
                return str_starts_with((string)$actual, (string)$expected);
            case 'ends_with':
                return str_ends_with((string)$actual, (string)$expected);
            case 'in':
                return is_array($expected) && in_array($actual, $expected);
            case 'not_in':
                return is_array($expected) && !in_array($actual, $expected);
            default:
                return false;
        }
    }

    private function evaluateTagCriterion(array $contactTags, string $operator, $value): bool
    {
        switch ($operator) {
            case 'has_tag':
                return in_array($value, $contactTags);
            case 'has_any_tag':
                return is_array($value) && !empty(array_intersect($contactTags, $value));
            case 'has_all_tags':
                return is_array($value) && empty(array_diff($value, $contactTags));
            case 'missing_tag':
                return !in_array($value, $contactTags);
            default:
                return false;
        }
    }

    private function compareDates(?\DateTimeInterface $actual, string $operator, $value): bool
    {
        if (!$actual) {
            return $operator === 'is_null';
        }

        try {
            $expected = new \DateTimeImmutable($value);
            return $this->compareValues($actual->getTimestamp(), $operator, $expected->getTimestamp());
        } catch (\Exception) {
            return false;
        }
    }

    public function addTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
        }
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        $this->tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return ($this->metadata ?? [])[$key] ?? $default;
    }

    public function getFullPath(): string
    {
        $path = [$this->name];
        $current = $this->parent;
        
        while ($current) {
            array_unshift($path, $current->getName());
            $current = $current->getParent();
        }
        
        return implode(' > ', $path);
    }

    public function getAllDescendants(): Collection
    {
        $descendants = new ArrayCollection();
        
        foreach ($this->children as $child) {
            $descendants->add($child);
            foreach ($child->getAllDescendants() as $grandchild) {
                $descendants->add($grandchild);
            }
        }
        
        return $descendants;
    }

    public function isAncestorOf(ContactGroup $group): bool
    {
        return $this->getAllDescendants()->contains($group);
    }

    public function isDescendantOf(ContactGroup $group): bool
    {
        return $group->isAncestorOf($this);
    }

    // Getters and Setters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getCriteria(): ?array
    {
        return $this->criteria;
    }

    public function setCriteria(?array $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getMetadataArray(): ?array
    {
        return $this->metadata;
    }

    public function setMetadataArray(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getContactCount(): int
    {
        return $this->contactCount;
    }

    public function setContactCount(int $contactCount): self
    {
        $this->contactCount = $contactCount;
        return $this;
    }

    public function getActiveContactCount(): int
    {
        return $this->activeContactCount;
    }

    public function setActiveContactCount(int $activeContactCount): self
    {
        $this->activeContactCount = $activeContactCount;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }
}
