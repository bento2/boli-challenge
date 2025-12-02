<?php

declare(strict_types=1);

namespace App\Document;

use App\Enum\NotificationTypes;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mappage du document avec les index composés.
 */
#[ODM\Document(collection: 'notifications')]
// Index Composé 1 : Pour récupérer les dernières notifications d'un utilisateur.
#[ODM\Index(keys: ['userId' => 'asc', 'createdAt' => 'desc'])]
// Index Composé 2 : Pour optimiser les tâches backend de traitement des envois.
#[ODM\Index(keys: ['status' => 'asc', 'serviceName' => 'asc'])]
class Notification
{
    /**
     * @var string|null
     * ID unique du document.
     */
    #[ODM\Id]
    private ?string $id = null;

    /**
     * @var string
     * ID de l'utilisateur. Ajout de l'index simple ici (bien que couvert par l'index composé)
     * pour plus de clarté si des requêtes sur userId seul existent.
     */
    #[ODM\Field(type: 'string')]
    // Ajout d'un index simple sur userId
    #[ODM\Index(unique: false)]
    private string $userId;

    /**
     * @var string
     * Type de notification (alert|reminder|info).
     */
    #[ODM\Field(type: 'string')]
    #[Assert\NotNull]
    #[Assert\Choice(callback: [NotificationTypes::class, 'getValues'])]
    private string $type;

    /**
     * @var string
     * Titre de la notification.
     */
    #[ODM\Field(type: 'string')]
    private string $title;

    /**
     * @var string
     * Corps du message.
     */
    #[ODM\Field(type: 'string')]
    private string $body;

    /**
     * @var array
     * Données flexibles/payload.
     */
    #[ODM\Field(type: 'hash')]
    private array $data = [];

    /**
     * @var string
     * Statut de livraison (pending|sent|failed).
     */
    #[ODM\Field(type: 'string')]
    private string $status = 'pending';

    /**
     * @var \DateTimeInterface|null
     * Horodatage de l'envoi.
     */
    #[ODM\Field(type: 'date', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    /**
     * @var \DateTimeInterface
     * Horodatage de la création. **Ce champ est la base de l'index TTL de 90 jours.**
     */
    #[ODM\Field(type: 'date')]
    private \DateTimeInterface $createdAt;

    /**
     * @var string
     * Le service source (diabetes|wellness|maternity).
     */
    #[ODM\Field(type: 'string')]
    private string $serviceName;

    /**
     * @var \DateTimeInterface|null
     * Horodatage de la lecture par l'utilisateur.
     */
    #[ODM\Field(type: 'date', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    // --- Constructor & Getters/Setters (omitted for brevity) ---
    public function __construct(string $userId, string $type, string $title, string $body, string $serviceName, array $data = [])
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->title = $title;
        $this->body = $body;
        $this->serviceName = $serviceName;
        $this->data = $data;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status):  self
    {
        $this->status = $status;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }


}
