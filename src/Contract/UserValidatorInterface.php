<?php

namespace App\Contract;

use App\Entity\User;

/**
 * User Validator Interface
 */
interface UserValidatorInterface
{
    /**
     * Validate registration data
     *
     * @param array|null $data
     * @return void
     */
    public function validateRegistrationData(?array $data): void;

    /**
     * Check if email already exists
     *
     * @param string $email
     * @return void
     */
    public function checkEmailExists(string $email): void;

    /**
     * Validate user entity
     *
     * @param User $user
     * @return void
     */
    public function validateUser(User $user): void;
}