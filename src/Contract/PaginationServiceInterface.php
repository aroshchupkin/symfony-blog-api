<?php

namespace App\Contract;

use Symfony\Component\HttpFoundation\Request;

/**
 * Pagination Service Interface
 */
interface PaginationServiceInterface
{
    /**
     * Validate pagination parameters from request
     *
     * @param Request $request
     * @return array
     */
    public function validatePagination(Request $request): array;

    /**
     * Get default page number
     *
     * @return int
     */
    public function getDefaultPage(): int;

    /**
     * Get default limit per page
     *
     * @return int
     */
    public function getDefaultLimit(): int;

    /**
     * Get min limit per page
     *
     * @return int
     */
    public function getMinLimit(): int;

    /**
     * Get max limit per page
     *
     * @return int
     */
    public function getMaxLimit(): int;
}