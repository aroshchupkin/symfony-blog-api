<?php

namespace App\Service;

use App\Contract\PaginationServiceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pagination Service
 */
readonly class PaginationService implements PaginationServiceInterface
{
    public function __construct(
        private int $defaultPage,
        private int $defaultLimit,
        private int $minLimit,
        private int $maxLimit
    ) {
    }

    /**
     * Validate pagination parameters from request
     *
     * @param Request $request
     * @return array
     */
    public function validatePagination(Request $request): array
    {
        $page = max($this->minLimit, (int)$request->query->get('page', $this->defaultPage));
        $limit = min($this->maxLimit, max($this->minLimit, (int)$request->query->get('limit', $this->defaultLimit)));

        return [
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Get default page number
     *
     * @return int
     */
    public function getDefaultPage(): int
    {
        return $this->defaultPage;
    }

    /**
     * Get default limit per page
     *
     * @return int
     */
    public function getDefaultLimit(): int
    {
        return $this->defaultLimit;
    }

    /**
     * Get min limit per page
     *
     * @return int
     */
    public function getMinLimit(): int
    {
        return $this->minLimit;
    }

    /**
     * Get max limit per page
     *
     * @return int
     */
    public function getMaxLimit(): int
    {
        return $this->maxLimit;
    }
}