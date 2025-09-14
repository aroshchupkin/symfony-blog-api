<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Comment Entity
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'comments')]
#[ORM\Index(name: 'comment_created_at_index', columns: ['created_at'])]
class Comment
{
    /**
     * Unique identifier for the comment
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comment:read', 'comment:list', 'post:read'])]
    private ?int $id = null;

    /**
     * Comment content
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content cannot be blank')]
    #[Assert\Length(min: 5, minMessage: 'Content must be at least 5 characters')]
    #[Groups(['comment:read', 'comment:list', 'comment:write', 'post:read'])]
    private ?string $content = null;

    /**
     * Timestamp when comment was created
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['comment:read', 'comment:list', 'post:read'])]
    private ?\DateTimeInterface  $createdAt = null;

    /**
     * Timestamp when comment was last updated
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['comment:read', 'comment:list', 'post:read'])]
    private ?\DateTimeInterface  $updatedAt = null;

    /**
     * Post that this comment belongs to
     */
    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Post is required')]
    private ?Post $post = null;

    /**
     * User who authored this comment
     */
    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Author is required')]
    private ?User $author = null;

    /**
     * Get comment ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get comment content
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Set comment content
     */
    public function setContent(string $content): static
    {
        $this->content = trim($content);

        return $this;
    }

    /**
     * Get author username
     */
    #[Groups(['comment:read', 'comment:list', 'post:read'])]
    public function getAuthorUsername(): ?string
    {
        return $this->author?->getUsername();
    }

    /**
     * Get author email
     */
    public function getAuthorEmail(): ?string
    {
        return $this->author?->getEmail();
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set creation timestamp
     */
    public function setCreatedAt(\DateTimeInterface  $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get last update timestamp
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Set last update timestamp
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Automatically set creation timestamp before persistence
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Automatically set update timestamp before update
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get the post this comment belongs to
     */
    public function getPost(): ?Post
    {
        return $this->post;
    }

    /**
     * Set the post this comment belongs to
     */
    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }

    /**
     * Get comment author
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * Set comment author
     */
    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }
}
