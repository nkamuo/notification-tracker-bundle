<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nkamuo\NotificationTrackerBundle\Repository\MessageTemplateRepository;
use Nkamuo\NotificationTrackerBundle\Config\ApiRoutes;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageTemplateRepository::class)]
#[ORM\Table(name: 'notification_tracker_message_templates')]
#[ORM\UniqueConstraint(name: 'uniq_nt_template_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'MessageTemplate',
    description: 'Reusable message templates',
    normalizationContext: ['groups' => ['template:read']],
    denormalizationContext: ['groups' => ['template:write']],
    operations: [
        new GetCollection(
            uriTemplate: ApiRoutes::TEMPLATES,
            normalizationContext: ['groups' => ['template:list']]
        ),
        new Get(
            uriTemplate: ApiRoutes::TEMPLATES . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}']
        ),
        new Post(
            uriTemplate: ApiRoutes::TEMPLATES
        ),
        new Put(
            uriTemplate: ApiRoutes::TEMPLATES . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}']
        ),
        new Delete(
            uriTemplate: ApiRoutes::TEMPLATES . '/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}']
        ),
    ]
)]
class MessageTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[Groups(['template:read', 'template:list', 'message:read'])]
    private Ulid $id;

    #[ORM\Column(length: 100)]
    #[Groups(['template:read', 'template:list', 'template:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['template:read', 'template:list', 'template:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Groups(['template:read', 'template:list', 'template:write'])]
    #[Assert\Choice(choices: ['email', 'sms', 'slack', 'telegram', 'push', 'generic'])]
    private string $type;

    #[ORM\Column(length: 998, nullable: true)]
    #[Groups(['template:read', 'template:write'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['template:read', 'template:write'])]
    private ?string $bodyText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['template:read', 'template:write'])]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['template:read', 'template:write'])]
    private array $variables = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['template:read', 'template:write'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['template:read', 'template:list', 'template:write'])]
    private bool $active = true;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['template:read', 'template:write'])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['template:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['template:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // All getters and setters
    public function getId(): Ulid
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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): self
    {
        $this->bodyText = $bodyText;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): self
    {
        $this->bodyHtml = $bodyHtml;
        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): self
    {
        $this->variables = $variables;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}