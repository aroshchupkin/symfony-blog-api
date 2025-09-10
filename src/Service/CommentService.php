<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Exception\CommentNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use App\Exception\AccessDeniedException;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CommentService
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly PostRepository $postRepository,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly CacheService $cacheService
    ) {
    }

    /**
     * Get comments for a specific post with pagination
     *
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function getCommentsForPost(int $postId, int $page, int $limit): array
    {
        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw new PostNotFoundException("Post with ID {$postId} not found");
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
            throw new CommentNotFoundException("Comment with ID {$id} not found");
        }

        return $comment;
    }

    /**
     * Create a new comment
     *
     * @throws InvalidInputException
     * @throws PostNotFoundException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function createComment(array $data, int $postId, User $user): Comment
    {
        if (!$data) {
            throw new InvalidInputException('Invalid JSON body');
        }

        $post = $this->postRepository->find($postId);
        if (!$post) {
            throw new PostNotFoundException("Post with ID {$postId} not found");
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($user);

        if (isset($data['content'])) {
            $comment->setContent(trim($data['content']));
        }

        $this->validateComment($comment);

        $this->commentRepository->save($comment, true);
        $this->clearCommentCaches($postId, $comment->getId());

        return $comment;
    }

    /**
     * Update an existing comment
     *
     * @throws CommentNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidInputException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function updateComment(int $id, array $data, User $user): Comment
    {
        if (!$data) {
            throw new InvalidInputException('Invalid JSON body');
        }

        $comment = $this->getCommentById($id);

        $this->checkCommentOwnership($comment, $user);

        if (isset($data['content'])) {
            $comment->setContent(trim($data['content']));
        }

        $this->validateComment($comment);

        $this->commentRepository->save($comment, true);
        $this->clearCommentCaches($comment->getPost()->getId(), $comment->getId());

        return $comment;
    }

    /**
     * Delete a comment
     *
     * @throws CommentNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    public function deleteComment(int $id, User $user): void
    {
        $comment = $this->getCommentById($id);

        $this->checkCommentOwnership($comment, $user);

        $postId = $comment->getPost()->getId();

        $this->commentRepository->remove($comment, true);
        $this->clearCommentCaches($postId, $id);
    }

    /**
     * Serialize comment data
     */
    public function serializeComment(Comment $comment): array
    {
        $serializedData = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:read']]);
        return json_decode($serializedData, true);
    }

    /**
     * Validate comment entity
     *
     * @throws ValidationException
     */
    private function validateComment(Comment $comment): void
    {
        $errors = $this->validator->validate($comment);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new ValidationException('Validation failed', $errorMessages);
        }
    }

    /**
     * Check if user owns the comment
     *
     * @throws AccessDeniedException
     */
    private function checkCommentOwnership(Comment $comment, User $user): void
    {
        if ($comment->getAuthor() !== $user) {
            throw new AccessDeniedException('Access denied. You can only modify your own comments');
        }
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

        // Also clear post detail cache since it includes comments
        $this->cacheService->clearPostDetailCache($postId);
    }
}