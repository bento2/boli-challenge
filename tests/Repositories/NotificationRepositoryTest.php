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
}
