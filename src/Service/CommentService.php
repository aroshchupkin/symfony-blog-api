<?php

namespace App\Service;

use App\Contract\CommentServiceInterface;
use App\Contract\CommentValidatorInterface;
use App\Contract\SerializerServiceInterface;
use App\Entity\Comment;
use App\Entity\User;
use App\Exception\CommentNotFoundException;
use App\Exception\PostNotFoundException;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Comment Service
 */
readonly class CommentService implements CommentServiceInterface
{
    public function __construct(
        private CommentRepository          $commentRepository,
        private PostRepository             $postRepository,
        private CommentValidatorInterface  $commentValidator,
        private SerializerServiceInterface $serializerService,
        private CacheService               $cacheService
    )
    {
    }

    /**
     * Get comments for a specific post with pagination
     *
     * @param int $postId
     * @param int $page
     * @param int $limit
     * @return array
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function getCommentsForPost(int $postId, int $page, int $limit): array
    {
        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw new PostNotFoundException("Post with ID $postId not found");
        }

        $cacheKey = $this->cacheService->getCommentsListCacheKey($postId, $page, $limit);

        return $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($post, $page, $limit) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            $comments = $this->commentRepository->findByPostWithPagination($post, $page, $limit);
            $total = $this->commentRepository->countByPost($post);
            $totalPages = (int)ceil($total / $limit);

            return [
                'comments' => $comments,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1,
                ]
            ];
        });
    }

    /**
     * Get a single comment by ID
     *
     * @param int $id
     * @return Comment
     * @throws CommentNotFoundException
     * @throws InvalidArgumentException
     */
    public function getCommentById(int $id): Comment
    {
        $cacheKey = $this->cacheService->getCommentDetailCacheKey($id);

        $comment = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->commentRepository->find($id);
        });

        if (!$comment) {
            throw new CommentNotFoundException("Comment with ID $id not found");
        }

        return $comment;
    }

    /**
     * Create a new comment
     *
     * @param array|null $data
     * @param int $postId
     * @param User $user
     * @return Comment
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function createComment(?array $data, int $postId, User $user): Comment
    {
        $this->commentValidator->validateCommentData($data);

        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw new PostNotFoundException("Post with ID $postId not found");
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($user);
        $comment->setContent(trim($data['content']));

        $this->commentValidator->validateComment($comment);

        $this->commentRepository->save($comment, true);
        $this->clearCommentCaches($postId, $comment->getId());

        return $comment;
    }

    /**
     * Update an existing comment
     *
     * @param int $id
     * @param array|null $data
     * @param User $user
     * @return Comment
     * @throws CommentNotFoundException
     * @throws InvalidArgumentException
     */
    public function updateComment(int $id, ?array $data, User $user): Comment
    {
        $this->commentValidator->validateCommentData($data);
        $comment = $this->getCommentById($id);
        $this->commentValidator->checkCommentOwnership($comment, $user);

        $comment->setContent(trim($data['content']));
        $this->commentValidator->validateComment($comment);

        $this->commentRepository->save($comment, true);
        $this->clearCommentCaches($comment->getPost()->getId(), $comment->getId());

        return $comment;
    }

    /**
     * Delete a comment
     *
     * @param int $id
     * @param User $user
     * @return void
     * @throws CommentNotFoundException
     * @throws InvalidArgumentException
     */
    public function deleteComment(int $id, User $user): void
    {
        $comment = $this->getCommentById($id);

        $this->commentValidator->checkCommentOwnership($comment, $user);

        $postId = $comment->getPost()->getId();

        $this->commentRepository->remove($comment, true);
        $this->clearCommentCaches($postId, $id);
    }

    /**
     * Serialize comment data
     *
     * @param Comment $comment
     * @return array
     */
    public function serializeComment(Comment $comment): array
    {
        return $this->serializerService->serializeComment($comment);
    }

    /**
     * Clear comment-related caches
     *
     * @throws InvalidArgumentException
     */
    private function clearCommentCaches(int $postId, ?int $commentId = null): void
    {
        $this->cacheService->clearCommentsListCache($postId);

        if ($commentId) {
            $this->cacheService->clearCommentDetailCache($commentId);
        }

        $this->cacheService->clearPostDetailCache($postId);
    }
}