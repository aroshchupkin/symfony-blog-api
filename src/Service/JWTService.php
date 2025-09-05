<?php

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JWTService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        private readonly TokenStorageInterface $tokenStorage
    )
    {
    }

    public function createToken(User $user): string
    {
        return $this->JWTTokenManager->create($user);
    }

    public function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    public function getUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user?->getId();
    }

    public function getUserEmail(): ?string
    {
        $user = $this->getCurrentUser();
        return $user?->getEmail();
    }

    public function getUserName(): ?string
    {
        $user = $this->getCurrentUser();
        return $user?->getUsername();
    }
}