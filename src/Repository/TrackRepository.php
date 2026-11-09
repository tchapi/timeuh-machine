<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Track;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @extends EntityRepository<Track>
 */
final class TrackRepository extends EntityRepository
{
    public const MODE_YEARS = 0;
    public const MODE_MONTHS = 1;
    public const MODE_DAYS = 2;

    public const MISSING_TUNEEFY = 0;
    public const MISSING_SPOTIFY = 1;
    public const MISSING_DEEZER = 2;

    public function findCurrentlyPlayingTrack(): ?Track
    {
        $limit = new \DateTime('now - 30 minutes');

        return $this->createQueryBuilder('t')
            ->where('t.startedAt > :limit')
            ->setParameter('limit', $limit, Types::DATETIME_MUTABLE)
            ->andWhere('t.valid = 1')
            ->orderBy('t.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * @return list<Track>
     */
    public function findNLastTracksExceptCurrentOnPage(int $max, ?Track $current, int $page): array
    {
        $q = $this->createQueryBuilder('t')
            ->where('t.valid = 1');

        if ($current) {
            $q->andWhere('t.id != :currentId')
                ->setParameter('currentId', $current->getId());
        }

        return $q->setFirstResult(($page - 1) * $max)
            ->setMaxResults($max)
            ->orderBy('t.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{0: Track, year_n: int|string}>
     */
    public function findHighlightsByYears(): array
    {
        $select = 'SELECT id, title, album, artist, image, year_n FROM years_mv ORDER BY year_n DESC';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);

        return $query->getResult();
    }

    /**
     * @return list<array{0: Track, month_n: int|string}>
     */
    public function findHighlightsByMonths(int $year): array
    {
        $select = 'SELECT id, title, album, artist, image, month_n FROM months_mv WHERE year_n = ? ORDER BY month_n DESC';

        $rsm = $this->getTrackResultSetMapping();
        $rsm->addScalarResult('year_n', 'year_n');
        $rsm->addScalarResult('month_n', 'month_n');

        $query = $this->getEntityManager()->createNativeQuery($select, $rsm);
        $query->setParameter(1, $year);

        return $query->getResult();
    }

    /**
     * @return list<array{0: Track, day_n: int|string}>
     */
    public function findHighlightsByDays(int $year, int $month): array
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

    public function updateHighlights(int $mode, int $year, ?int $month = null): bool
    {
        $connection = $this->getEntityManager()
            ->getConnection()
            ->getNativeConnection();

        if (!$connection instanceof \PDO) {
            throw new \RuntimeException('Refreshing highlights requires a PDO connection, got '.get_debug_type($connection));
        }

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
        }

        $stmt->bindParam(':year', $year, \PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    /**
     * @return list<array{startedAt: \DateTime, image: ?string, title: string, album: ?string, artist: ?string, day_n: int|string}>
     */
    public function findByMonth(int $year, int $month): array
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

    /**
     * @return list<Track>
     */
    public function findByDay(int $year, int $month, int $day): array
    {
        return $this->createQueryBuilder('t')
            ->where('YEAR(t.startedAt) = :year')
            ->setParameter('year', $year)
            ->andWhere('MONTH(t.startedAt) = :month')
            ->setParameter('month', $month)
            ->andWhere('DAY(t.startedAt) = :day')
            ->setParameter('day', $day)
            ->andWhere('t.valid = 1')
            ->orderBy('t.startedAt', \SortDirection::Descending)
            ->andWhere("t.image != ''")
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array<string, string>>
     */
    public function findProviderLinksForMonth(string $provider, int $year, int $month): array
    {
        $columnName = $provider.'Link';

        return $this->createQueryBuilder('t')
            ->select('t.'.$columnName)
            ->where('YEAR(t.startedAt) = :year')
            ->setParameter('year', $year)
            ->andWhere('MONTH(t.startedAt) = :month')
            ->setParameter('month', $month)
            ->andWhere('t.valid = 1')
            ->andWhere('t.'.$columnName.' IS NOT NULL')
            ->orderBy('t.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array<string, string>>
     */
    public function findProviderLinksForDay(string $provider, int $year, int $month, int $day): array
    {
        $columnName = $provider.'Link';

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
            ->orderBy('t.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Track>
     */
    public function findMissingTracksFrom(int $what = self::MISSING_TUNEEFY, ?\DateTime $fromDate = null): array
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
                ->setParameter('fromDate', $fromDate, Types::DATETIME_MUTABLE);
        }

        return $query->orderBy('t.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    private function getTrackResultSetMapping(): ResultSetMapping
    {
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Track::class, 't');
        $rsm->addFieldResult('t', 'id', 'id');
        $rsm->addFieldResult('t', 'title', 'title');
        $rsm->addFieldResult('t', 'album', 'album');
        $rsm->addFieldResult('t', 'artist', 'artist');
        $rsm->addFieldResult('t', 'image', 'image');

        return $rsm;
    }
}
