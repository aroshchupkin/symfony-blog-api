<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\CommentNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use App\Service\CommentService;
use Nelmio\ApiDocBundle\Attribute\Model;
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
        private readonly CommentService $commentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'comments_index', requirements: ['postId' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/posts/{postId}/comments',
        description: 'Return paginated list of comments for a specific post',
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10, maximum: 100, minimum: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comments list with pagination',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'comments',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Comment::class, groups: ['comment:list']))
                        ),
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'total_pages', type: 'integer'),
                                new OA\Property(property: 'total_items', type: 'integer'),
                                new OA\Property(property: 'items_per_page', type: 'integer'),
                                new OA\Property(property: 'has_next_page', type: 'boolean'),
                                new OA\Property(property: 'has_previous_page', type: 'boolean')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Post not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_NOT_FOUND')
                    ]
                )
            )
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
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment details',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:list']))
            ),
            new OA\Response(
                response: 404,
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
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
        requestBody: new OA\RequestBody(
            description: 'Comment data',
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'This is a great post!'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comment created successfully',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:read']))
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
                        new OA\Property(property: 'details', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Post not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'POST_NOT_FOUND')
                    ]
                )
            )
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
        requestBody: new OA\RequestBody(
            description: 'Updated comment data',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'Updated comment content'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment updated successfully',
                content: new OA\JsonContent(ref: new Model(type: Comment::class, groups: ['comment:read']))
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Access denied. You can only update your own posts'),
                        new OA\Property(property: 'code', type: 'string', example: 'ACCESS_DENIED')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
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
            new OA\Parameter(
                name: 'postId',
                description: 'Post ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'id',
                description: 'Comment ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Comment deleted successfully'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_DELETED')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not authenticated'),
                        new OA\Property(property: 'code', type: 'string', example: 'USER_NOT_AUTHENTICATED')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Access denied. You can only delete your own posts'),
                        new OA\Property(property: 'code', type: 'string', example: 'ACCESS_DENIED')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Post or comment not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Comment not found'),
                        new OA\Property(property: 'code', type: 'string', example: 'COMMENT_NOT_FOUND')
                    ]
                )
            )
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
