<?php

namespace App\Tests\Repositories;

use App\Document\Notification;
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
    public function testFindUnreadByUser(): void{
        //création
    }
}
