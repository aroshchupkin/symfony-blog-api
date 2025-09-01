<?php

namespace App\Service;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
class CacheService
{
    private const CACHE_LIST_TIME = 300;
    private const CACHE_DETAIL_TIME = 600;

    private const POST_LIST_KEY = 'post_list_page_%d_limit_%d';
    private const POST_DETAIL_KEY = 'post_detail_%d';
    private const COMMENT_LIST_KEY = 'comments_post_%d_page_%d_limit_%d';
    private const COMMENT_DETAIL_KEY = 'comment_detail_%d';

    private const MAX_PAGES_TO_CLEAR = 10;
    private const LIMIT_STEP = 10;
    private const MAX_LIMIT = 100;

    private function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getListCacheTime(): int
    {
        return self::CACHE_LIST_TIME;
    }

    public function getItemCacheTime(): int
    {
        return self::CACHE_DETAIL_TIME;
    }

    public function getPostsListCacheKey(int $page, int $limit): string
    {
        return sprintf(self::POST_LIST_KEY, $page, $limit);
    }

    public function getPostDetailCacheKey(int $postId): string
    {
        return sprintf(self::POST_DETAIL_KEY, $postId);
    }

    public function getCommentsListCacheKey(int $postId, int $page, int $limit): string
    {
        return sprintf(self::COMMENT_LIST_KEY, $postId, $page, $limit);
    }

    public function getCommentDetailCacheKey(int $commentId): string
    {
        return sprintf(self::COMMENT_DETAIL_KEY, $commentId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearPostsListCache(): void
    {
        for ($page = 1; $page <= self::MAX_PAGES_TO_CLEAR; $page++) {
            for ($limit = self::LIMIT_STEP; $limit <= self::MAX_LIMIT; $limit += self::LIMIT_STEP) {
                $key = $this->getPostsListCacheKey($page, $limit);
                $this->cache->delete($key);
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearPostDetailCache(int $postId): void
    {
        $key = $this->getPostDetailCacheKey($postId);
        $this->cache->delete($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearCommentsListCache(int $postId): void
    {
        for ($page = 1; $page <= self::MAX_PAGES_TO_CLEAR; $page++) {
            for ($limit = self::LIMIT_STEP; $limit <= self::MAX_LIMIT; $limit += self::LIMIT_STEP) {
                $key = $this->getCommentsListCacheKey($postId, $page, $limit);
                $this->cache->delete($key);
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearCommentDetailCache(int $commentId): void
    {
        $key = $this->getCommentDetailCacheKey($commentId);
        $this->cache->delete($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearPostRelatedCache(int $postId): void
    {
        $this->clearPostDetailCache($postId);
        $this->clearPostsListCache();
        $this->clearCommentsListCache($postId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearCommentRelatedCache(int $commentId, int $postId): void
    {
        $this->clearCommentDetailCache($commentId);
        $this->clearCommentsListCache($postId);
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}