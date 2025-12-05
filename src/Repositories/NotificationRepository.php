<?php

namespace App\Repositories;

use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use App\Enum\NotificationStatus;

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

    /**
     * @param string $serviceName
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     * - Retourne des statistiques complètes :
     *   - Total de notifications
     *   - Par type (alert, reminder, info)
     *   - Par statut (pending, sent, failed)
     *   - Taux de succès (%)
     *   - Temps moyen de traitement (différence entre createdAt et sentAt)
     * - Utilise une pipeline d'agrégation MongoDB
     */
    public function getStatisticsByService(string $serviceName, \DateTime $startDate, \DateTime $endDate): array
    {
        // Get total count
        $totalCount = $this->createQueryBuilder()
            ->field('serviceName')->equals($serviceName)
            ->field('createdAt')->gte($startDate)
            ->field('createdAt')->lte($endDate)
            ->count()
            ->getQuery()
            ->execute();

        if ($totalCount === 0) {
            return [
                'total' => 0,
                'byType' => [],
                'byStatus' => [],
                'successRate' => 0,
                'avgProcessingTime' => 0,
            ];
        }

        // Group by type
        $byTypeBuilder = $this->createAggregationBuilder();
        $byTypeBuilder
            ->match()
                ->field('serviceName')->equals($serviceName)
                ->field('createdAt')->gte($startDate)
                ->field('createdAt')->lte($endDate)
            ->group()
                ->field('_id')->expression('$type')
                ->field('count')->sum(1);

        $byTypeResult = $byTypeBuilder->getAggregation()->getIterator()->toArray();
        $byType = [];
        foreach ($byTypeResult as $item) {
            $byType[$item['_id']] = (int)$item['count'];
        }

        // Group by status
        $byStatusBuilder = $this->createAggregationBuilder();
        $byStatusBuilder
            ->match()
                ->field('serviceName')->equals($serviceName)
                ->field('createdAt')->gte($startDate)
                ->field('createdAt')->lte($endDate)
            ->group()
                ->field('_id')->expression('$status')
                ->field('count')->sum(1);

        $byStatusResult = $byStatusBuilder->getAggregation()->getIterator()->toArray();
        $byStatus = [];
        $sentCount = 0;
        $failedCount = 0;
        foreach ($byStatusResult as $item) {
            $byStatus[$item['_id']] = (int)$item['count'];
            if ($item['_id'] === 'sent') {
                $sentCount = (int)$item['count'];
            } elseif ($item['_id'] === 'failed') {
                $failedCount = (int)$item['count'];
            }
        }

        // Calculate success rate
        $totalAttempted = $sentCount + $failedCount;
        $successRate = $totalAttempted > 0
            ? round(($sentCount / $totalAttempted) * 100, 2)
            : 0;

        // Calculate average processing time for sent notifications
        $avgProcessingTime = 0;
        $avgBuilder = $this->createAggregationBuilder();
        $avgBuilder
            ->match()
                ->field('serviceName')->equals($serviceName)
                ->field('createdAt')->gte($startDate)
                ->field('createdAt')->lte($endDate)
                ->field('status')->equals('sent')
                ->field('sentAt')->notEqual(null)
            ->group()
                ->field('_id')->expression(null)
                ->field('avgTime')->avg(
                    $avgBuilder->expr()->subtract('$sentAt', '$createdAt')
                );

        $avgResult = $avgBuilder->getAggregation()->getIterator()->toArray();
        if (!empty($avgResult) && !empty($avgResult[0]['avgTime'])) {
            // Convert milliseconds to seconds
            $avgProcessingTime = round($avgResult[0]['avgTime'] / 1000);
        }

        return [
            'total' => $totalCount,
            'byType' => $byType,
            'byStatus' => $byStatus,
            'successRate' => $successRate,
            'avgProcessingTime' => $avgProcessingTime,
        ];
    }

    /**
     * @param int $hours
     * @return array
     * - Trouve les notifications en échec plus anciennes que X heures
     * - Pour retry automatique
     * - Utilise une requête avec opérateurs MongoDB
     */
    public function findFailedNotificationsOlderThan(int $hours): array
    {
        // Calculate the threshold datetime
        $threshold = new \DateTime();
        $threshold->modify("-{$hours} hours");

        return $this->createQueryBuilder()
            ->field('status')->equals(NotificationStatus::FAILED->value)
            ->field('createdAt')->lt($threshold)
            ->sort('createdAt', 'ASC') // Oldest first for retry processing
            ->getQuery()
            ->execute()
            ->toArray();
    }
}
