<?php

namespace MainBundle\Repository;

use MainBundle\Entity\Track;
use Doctrine\ORM\EntityRepository;

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

        return $q->setFirstResult(($page-1)*$max)
                 ->setMaxResults($max)
                 ->orderBy('t.startedAt', 'DESC')
                 ->getQuery()
                 ->getResult();
    }

    public function findByYear($year)
    {
        return $this->createQueryBuilder('t')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('t.valid = 1')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }

    public function findByMonth($year, $month)
    {
        return $this->createQueryBuilder('t')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('t.valid = 1')
             ->orderBy('t.startedAt', 'DESC')
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
             ->getQuery()
             ->getResult();
    }

    public function findSpotifyIdsForMonth($year,$month)
    {
        return $this->createQueryBuilder('t')
             ->select('t.spotifyTrackId')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('t.valid = 1')
             ->andWhere('t.spotifyTrackId IS NOT NULL')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }

    public function findSpotifyIdsForDay($year,$month, $day)
    {
        return $this->createQueryBuilder('t')
             ->select('t.spotifyTrackId')
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('DAY(t.startedAt) = :day')
             ->setParameter('day', $day)
             ->andWhere('t.valid = 1')
             ->andWhere('t.spotifyTrackId IS NOT NULL')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }

}
