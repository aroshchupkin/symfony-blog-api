<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\EmailAlreadyExistsException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * User Service
 */
readonly class UserService
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface          $validator,
        private SerializerInterface         $serializer,
        private JWTTokenManagerInterface    $JWTTokenManager
    )
    {
    }

    /**
     * Validate registration data format and required fields
     *
     * @throws InvalidInputException
     */
    public function validateRegistrationData(?array $data): void
    {
        if (!$data) {
            throw new InvalidInputException('Invalid JSON body');
        }

        $requiredFields = ['username', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new InvalidInputException("Field {$field} is required");
            }
        }
    }

    /**
     * Check if email already exists
     *
     * @throws EmailAlreadyExistsException
     */
    public function checkEmailExists(string $email): void
    {
        if ($this->userRepository->emailExists($email)) {
            throw new EmailAlreadyExistsException("Email {$email} already exists");
        }
    }


    /**
     * Create user entity from registration data
     */
    public function createUserFromData(array $data): User
    {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPlainPassword($data['password']);

        return $user;
    }

    /**
     * Validate user entity using Symfony validator
     *
     * @throws ValidationException
     */
    public function validateUser(User $user): void
    {
        $errors = $this->validator->validate($user);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new ValidationException('Validation failed', $errorMessages);
        }
    }

    /**
     * Hash user password and clear plain password
     */
    public function hashUserPassword(User $user): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
        $user->eraseCredentials();
    }

    /**
     * Save user to database
     */
    public function saveUser(User $user): void
    {
        $this->userRepository->save($user, true);
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
        $userData = $this->serializer->serialize($user, 'json', ['groups' => 'user:read']);

        return json_decode($userData, true);
    }

    /**
     * Complete user registration process
     *
     * @throws ValidationException
     * @throws InvalidInputException
     * @throws EmailAlreadyExistsException
     */
    public function registerUser(?array $data): User
    {
        $this->validateRegistrationData($data);

        $this->checkEmailExists($data['email']);

        $user = $this->createUserFromData($data);

        $this->validateUser($user);

        $this->hashUserPassword($user);

        $this->saveUser($user);

        return $user;
    }
}