<?php

namespace App\Service;

use App\Contract\CacheServiceInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Cache Service
 */
readonly class CacheService implements CacheServiceInterface
{
    private const POST_LIST_KEY = 'post_list_page_%d_limit_%d';
    private const POST_DETAIL_KEY = 'post_detail_%d';
    private const COMMENT_LIST_KEY = 'comments_post_%d_page_%d_limit_%d';
    private const COMMENT_DETAIL_KEY = 'comment_detail_%d';

    /**
     * Constructor
     *
     * @param CacheItemPoolInterface $cache
     * @param int $cacheTtlList
     * @param int $cacheTtlDetail
     * @param int $maxPagesToClear
     * @param int $limitStep
     * @param int $maxLimit
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtlList,
        private readonly int $cacheTtlDetail,
        private readonly int $maxPagesToClear,
        private readonly int $limitStep,
        private readonly int $maxLimit
    ) {
    }

    /**
     * Get cache TTL for list operations
     *
     * @return int
     */
    public function getListCacheTime(): int
    {
        return $this->cacheTtlList;
    }

    /**
     * Get cache TTL for detail operations
     *
     * @return int
     */
    public function getItemCacheTime(): int
    {
        return $this->cacheTtlDetail;
    }

    /**
     * Generate cache key for posts list
     *
     * @param int $page
     * @param int $limit
     * @return string
     */
    public function getPostsListCacheKey(int $page, int $limit): string
    {
        return sprintf(self::POST_LIST_KEY, $page, $limit);
    }

    /**
     * Generate cache key for post detail
     *
     * @param int $postId
     * @return string
     */
    public function getPostDetailCacheKey(int $postId): string
    {
        return sprintf(self::POST_DETAIL_KEY, $postId);
    }

    /**
     * Generate cache key for comments list
     *
     * @param int $postId
     * @param int $page
     * @param int $limit
     * @return string
     */
    public function getCommentsListCacheKey(int $postId, int $page, int $limit): string
    {
        return sprintf(self::COMMENT_LIST_KEY, $postId, $page, $limit);
    }

    /**
     * Generate cache key for comment detail
     *
     * @param int $commentId
     * @return string
     */
    public function getCommentDetailCacheKey(int $commentId): string
    {
        return sprintf(self::COMMENT_DETAIL_KEY, $commentId);
    }

    /**
     * Clear all posts list cache entries
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function clearPostsListCache(): void
    {
        for ($page = 1; $page <= $this->maxPagesToClear; $page++) {
            for ($limit = $this->limitStep; $limit <= $this->maxLimit; $limit += $this->limitStep) {
                $key = $this->getPostsListCacheKey($page, $limit);
                $this->cache->deleteItem($key);
            }
        }
    }

    /**
     * Clear specific post detail cache
     *
     * @param int $postId
     * @return void
     * @throws InvalidArgumentException
     */
    public function clearPostDetailCache(int $postId): void
    {
        $key = $this->getPostDetailCacheKey($postId);
        $this->cache->deleteItem($key);
    }

    /**
     * Clear all comments list cache for a specific post
     *
     * @param int $postId
     * @return void
     * @throws InvalidArgumentException
     */
    public function clearCommentsListCache(int $postId): void
    {
        for ($page = 1; $page <= $this->maxPagesToClear; $page++) {
            for ($limit = $this->limitStep; $limit <= $this->maxLimit; $limit += $this->limitStep) {
                $key = $this->getCommentsListCacheKey($postId, $page, $limit);
                $this->cache->deleteItem($key);
            }
        }
    }

    /**
     * Clear specific comment detail cache
     *
     * @param int $commentId
     * @return void
     * @throws InvalidArgumentException
     */
    public function clearCommentDetailCache(int $commentId): void
    {
        $key = $this->getCommentDetailCacheKey($commentId);
        $this->cache->deleteItem($key);
    }

    /**
     * Clear all cache related to a specific post
     *
     * @throws InvalidArgumentException
     */
    public function clearPostRelatedCache(int $postId): void
    {
        $this->clearPostDetailCache($postId);
        $this->clearPostsListCache();
        $this->clearCommentsListCache($postId);
    }

    /**
     * Clear all cache related to a specific comment
     *
     * @throws InvalidArgumentException
     */
    public function clearCommentRelatedCache(int $commentId, int $postId): void
    {
        $this->clearCommentDetailCache($commentId);
        $this->clearCommentsListCache($postId);
    }

    /**
     * Get the cache pool instance
     *
     * @return CacheItemPoolInterface
     */
    public function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }
}