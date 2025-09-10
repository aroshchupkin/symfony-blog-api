<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Comment Repository
 *
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    /**
     * Comment Repository
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Find comments for a specific post with pagination
     *
     * @param Post $post
     * @param int $page
     * @param int $limit
     * @return Comment[]
     */
    public function findByPostWithPagination(Post $post, int $page = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('comment')
            ->andWhere('comment.post = :post')
            ->setParameter('post', $post)
            ->orderBy('comment.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total number of comments for a specific post
     *
     * @param Post $post
     * @return int
     */
    public function countByPost(Post $post): int
    {
        return $this->createQueryBuilder('comment')
            ->select('count(comment.id)')
            ->andWhere('comment.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Save comment entity to database
     *
     * @param Comment $comment
     * @param bool $flush
     * @return void
     */
    public function save(Comment $comment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($comment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove comment entity from database
     *
     * @param Comment $comment
     * @param bool $flush
     * @return void
     */
    public function remove(Comment $comment, bool $flush = false): void
    {
        $this->getEntityManager()->remove($comment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
