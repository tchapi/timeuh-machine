<?php

namespace MainBundle\Repository;

use MainBundle\Entity\Track;
use Doctrine\ORM\EntityRepository;

class TrackRepository extends EntityRepository
{
    public function findCurrentlyPlayingTrack()
    {
        $limit = new \Datetime('now - 10 minutes');

        return $this->createQueryBuilder('t')
               ->where('t.startedAt > :limit')
               ->setParameter('limit', $limit)
               ->andWhere('t.valid = 1')
               ->orderBy('t.startedAt', 'DESC')
               ->getQuery()
               ->setMaxResults(1)
               ->getOneOrNullResult();
    }

    public function findNLastTracksExceptCurrent(int $max, ?Track $current)
    {
        $q = $this->createQueryBuilder('t')
                  ->where('t.valid = 1');

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
