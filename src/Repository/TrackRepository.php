<?php

namespace App\Repository;

use App\Entity\Track;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

class TrackRepository extends EntityRepository
{
    const MODE_YEARS = 0;
    const MODE_MONTHS = 1;
    const MODE_DAYS = 2;

    const MISSING_TUNEEFY = 0;
    const MISSING_SPOTIFY = 1;
    const MISSING_DEEZER = 2;

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
        $select = 'SELECT id, title, album, artist, image, year_n FROM years_mv ORDER BY year_n DESC';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);

        return $query->getResult();
    }

    public function findHighlightsByMonths(int $year)
    {
        $select = 'SELECT id, title, album, artist, image, month_n FROM months_mv WHERE year_n = ? ORDER BY month_n DESC';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');
        $rsm->addScalarResult('month_n', 'month_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);
        $query->setParameter(1, $year);

        return $query->getResult();
    }

    public function findHighlightsByDays(int $year, int $month)
    {
        $select = 'SELECT id, title, album, artist, image, day_n FROM days_mv WHERE year_n = ? AND month_n = ? ORDER BY day_n DESC';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');
        $rsm->addScalarResult('month_n', 'month_n');
        $rsm->addScalarResult('day_n', 'day_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);
        $query->setParameter(1, $year);
        $query->setParameter(2, $month);

        return $query->getResult();
    }

    public function updateHighlights(int $mode, string $year, ?string $month = null)
    {
        $connection = $this->getEntityManager()
                            ->getConnection()
                            ->getWrappedConnection();

        switch ($mode) {
            case self::MODE_YEARS:
                $stmt = $connection->prepare('CALL refresh_years_mv_now(:year)');
                break;
            case self::MODE_MONTHS:
                $stmt = $connection->prepare('CALL refresh_months_mv_now(:year)');
                break;
            case self::MODE_DAYS:
                $stmt = $connection->prepare('CALL refresh_days_mv_now(:year, :month)');
                $stmt->bindParam(':month', $month, \PDO::PARAM_INT);
                break;
            default:
                return false;
                break;
        }

        $stmt->bindParam(':year', $year, \PDO::PARAM_INT);
        $stmt->execute();

        return true;
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

    public function findProviderLinksForMonth($provider, $year, $month)
    {
        $columnName = strtolower($provider).'Link';

        return $this->createQueryBuilder('t')
             ->select('t.'.$columnName)
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('t.valid = 1')
             ->andWhere('t.'.$columnName.' IS NOT NULL')
             ->orderBy('t.startedAt', 'DESC')
             ->getQuery()
             ->getResult();
    }

    public function findProviderLinksForDay($provider, $year, $month, $day)
    {
        $columnName = strtolower($provider).'Link';

        return $this->createQueryBuilder('t')
             ->select('t.'.$columnName)
             ->where('YEAR(t.startedAt) = :year')
             ->setParameter('year', $year)
             ->andWhere('MONTH(t.startedAt) = :month')
             ->setParameter('month', $month)
             ->andWhere('DAY(t.startedAt) = :day')
             ->setParameter('day', $day)
             ->andWhere('t.valid = 1')
             ->andWhere('t.'.$columnName.' IS NOT NULL')
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
            $query->where('t.tuneefyLink IS NOT NULL')
                ->andWhere('t.spotifyLink IS NULL')
                ->andWhere('t.valid = 1');
        } elseif (self::MISSING_DEEZER === $what) {
            $query->where('t.tuneefyLink IS NOT NULL')
                ->andWhere('t.deezerLink IS NULL')
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
