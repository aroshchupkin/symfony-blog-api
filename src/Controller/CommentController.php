<?php

namespace App\Controller;

use App\Contract\CommentServiceInterface;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\CommentNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Comment Controller
 */
#[Route('/api/posts/{postId}/comments')]
#[OA\Tag(name: 'Comments')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly SerializerInterface     $serializer
    )
    {
    }

    #[Route('', name: 'comments_index', requirements: ['postId' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{postId}/comments',
        description: 'Return paginated list of comments for a specific post',
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdPathParameter'),
            new OA\Parameter(ref: '#/components/parameters/PageParameter'),
            new OA\Parameter(ref: '#/components/parameters/LimitParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/CommentListSuccess', response: 200),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function index(int $postId, Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, max(1, (int)$request->query->get('limit', 10)));

        try {
            $result = $this->commentService->getCommentsForPost($postId, $page, $limit);
            $data = $this->serializer->serialize($result, 'json', ['groups' => ['comment:list']]);

            return new JsonResponse($data, Response::HTTP_OK, [], true);
        } catch (PostNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'comments_show', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Return detailed information about a specific comment',
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdPathParameter'),
            new OA\Parameter(ref: '#/components/parameters/CommentIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/CommentSuccess', response: 200),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function show(int $postId, int $id): JsonResponse
    {
        try {
            $comment = $this->commentService->getCommentById($id);

            // Verify comment belongs to the specified post
            if ($comment->getPost()->getId() !== $postId) {
                return new JsonResponse([
                    'error' => 'Comment not found',
                    'code' => 'COMMENT_NOT_FOUND'
                ], Response::HTTP_NOT_FOUND);
            }

            $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:list']]);

            return new JsonResponse($data, Response::HTTP_OK, [], true);

        } catch (CommentNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'COMMENT_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'comments_create', requirements: ['postId' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/api/posts/{postId}/comments',
        description: 'Create a new comment for a specific post (authentication required)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/CommentRequest'),
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdPathParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/CommentCreated', response: 201),
            new OA\Response(ref: '#/components/responses/ValidationError', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function create(int $postId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $comment = $this->commentService->createComment($data, $postId, $user);
            $commentData = $this->commentService->serializeComment($comment);

            return new JsonResponse($commentData, Response::HTTP_CREATED);

        } catch (PostNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (InvalidInputException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_INPUT'
            ], Response::HTTP_BAD_REQUEST);

        } catch (ValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'comments_update', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Update an existing comment (only author can update)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/CommentRequest'),
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdPathParameter'),
            new OA\Parameter(ref: '#/components/parameters/CommentIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/CommentUpdated', response: 200),
            new OA\Response(ref: '#/components/responses/ValidationError', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function update(int $postId, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $comment = $this->commentService->updateComment($id, $data, $user);

            // Verify comment belongs to the specified post
            if ($comment->getPost()->getId() !== $postId) {
                return new JsonResponse([
                    'error' => 'Comment not found',
                    'code' => 'COMMENT_NOT_FOUND'
                ], Response::HTTP_NOT_FOUND);
            }

            $commentData = $this->commentService->serializeComment($comment);

            return new JsonResponse($commentData, Response::HTTP_OK);

        } catch (CommentNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'COMMENT_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (AccessDeniedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);

        } catch (InvalidInputException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_INPUT'
            ], Response::HTTP_BAD_REQUEST);

        } catch (ValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'details' => $e->getValidationErrors()
            ], Response::HTTP_BAD_REQUEST);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'comments_delete', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/posts/{postId}/comments/{id}',
        description: 'Delete a comment (only author can delete)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PostIdPathParameter'),
            new OA\Parameter(ref: '#/components/parameters/CommentIdParameter')
        ],
        responses: [
            new OA\Response(ref: '#/components/responses/CommentDeleted', response: 200),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ]
    )]
    public function delete(int $postId, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // First get the comment to verify it belongs to the post
            $comment = $this->commentService->getCommentById($id);

            if ($comment->getPost()->getId() !== $postId) {
                return new JsonResponse([
                    'error' => 'Comment not found',
                    'code' => 'COMMENT_NOT_FOUND'
                ], Response::HTTP_NOT_FOUND);
            }

            $this->commentService->deleteComment($id, $user);

            return new JsonResponse([
                'message' => 'Comment deleted successfully',
                'code' => 'COMMENT_DELETED'
            ], Response::HTTP_OK);

        } catch (CommentNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'COMMENT_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);

        } catch (AccessDeniedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);

        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
