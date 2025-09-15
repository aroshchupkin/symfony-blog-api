<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Post Entity
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'posts')]
#[ORM\Index(name: 'post_created_at_index', columns: ['created_at'])]
class Post
{
    /**
     * Unique identifier for the post
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['post:detail', 'post:list'])]
    private ?int $id = null;

    /**
     * Post title
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Title cannot be blank.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Title must be at least 3 characters long',
        maxMessage: 'Title cannot be longer than 255 characters')]
    #[Groups(['post:detail', 'post:list'])]
    private ?string $title = null;

    /**
     * Post content
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content cannot be blank.')]
    #[Assert\Length(min: 10, minMessage: 'Content must be at least 10 characters long')]
    #[Groups(['post:detail'])]
    private ?string $content = null;

    /**
     * Timestamp when post was created
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['post:detail', 'post:list'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Timestamp when post was last updated
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['post:detail', 'post:list'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * User who authored this post
     */
    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    /**
     * Collection of comments on this post
     *
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true)]
    #[Groups(['post:detail'])]
    private Collection $comments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }

    /**
     * Get post ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get post title
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set post title
     */
    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    /**
     * Get post content
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Set post content
     */
    public function setContent(string $content): static
    {
        $this->content = trim($content);

        return $this;
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
    public function setCreatedAt(\DateTimeInterface $createdAt): static
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
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
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
     * Get collection of post comments
     *
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Add a comment to post
     */
    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }

        return $this;
    }

    /**
     * Remove a comment from post
     */
    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }

        return $this;
    }

    /**
     * Get number of comments on this post
     */
    #[Groups(['post:detail', 'post:list'])]
    public function getCommentsCount(): int
    {
        return $this->comments->count();
    }

    /**
     * Get post author
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * Get author username
     */
    #[Groups(['post:detail', 'post:list'])]
    public function getAuthorUsername(): ?string
    {
        return $this->author?->getUsername();
    }

    /**
     * Set post author
     */
    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get author email
     */
    public function getAuthorEmail(): ?string
    {
        return $this->author?->getEmail();
    }

    /**
     * String representation of post
     */
    public function __toString(): string
    {
        return $this->title ?? 'New Post';
    }
}
