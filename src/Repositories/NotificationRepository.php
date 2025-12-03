<?php

namespace App\Repositories;

use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class NotificationRepository extends DocumentRepository
{
    /***
     * @param string $userId
     * @param string $serviceName
     * @param int $limit
     * @return array
     * - Retourne les notifications non lues d'un utilisateur
     * - Triées par date de création décroissante
     * - Limitées à `$limit` résultats
     * - Utilise une projection pour ne récupérer que les champs nécessaires
     */
    public function findUnreadByUser(string $userId, string $serviceName, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->field('userId')->equals($userId)// d'un utilisateur
            ->field('readAt')->equals(null)// non lues
            ->field('serviceName')->equals($serviceName)
            ->sort('createdAt', 'DESC')//- Triées par date de création décroissante
            ->sort('id', 'DESC')//- Triées par id si il y a des problèmes de microseconde
            ->limit($limit) //Limitées à `$limit` résultats
            ->getQuery()
            ->execute()
            ->toArray();
    }

    /**
     * @param string $status
     * @param string $serviceName
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return int
     * - Compte les notifications par statut et service dans une période
     * - Utilise une agrégation MongoDB optimisée
     */
    public function countByStatusAndService(string $status, string $serviceName, \DateTimeInterface $startDate, \DateTimeInterface $endDate):int
    {

        return $this->createQueryBuilder()
            ->field('status')->equals($status)
            ->field('serviceName')->equals($serviceName)
            ->field('createdAt')->gte($startDate)
            ->field('createdAt')->lte($endDate)
            ->getQuery()
            ->execute()->count();
    }

    public function getStatisticsByService(string $serviceName, \DateTime $startDate, \DateTime $endDate)
    {

    }

    public function findFailedNotificationsOlderThan(int $hours)
    {

    }

}
