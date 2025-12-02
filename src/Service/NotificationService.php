<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

class NotificationService implements NotificationServiceInterface
{
    private $dryRun = false;
    public function __construct(
        private RateLimiterFactoryInterface $notificationApi,
        private LoggerInterface $logger,
    ) {}
    public function setDryRun(bool $dryRun = false): void
    {
        $this->dryRun = $dryRun;
    }



    public function sendNotification(string $userId, string $type, string $title, string $body, array $data, string $serviceName): bool
    {
        $typeNotification = NotificationType::fromString($type);

        $policy = $this->notificationApi->create("notification_api_$userId");
        $limiter = $policy->consume();

        if ($limiter->isAccepted() === false) {
            $this->logger->error("Rate limit exceeded for user $userId");
            throw new RateLimitExceededException($limiter);
        }
        if($this->dryRun) {
            $this->logger->warning("Dry run mode, notification not sent for user $userId");
            return true;
        }else{
            //envoi de la notification
        }
        return true;
    }
}
