<?php

namespace App\Contract;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache Service Interface
 */
interface CacheServiceInterface
{
    /**
     * Get cache TTL for list operations
     *
     * @return int
     */
    public function getListCacheTime(): int;

    /**
     * Get cache TTL for detail operations
     *
     * @return int
     */
    public function getItemCacheTime(): int;

    /**
     * Generate cache key for posts list
     *
     * @param int $page
     * @param int $limit
     * @return string
     */
    public function getPostsListCacheKey(int $page, int $limit): string;

    /**
     * Generate cache key for post detail
     *
     * @param int $postId
     * @return string
     */
    public function getPostDetailCacheKey(int $postId): string;

    /**
     * Generate cache key for comments list
     *
     * @param int $postId
     * @param int $page
     * @param int $limit
     * @return string
     */
    public function getCommentsListCacheKey(int $postId, int $page, int $limit): string;

    /**
     * Generate cache key for comment detail
     *
     * @param int $commentId
     * @return string
     */
    public function getCommentDetailCacheKey(int $commentId): string;

    /**
     * Clear all posts list cache entries
     *
     * @return void
     */
    public function clearPostsListCache(): void;

    /**
     * Clear specific post detail cache
     *
     * @param int $postId
     * @return void
     */
    public function clearPostDetailCache(int $postId): void;

    /**
     * Clear all comments list cache for a specific post
     *
     * @param int $postId
     * @return void
     */
    public function clearCommentsListCache(int $postId): void;

    /**
     * Clear specific comment detail cache
     *
     * @param int $commentId
     * @return void
     */
    public function clearCommentDetailCache(int $commentId): void;

    /**
     * Get the cache pool instance
     *
     * @return CacheItemPoolInterface
     */
    public function getCache(): CacheItemPoolInterface;
}