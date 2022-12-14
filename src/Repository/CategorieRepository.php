<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Categorie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categorie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categorie[]    findAll()
 * @method Categorie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieRepository extends ServiceEntityRepository
{
    use TableInfoTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    public function countAll($searchValue = null)
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        $sql = <<<SQL
SELECT COUNT(t.id),t.libelle as libelle

FROM categorie as t
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
t.libelle as libelle
FROM categorie t
WHERE 1 = 1
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

    public function affiche_produit($id)
    {
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = "
            SELECT *
            FROM produit p
            INNER JOIN image as i on i.produit_id=p.id
            WHERE p.`categorie_id`=:id 
           
            ";
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery(array('id' => $id));
        return $stmt->executeStatement();
    }

    public function affiche_produit_One($id)
    {
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = "
            SELECT *
            FROM produit p
            INNER JOIN image as i on i.produit_id=p.id
            WHERE p.id=:id 
           
            ";
        $stmt = $conn->prepare($sql);
        $stmt->executeQuery(array('id' => $id));
        return $stmt->executeStatement();
    }

    public function listeCategorie()
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.active = :val')
            ->setParameter('val', 1)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }
    // /**
    //  * @return Categorie[] Returns an array of Categorie objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Categorie
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
