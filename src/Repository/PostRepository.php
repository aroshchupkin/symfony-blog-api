<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Post Repository
 *
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    /**
     * Constructor
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Find posts with pagination and eager loading of comments
     *
     * @param int $page
     * @param int $limit
     * @return Post[]
     */
    public function findAllWithPagination(int $page = 1, int $limit = 10): array
    {
        $query = $this->createQueryBuilder('post')
            ->leftJoin('post.comments', 'comments')
            ->addSelect('comments')
            ->orderBy('post.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $query->getQuery()->getResult();
    }

    /**
     * Count total number of posts
     *
     * @return int
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('post')
            ->select('count(post.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Save post entity to database
     *
     * @param Post $post
     * @param bool $flush
     * @return void
     */
    public function save(Post $post, bool $flush = false): void
    {
        $this->getEntityManager()->persist($post);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove post entity from database
     *
     * @param Post $post
     * @param bool $flush
     * @return void
     */
    public function remove(Post $post, bool $flush = false): void
    {
        $this->getEntityManager()->remove($post);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
