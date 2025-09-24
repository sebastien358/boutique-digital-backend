<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findLoadProducts(int $offset, int $limit) {
        return $this->createQueryBuilder('p')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySearch($filterSearch)
    {
        $qb = $this->createQueryBuilder('p');
        if (isset($filterSearch['search'])) {
            $qb->andWhere('p.title LIKE :search');
            $qb->setParameter('search', '%' . $filterSearch['search'] . '%');
            return $qb->getQuery()->getResult();
        }
    }

    public function findByPrice(int $minPrice, int $maxPrice)
    {
        return $this->createQueryBuilder('p')
        ->andWhere('p.price BETWEEN :minPrice AND :maxPrice')
        ->setParameter('minPrice', $minPrice)
        ->setParameter('maxPrice', $maxPrice)
        ->getQuery()
        ->getResult();
    }

    public function findByCategory(string $category)
    {
        return $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->where('c.name = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }

    public function findAllProducts(int $page, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAllProducts() {
        return $this->createQueryBuilder('p')
        ->select('count(p)')
        ->getQuery()
        ->getSingleScalarResult();
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
