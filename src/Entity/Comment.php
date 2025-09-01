<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'comment')]
#[ORM\Index(name: 'comment_created_at_index', columns: ['created_at'])]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comment:read', 'comment:list'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Content cannot be blank')]
    #[Assert\Length(min: 5, minMessage: 'Content must be at least 5 characters')]
    #[Groups(['comment:read', 'comment:list', 'comment:write'])]
    private ?string $content = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Author name cannot be blank')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Author name must be at least 2 characters',
        maxMessage: 'Author name cannot be longer than 100 characters')]
    #[Groups(['comment:read', 'comment:list', 'comment:write'])]
    private ?string $authorName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Author email cannot be blank')]
    #[Assert\Email(message: 'Please enter a valid email address')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Email cannot be longer than 100 characters')]
    #[Groups(['comment:read', 'comment:list', 'comment:write'])]
    private ?string $authorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['comment:read', 'comment:list'])]
    private ?\DateTimeInterface  $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Post is required')]
    private ?Post $post = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = trim($content);

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(string $authorEmail): static
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface  $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }
}
