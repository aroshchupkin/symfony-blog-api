<?php

namespace App\Service;

use App\Contract\SerializerServiceInterface;
use App\Contract\UserServiceInterface;
use App\Contract\UserValidatorInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * User Service
 */
readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UserValidatorInterface      $userValidator,
        private SerializerServiceInterface  $serializerService,
        private JWTTokenManagerInterface    $JWTTokenManager
    )
    {
    }

    /**
     * Register a new user
     */
    public function registerUser(?array $data): User
    {
        $this->userValidator->validateRegistrationData($data);
        $this->userValidator->checkEmailExists($data['email']);

        $user = $this->createUserFromData($data);
        $this->userValidator->validateUser($user);
        $this->hashUserPassword($user);
        $this->saveUser($user);

        return $user;
    }

    /**
     * Generate JWT token for user
     */
    public function generateToken(User $user): string
    {
        return $this->JWTTokenManager->create($user);
    }

    /**
     * Serialize user data for response
     */
    public function serializeUser(User $user): array
    {
        return $this->serializerService->serializeUser($user);
    }

    /**
     * Create user entity from registration data
     */
    private function createUserFromData(array $data): User
    {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPlainPassword($data['password']);

        return $user;
    }

    /**
     * Hash user password and clear plain password
     */
    private function hashUserPassword(User $user): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();
    }

    /**
     * Save user to database
     */
    private function saveUser(User $user): void
    {
        $this->userRepository->save($user, true);
    }
}