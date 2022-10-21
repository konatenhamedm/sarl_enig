<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Produit|null find($id, $lockMode = null, $lockVersion = null)
 * @method Produit|null findOneBy(array $criteria, array $orderBy = null)
 * @method Produit[]    findAll()
 * @method Produit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitRepository extends ServiceEntityRepository
{
    use TableInfoTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    public function countAll($searchValue = null)
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        $sql = <<<SQL
SELECT COUNT(id),t.libelle
FROM produit as t
WHERE  1 = 1
SQL;
        $params = [];

        $sql .= $this->getSearchColumns($searchValue, $params, ['libelle']);



        $stmt = $connection->executeQuery($sql, $params);


        return intval($stmt->fetchOne());
    }

    public function getAll($limit, $offset, $searchValue = null)
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        $sql = <<<SQL
SELECT
t.id,
t.libelle as libelle,t.date_ajout ,c.libelle as categorie
FROM produit t,categorie  c
WHERE t.categorie_id = c.id and  1 = 1
SQL;
        $params = [];

        $sql .= $this->getSearchColumns($searchValue, $params, ['libelle']);

        $sql .= ' ORDER BY libelle';

        if ($limit && $offset == null) {
            $sql .= " LIMIT {$limit}";
        } else if ($limit && $offset) {
            $sql .= " LIMIT {$offset},{$limit}";
        }



        $stmt = $connection->executeQuery($sql, $params);
        return $stmt->fetchAllAssociative();
    }

    public function affiche_produit_all($categorie)
    {

        return $this->createQueryBuilder('p')
            /*->select('p.libelle as titre','p.id','p.date_ajout','c.libelle')*/
            ->andWhere('p.active = 1')
            ->innerJoin('p.categorie','c')
            ->andWhere('c.active = 1')
            ->andWhere('p.categorie = :val')
            ->setParameter('val', $categorie)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;

       /* $conn = $this->getEntityManager()
            ->getConnection();
        $sql = "
            SELECT p.libelle as titre,p.id,p.date_ajout,c.libelle
            FROM produit p
            left JOIN categorie as c on p.categorie_id=c.id
           
            ";
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery();
        return $stmt->executeStatement();*/
    }
    // /**
    //  * @return Produit[] Returns an array of Produit objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Produit
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
