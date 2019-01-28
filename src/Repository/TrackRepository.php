<?php

namespace App\Repository;

use App\Entity\Track;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class TrackRepository extends EntityRepository
{
    const MISSING_TUNEEFY = 0;
    const MISSING_SPOTIFY = 1;

    private function getTrackResultSetMapping()
    {
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(\App\Entity\Track::class, 't');
        $rsm->addFieldResult('t', 'id', 'id');
        $rsm->addFieldResult('t', 'title', 'title');
        $rsm->addFieldResult('t', 'album', 'album');
        $rsm->addFieldResult('t', 'artist', 'artist');
        $rsm->addFieldResult('t', 'image', 'image');

        return $rsm;
    }

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

    public function findHighlightsByYears()
    {
        $select = 'SELECT id, title, album, artist, image, year_n FROM years_mv';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);

        return $query->getResult();
    }

    public function findHighlightsByMonths(int $year)
    {
        $select = 'SELECT id, title, album, artist, image, year_n, month_n FROM months_mv WHERE year_n = ?';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');
        $rsm->addScalarResult('month_n', 'month_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);
        $query->setParameter(1, $year);

        return $query->getResult();
    }

    public function findHighlightsByDays(int $year, int $month)
    {
        $select = 'SELECT id, title, album, artist, image, year_n, month_n, day_n FROM days_mv WHERE year_n = ? AND month_n = ?';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');
        $rsm->addScalarResult('month_n', 'month_n');
        $rsm->addScalarResult('day_n', 'day_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);
        $query->setParameter(1, $year);
        $query->setParameter(2, $month);

        return $query->getResult();
    }

    public function findByMonth(int $year, int $month)
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

    public function findMissingTracksFrom(int $what = self::MISSING_TUNEEFY, \Datetime $fromDate = null)
    {
        $query = $this->createQueryBuilder('t');

        if (self::MISSING_TUNEEFY === $what) {
            $query->where('t.tuneefyLink IS NULL')
             ->andWhere('t.valid = 1');
        } elseif (self::MISSING_SPOTIFY === $what) {
            $query->where('t.spotifyLink IS NULL')
             ->andWhere('t.valid = 1');
        }

        if ($fromDate) {
            $query->andWhere('t.startedAt > :fromDate')
             ->setParameter('fromDate', $fromDate);
        }

        return $query->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }
}
