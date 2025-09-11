<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User Entity
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'user_email_index', columns: ['email'])]
#[ORM\Index(name: 'user_username_index', columns: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Unique identifier for the user
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    /**
     * Unique username for the user
     */
    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Username cannot be blank.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "Username must be at least 3 characters long.",
        maxMessage: "Username cannot be longer than 50 characters."
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'Username can only contain letters, numbers, and underscores.'
    )]
    #[Groups(['user:read', 'user:write', 'post:read', 'comment:read', 'comment:list'])]
    private ?string $username = null;

    /**
     * Unique email address for the user
     */
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 180, maxMessage: "Email cannot be longer than 180 characters.")]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    /**
     * Hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Plain password used during registration
     */
    #[Assert\NotBlank(message: 'Password cannot be blank.')]
    #[Assert\Length(
        min: 6,
        minMessage: "Password must be at least 6 characters long."
    )]
    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    /**
     * Timestamp when user account was created
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * User roles for authorization
     *
     * @var list<string> The user roles
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * Collection of posts authored by this user
     *
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $posts;

    /**
     * Collection of comments authored by this user
     *
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $comments;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    /**
     * Get user ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get username
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Set username
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get email address
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set email address
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get hashed password
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set hashed password
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get plain password
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * Set plain password
     */
    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set creation timestamp
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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
     * Get user roles
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Set user roles
     *
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Erase credentials
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /**
     * Get user identifier (email as login)
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Get collection of user's posts
     *
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    /**
     * Add a post to user's collection
     */
    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setAuthor($this);
        }

        return $this;
    }

    /**
     * Remove a post from user's collection
     */
    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getAuthor() === $this) {
                $post->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * Get collection of user's comments
     *
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Add a comment to user's collection
     */
    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }

        return $this;
    }

    /**
     * Remove a comment from user's collection
     */
    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getAuthor() === $this) {
                $comment->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * String representation of user (for debugging)
     */
    public function __toString(): string
    {
        return $this->username ?? 'New User';
    }
}
