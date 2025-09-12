<?php

namespace App\Contract;

use App\Entity\User;
use App\Exception\EmailAlreadyExistsException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;

/**
 * User Service Interface
 */
interface UserServiceInterface
{
    /**
     * Register a new user
     *
     * @param array|null $data
     * @return User
     * @throws InvalidInputException
     * @throws EmailAlreadyExistsException
     * @throws ValidationException
     */
    public function registerUser(?array $data): User;

    /**
     * Generate JWT token for user
     *
     * @param User $user
     * @return string
     */
    public function generateToken(User $user): string;

    /**
     * Serialize user data for response
     *
     * @param User $user
     * @return array
     */
    public function serializeUser(User $user): array;
}