<?php

namespace App\Service;

use App\Contract\UserValidatorInterface;
use App\Entity\User;
use App\Exception\EmailAlreadyExistsException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * User Validator
 */
readonly class UserValidator implements UserValidatorInterface
{
    /**
     * Constructor
     *
     * @param ValidatorInterface $validator
     * @param UserRepository $userRepository
     */
    public function __construct(
        private ValidatorInterface $validator,
        private UserRepository $userRepository
    ) {
    }

    /**
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
     * @throws EmailAlreadyExistsException
     */
    public function checkEmailExists(string $email): void
    {
        if ($this->userRepository->emailExists($email)) {
            throw new EmailAlreadyExistsException("Email {$email} already exists");
        }
    }

    /**
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
}