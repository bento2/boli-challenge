<?php

namespace App\Tests\Repositories;

use App\Document\Notification;
use App\Enum\NotificationServiceName;
use App\Enum\NotificationStatus;
use App\Enum\NotificationTypes;
use App\Repositories\NotificationRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NotificationRepositoryTest extends KernelTestCase
{
    private DocumentManager $dm;
    private NotificationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        // Utiliser le document manager de test
        /** @var DocumentManager dm */
        $this->dm = self::getContainer()->get('doctrine_mongodb.odm.test_document_manager');
        $this->repository = $this->dm->getRepository(Notification::class);

        // Nettoyer la collection avant chaque test
        $this->dm->getDocumentCollection(Notification::class)->drop();
    }

    protected function tearDown(): void
    {
        // Nettoyer après les tests
        $this->dm->getDocumentCollection(Notification::class)->drop();
        $this->dm->close();
        parent::tearDown();
    }

    //test pour findUnreadByUser
    public function testFindUnreadByUser(): void
    {
        $userId = "user-1";
        //création d'une notification non lue
        $unreadNotification = new Notification($userId, NotificationTypes::INFO->value, "Non lue 1", "Le message non lu", NotificationServiceName::DIABETES->value, []);
        $unreadNotification2 = new Notification($userId, NotificationTypes::ALERT->value, "Non lue 2", "Le message non lu 2", NotificationServiceName::DIABETES->value, []);
        $readNotification = new Notification($userId, NotificationTypes::INFO->value, "lue 1", "Le message lu", NotificationServiceName::DIABETES->value, []);
        $readNotification->setReadAt(new \DateTimeImmutable());

        $this->dm->persist($readNotification);
        $this->dm->persist($unreadNotification);
        $this->dm->persist($unreadNotification2);
        $this->dm->flush();

        //passage au tests

        $unreadNotifications = $this->repository->findUnreadByUser($userId,NotificationServiceName::DIABETES->value);
        $this->assertCount(2, $unreadNotifications);
        $this->assertNull($unreadNotifications[0]->getReadAt());
        $this->assertNull($unreadNotifications[1]->getReadAt());
    }

    public function testLimitDefaultFindUnreadByUser(): void{
        $userId = "user-2";
        for($i=0;$i<21;$i++){
            $unreadNotification = new Notification($userId, NotificationTypes::INFO->value, "Non lue $i", "Le message non lu", NotificationServiceName::DIABETES->value, []);
            $this->dm->persist($unreadNotification);
        }

        $this->dm->flush();
        $unreadNotifications = $this->repository->findUnreadByUser($userId,NotificationServiceName::DIABETES->value);
        $this->assertCount(20, $unreadNotifications);

        $lastDateCreate = null;
        //vérification de l'ordre
        foreach ($unreadNotifications as $unreadNotification) {
            if($lastDateCreate === null){
                $lastDateCreate = $unreadNotification->getCreatedAt();
            }else{
                $this->assertTrue($unreadNotification->getCreatedAt()<$lastDateCreate);
                $lastDateCreate = $unreadNotification->getCreatedAt();
            }
        }
    }
    public function testLimitFindUnreadByUser(): void{
        $userId = "user-3";
        for($i=0;$i<10;$i++){
            $unreadNotification = new Notification($userId, NotificationTypes::INFO->value, "Non lue $i", "Le message non lu", NotificationServiceName::DIABETES->value, []);
            $this->dm->persist($unreadNotification);
        }

        $this->dm->flush();

        $unreadNotifications = $this->repository->findUnreadByUser($userId,NotificationServiceName::DIABETES->value,5);
        $this->assertCount(5, $unreadNotifications);
    }

    public function testCountByStatusAndService()
    {
        $userId = "user-4";
        $now = (new \DateTime())->modify('+10 minutes');
        $yesterday = (new \DateTime())->modify('-1 day');
        $notificationMaternity = new Notification($userId, NotificationTypes::INFO->value, "Non lue 1", "Le message non lu", NotificationServiceName::MATERNITY->value, []);
        $notificationDiabetesAlert = new Notification($userId, NotificationTypes::ALERT->value, "Non lue 2", "Le message non lu 2", NotificationServiceName::DIABETES->value, []);
        $notificationDiabetesInfo = new Notification($userId, NotificationTypes::INFO->value, "lue 1", "Le message lu", NotificationServiceName::DIABETES->value, []);

        $this->dm->persist($notificationMaternity);
        $this->dm->persist($notificationDiabetesInfo);
        $this->dm->persist($notificationDiabetesAlert);
        $this->dm->flush();

        //test 0 notification
        $result = $this->repository->countByStatusAndService(NotificationStatus::PENDING->value,NotificationServiceName::WELLNESS->value,$yesterday,$now);
        $this->assertEquals(0, $result);

        //test 1 notification
        $result = $this->repository->countByStatusAndService(NotificationStatus::PENDING->value,NotificationServiceName::MATERNITY->value,$yesterday,$now);
        $this->assertEquals(1, $result);

        //test 2 notifications
        $result = $this->repository->countByStatusAndService(NotificationStatus::PENDING->value,NotificationServiceName::DIABETES->value,$yesterday,$now);
        $this->assertEquals(2, $result);

    }

    public function testGetStatisticsByService(): void
    {
        $userId = "user-5";
        $now = new \DateTime();
        $yesterday = (new \DateTime())->modify('-1 day');
        $tomorrow = (new \DateTime())->modify('+1 day');

        // Create 3 pending notifications
        for ($i = 0; $i < 3; $i++) {
            $notification = new Notification($userId, NotificationTypes::INFO->value, "Pending notification $i", "Body", NotificationServiceName::DIABETES->value, []);
            $notification->setStatus(NotificationStatus::PENDING->value);
            $this->dm->persist($notification);
        }

        // Create 2 sent alert notifications with processing time
        for ($i = 0; $i < 2; $i++) {
            $notification = new Notification($userId, NotificationTypes::ALERT->value, "Sent notification $i", "Body", NotificationServiceName::DIABETES->value, []);
            $notification->setStatus(NotificationStatus::SENT->value);
            $createdAt = (new \DateTimeImmutable())->modify('-10 minutes');
            $sentAt = (new \DateTimeImmutable())->modify('-5 minutes');
            $notification->setCreatedAt($createdAt);
            $notification->setSentAt($sentAt);
            $this->dm->persist($notification);
        }

        // Create 1 failed reminder notification
        $notification = new Notification($userId, NotificationTypes::REMINDER->value, "Failed notification", "Body", NotificationServiceName::DIABETES->value, []);
        $notification->setStatus(NotificationStatus::FAILED->value);
        $this->dm->persist($notification);

        // Create 1 notification for another service (should not be counted)
        $otherServiceNotif = new Notification($userId, NotificationTypes::INFO->value, "Other service", "Body", NotificationServiceName::WELLNESS->value, []);
        $this->dm->persist($otherServiceNotif);

        $this->dm->flush();

        // Test the statistics
        $stats = $this->repository->getStatisticsByService(NotificationServiceName::DIABETES->value, $yesterday, $tomorrow);

        $this->assertIsArray($stats);
        $this->assertEquals(6, $stats['total']);

        // Test by type
        $this->assertEquals(3, $stats['byType'][NotificationTypes::INFO->value]);
        $this->assertEquals(2, $stats['byType'][NotificationTypes::ALERT->value]);
        $this->assertEquals(1, $stats['byType'][NotificationTypes::REMINDER->value]);

        // Test by status
        $this->assertEquals(3, $stats['byStatus'][NotificationStatus::PENDING->value]);
        $this->assertEquals(2, $stats['byStatus'][NotificationStatus::SENT->value]);
        $this->assertEquals(1, $stats['byStatus'][NotificationStatus::FAILED->value]);

        // Test success rate (2 sent / 3 total attempted = 66.66%)
        $this->assertEquals(66.67, $stats['successRate']);

        // Test average processing time (should be around 5 minutes = 300 seconds)
        $this->assertGreaterThan(250, $stats['avgProcessingTime']);
        $this->assertLessThan(350, $stats['avgProcessingTime']);

    }

    public function testGetStatisticsByServiceWithEmptyResult(): void
    {
        $yesterday = (new \DateTime())->modify('-1 day');
        $tomorrow = (new \DateTime())->modify('+1 day');

        $stats = $this->repository->getStatisticsByService(NotificationServiceName::DIABETES->value, $yesterday, $tomorrow);

        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals([], $stats['byType']);
        $this->assertEquals([], $stats['byStatus']);
        $this->assertEquals(0, $stats['successRate']);
        $this->assertEquals(0, $stats['avgProcessingTime']);
    }

    public function testFindFailedNotificationsOlderThan(): void
    {
        $userId = "user-6";

        // Create a failed notification from 2 hours ago
        $oldFailedNotif = new Notification($userId, NotificationTypes::ALERT->value, "Old failed", "Body", NotificationServiceName::DIABETES->value, []);
        $oldFailedNotif->setStatus(NotificationStatus::FAILED->value);
        $oldFailedNotif->setCreatedAt((new \DateTimeImmutable())->modify('-2 hours'));
        $this->dm->persist($oldFailedNotif);

        // Create a failed notification from 30 minutes ago (should not be included)
        $recentFailedNotif = new Notification($userId, NotificationTypes::INFO->value, "Recent failed", "Body", NotificationServiceName::DIABETES->value, []);
        $recentFailedNotif->setStatus(NotificationStatus::FAILED->value);
        $recentFailedNotif->setCreatedAt((new \DateTimeImmutable())->modify('-30 minutes'));
        $this->dm->persist($recentFailedNotif);

        // Create a sent notification from 3 hours ago (should not be included)
        $oldSentNotif = new Notification($userId, NotificationTypes::INFO->value, "Old sent", "Body", NotificationServiceName::DIABETES->value, []);
        $oldSentNotif->setStatus(NotificationStatus::SENT->value);
        $oldSentNotif->setCreatedAt((new \DateTimeImmutable())->modify('-3 hours'));
        $this->dm->persist($oldSentNotif);

        $this->dm->flush();

        // Test finding failed notifications older than 1 hour
        $failedNotifications = $this->repository->findFailedNotificationsOlderThan(1);

        $this->assertCount(1, $failedNotifications);
        $this->assertEquals("Old failed", $failedNotifications[0]->getTitle());
        $this->assertEquals(NotificationStatus::FAILED->value, $failedNotifications[0]->getStatus());
    }

    public function testFindFailedNotificationsOlderThanMultiple(): void
    {
        $userId = "user-7";

        // Create 3 failed notifications older than 2 hours
        for ($i = 0; $i < 3; $i++) {
            $notification = new Notification($userId, NotificationTypes::ALERT->value, "Failed $i", "Body", NotificationServiceName::DIABETES->value, []);
            $notification->setStatus(NotificationStatus::FAILED->value);
            $notification->setCreatedAt((new \DateTimeImmutable())->modify('-3 hours'));
            $this->dm->persist($notification);
        }

        // Create 1 pending notification older than 2 hours (should not be included)
        $pendingNotif = new Notification($userId, NotificationTypes::INFO->value, "Pending", "Body", NotificationServiceName::DIABETES->value, []);
        $pendingNotif->setStatus(NotificationStatus::PENDING->value);
        $pendingNotif->setCreatedAt((new \DateTimeImmutable())->modify('-3 hours'));
        $this->dm->persist($pendingNotif);

        $this->dm->flush();

        // Test finding failed notifications older than 2 hours
        $failedNotifications = $this->repository->findFailedNotificationsOlderThan(2);

        $this->assertCount(3, $failedNotifications);

        // Verify all are failed status
        foreach ($failedNotifications as $notification) {
            $this->assertEquals(NotificationStatus::FAILED->value, $notification->getStatus());
        }
    }
}
