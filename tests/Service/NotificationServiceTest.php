<?php

namespace App\Tests\Service;

use App\Service\Exception\InvalidTypeException;
use App\Service\NotificationServiceInterface;
use App\Service\NotificationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Psr\Log\LoggerInterface;

class NotificationServiceTest extends KernelTestCase
{
    private NotificationServiceInterface $notificationService;
    private LoggerInterface $loggerMock;
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        // on essaye de mocker le logger pour catcher les messages d'erreur
         $this->loggerMock = $this->createMock(LoggerInterface::class);

        // Remplacer le service logger dans le container de test
        self::getContainer()->set(LoggerInterface::class, $this->loggerMock);

        $this->notificationService = $this->getContainer()->get(NotificationServiceInterface::class);

        //supprimer le cache avant de refaire des tests
        $cache = self::getContainer()->get('cache.rate_limiter');
        $cache->clear();
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
        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Rate limit exceeded for user userId-11'));

        for($i = 0; $i < 11; $i++) {
            $retour = $this->notificationService->sendNotification('userId-11', NotificationType::INFO->value, 'title', 'body', [], 'serviceName');
            if( $i < 10) {
                $this->assertTrue($retour);
            }else{
                $this->assertFalse($retour);
            }
        }
    }

    public function testSansExecptionDeDeuxUser(): void{
        for($i = 0; $i < 11; $i++) {
            $retour = $this->notificationService->sendNotification('userId-'.($i%2), NotificationType::INFO->value, 'title', 'body', [], 'serviceName');
            $this->assertTrue($retour);
        }
    }
}
