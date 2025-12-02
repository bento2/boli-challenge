<?php

namespace App\Tests\Service;

use App\Service\Exception\InvalidTypeException;
use App\Service\NotificationServiceInterface;
use App\Service\NotificationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

class NotificationServiceTest extends KernelTestCase
{
    private NotificationServiceInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->notificationService = $this->getContainer()->get(NotificationServiceInterface::class);
    }

    public function testTypeNameException(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->notificationService->sendNotification('userId', 'type', 'title', 'body', [], 'serviceName');
    }

    public function testTypeName(): void
    {
        //verifier que le message est envoyé
        $retour = $this->notificationService->sendNotification('userId', NotificationType::ALERT->value, 'title', 'body', [], 'serviceName');
        $this->assertTrue($retour);
    }

    public function testTypeNameDryRun(): void
    {
        //verifier que le message est envoyé
        $this->notificationService->setDryRun(true);
        $retour = $this->notificationService->sendNotification('userId', NotificationType::ALERT->value, 'title', 'body', [], 'serviceName');
        $this->assertTrue($retour);
    }

    public function testFiveNotifications(): void
    {
        //test arbitraire sur 5 notifications
        for($i = 0; $i < 5; $i++) {
            $retour = $this->notificationService->sendNotification('userId-5', NotificationType::INFO->value, 'title', 'body', [], 'serviceName');
            $this->assertTrue($retour);
        }
    }

    public function testRateLimit(): void
    {
        $this->expectException(RateLimitExceededException::class);
        //test arbitraire sur 11 notifications
        //voir plutard si il est possible de récupérer l'information dans la factory
        for($i = 0; $i < 11; $i++) {
            $retour = $this->notificationService->sendNotification('userId-11', NotificationType::INFO->value, 'title', 'body', [], 'serviceName');
            $this->assertTrue($retour);
        }
    }

    public function testSansExecptionDeDeuxUser(): void{
        for($i = 0; $i < 11; $i++) {
            $retour = $this->notificationService->sendNotification('userId-'+($i%2), NotificationType::INFO->value, 'title', 'body', [], 'serviceName');
            $this->assertTrue($retour);
        }
    }
}
