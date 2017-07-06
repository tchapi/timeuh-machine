<?php

namespace MainBundle\Repository;

use Doctrine\ORM\EntityRepository;

class TrackRepository extends EntityRepository
{
    public function findCurrentlyPlayingTrack()
    {
        $limit = new \Datetime('now - 10 minutes');

        return $this->createQueryBuilder('t')
               ->where('t.startedAt > :limit')
               ->setParameter('limit', $limit)
               ->getQuery()
               ->getOneOrNullResult();
    }

    public function findNLastTracksExceptCurrent(int $max, ?Track $current)
    {
        $q = $this->createQueryBuilder('t');

        if ($current) {
          $q->andWhere('t.id != :currentId')
            ->setParameter('currentId', $current->getId());
        }

        return $q->setMaxResults($max)
                 ->orderBy('t.startedAt', 'DESC')
                 ->getQuery()
                 ->getResult();
    }
}
