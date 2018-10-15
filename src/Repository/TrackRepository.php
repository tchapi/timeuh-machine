<?php

namespace App\Repository;

use App\Entity\Track;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class TrackRepository extends EntityRepository
{
    public function findCurrentlyPlayingTrack()
    {
        $limit = new \Datetime('now - 30 minutes');

        return $this->createQueryBuilder('t')
               ->where('t.startedAt > :limit')
               ->setParameter('limit', $limit)
               ->andWhere('t.valid = 1')
               ->orderBy('t.startedAt', 'DESC')
               ->getQuery()
               ->setMaxResults(1)
               ->getOneOrNullResult();
    }

    public function findNLastTracksExceptCurrentOnPage(int $max, ?Track $current, int $page)
    {
        $q = $this->createQueryBuilder('t')
                  ->where('t.valid = 1');

        if ($current) {
            $q->andWhere('t.id != :currentId')
            ->setParameter('currentId', $current->getId());
        }

        return $q->setFirstResult(($page - 1) * $max)
                 ->setMaxResults($max)
                 ->orderBy('t.startedAt', 'DESC')
                 ->getQuery()
                 ->getResult();
    }

    public function findLatestByYears(array $years)
    {
        // All this thanks to https://www.wanadev.fr/56-comment-realiser-de-belles-requetes-sql-avec-doctrine/
        $table = $this->getClassMetadata()->table['name'];

        // Create UNION query
        $select = 'SELECT t.*, YEAR(t.started_at) as year_n FROM '.$table.' AS t';
        $where = "WHERE t.valid = 1 AND t.image != '' AND YEAR(t.started_at) = :year";
        $orderBy = 'LIMIT 16';

        $queries = [];
        foreach ($years as $year) {
            $queries[] = '('.str_replace(':year', $year, $select.' '.$where.' '.$orderBy).')';
        }

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addEntityResult(\App\Entity\Track::class, 't');
        foreach ($this->getClassMetadata()->fieldMappings as $obj) {
            $rsm->addFieldResult('t', $obj['columnName'], $obj['fieldName']);
        }
        $rsm->addScalarResult('year_n', 'year_n');

        return $this->getEntityManager()->createNativeQuery(implode(' UNION ALL ', $queries), $rsm)->getResult();
    }

    public function findLatestByMonths(int $year)
    {
        $table = $this->getClassMetadata()->table['name'];

        // Create UNION query
        $select = 'SELECT t.*, MONTH(t.started_at) as month_n FROM '.$table.' AS t';
        $where = "WHERE t.valid = 1 AND t.image != '' AND YEAR(t.started_at) = ".$year.' AND MONTH(t.started_at) = :month';
        $orderBy = 'LIMIT 16';

        $queries = [];
        foreach (range(1, 12) as $month) {
            $queries[] = '('.str_replace(':month', $month, $select.' '.$where.' '.$orderBy).')';
        }

        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addEntityResult(\App\Entity\Track::class, 't');
        foreach ($this->getClassMetadata()->fieldMappings as $obj) {
            $rsm->addFieldResult('t', $obj['columnName'], $obj['fieldName']);
        }
        $rsm->addScalarResult('month_n', 'month_n');

        return $this->getEntityManager()->createNativeQuery(implode(' UNION ALL ', $queries), $rsm)->getResult();
    }

    public function findByMonth($year, $month)
    {
        return $this->createQueryBuilder('t')
             ->select('t.startedAt, t.image, t.title, t.album, t.artist, DAY(t.startedAt) as day_n')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('t.valid = 1')
             ->andWhere("t.image != ''")
             ->getQuery()
             ->getResult();
    }

    public function findByDay($year, $month, $day)
    {
        return $this->createQueryBuilder('t')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('DAY(t.startedAt) = :day')
             ->setParameter('day', $day)
             ->andWhere('t.valid = 1')
             ->orderBy('t.startedAt', 'DESC')
             ->andWhere("t.image != ''")
             ->getQuery()
             ->getResult();
    }

    public function findSpotifyLinksForMonth($year, $month)
    {
        return $this->createQueryBuilder('t')
             ->select('t.spotifyLink')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('t.valid = 1')
             ->andWhere('t.spotifyLink IS NOT NULL')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }

    public function findSpotifyLinksForDay($year, $month, $day)
    {
        return $this->createQueryBuilder('t')
             ->select('t.spotifyLink')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('DAY(t.startedAt) = :day')
             ->setParameter('day', $day)
             ->andWhere('t.valid = 1')
             ->andWhere('t.spotifyLink IS NOT NULL')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }
}
