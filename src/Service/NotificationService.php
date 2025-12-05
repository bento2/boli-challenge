<?php

namespace App\Service;

use App\Service\Exception\SendMessageException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Bridge\Firebase\Notification\WebNotification;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;


class NotificationService implements NotificationServiceInterface
{
    private $dryRun = false;

    public function __construct(
        private RateLimiterFactoryInterface $notificationApi,
        private LoggerInterface             $logger,
        private ChatterInterface            $chatter,
    )
    {
    }

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
        if ($this->dryRun) {
            $this->logger->warning("Dry run mode, notification not sent for user $userId");
            return true;
        } else {
            //création du message
            $messageOptions = new Webnotification($userId, []);
            $messageOptions->body($body)->data($data)->title($title)->to;
            $message = new ChatMessage($title, $messageOptions);


            $sentMessage = $this->chatter->send($message);
            if ($sentMessage === null) {
                $erreur = "Unable to send message to $userId";
                $this->logger->warning($erreur);
                throw new SendMessageException($erreur);
            }

            return $sentMessage->getMessageId() !== null;
        }

    }
}
