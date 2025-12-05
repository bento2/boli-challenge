<?php

namespace App\Tests\Integration;

use App\Command\NotificationMigrateCommand;
use App\Document\Notification;
use App\Enum\NotificationServiceName;
use App\Enum\NotificationStatus;
use App\Enum\NotificationTypes;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for NotificationMigrateCommand using the test database.
 * These tests require MongoDB to be running.
 */
class NotificationMigrateIntegrationTest extends KernelTestCase
{
    private DocumentManager $testDm;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Check if MongoDB is available, skip tests if not
        try {
            // Get the test document manager
            $this->testDm = $container->get('doctrine_mongodb.odm.test_document_manager');

            // Test MongoDB connection with a short timeout
            $client = $this->testDm->getClient();
            $client->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB is not available: ' . $e->getMessage());
        }

        // Clean up test database before each test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->cleanDatabase();
        parent::tearDown();
    }

   /**public function testMigrationWithNoDocuments(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No documents to migrate', $output);
    }**/

    public function testMigrationWithValidDocuments(): void
    {
        // Create test documents
        $this->createTestNotifications(5);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Operation completed successfully', $output);
    }

    public function testMigrationWithInvalidDocuments(): void
    {
        // Create documents with missing fields
        $this->createInvalidTestNotifications(3);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        // Should show that documents were migrated
        $this->assertStringContainsString('Migrated:', $output);
    }

    public function testDryRunDoesNotModifyDocuments(): void
    {
        // Create test documents with invalid data
        $this->createInvalidTestNotifications(2);

        $countBefore = $this->getNotificationCount();

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--dry-run' => true,
        ]);

        $countAfter = $this->getNotificationCount();

        $this->assertEquals($countBefore, $countAfter);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testBatchProcessing(): void
    {
        // Create more documents than batch size
        $this->createTestNotifications(50);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--batch-size' => '10',
            '--force' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('50', $output); // Should show 50 documents
    }

    public function testBackupIsCreated(): void
    {
        $this->createTestNotifications(5);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        // Check that backup collection exists using MongoDB client
        $client = $this->testDm->getClient();
        $database = $client->selectDatabase('test_db');
        $collections = iterator_to_array($database->listCollectionNames());

        $this->assertContains('notifications_backup', $collections);
    }

    public function testRollbackRestoresFromBackup(): void
    {
        // Create initial documents
        $this->createTestNotifications(3);

        // First, run migration to create backup
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        // Add more documents to the main collection
        $this->createTestNotifications(2);
        $this->assertEquals(5, $this->getNotificationCount());

        // Now run rollback
        $commandTester->execute([
            '--database' => 'test',
            '--rollback' => true,
            '--force' => true,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());

        // Should have rolled back to 3 documents
        $this->assertEquals(3, $this->getNotificationCount());
    }

    public function testRollbackWithoutBackupWarns(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--rollback' => true,
            '--force' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No backup found', $output);
    }

    public function testProgressBarIsDisplayed(): void
    {
        $this->createTestNotifications(10);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--force' => true,
        ]);

        $output = $commandTester->getDisplay();
        // Progress bar should show some progress indication
        $this->assertStringContainsString('10', $output);
    }

    private function createCommand(): NotificationMigrateCommand
    {
        $container = static::getContainer();

        return new NotificationMigrateCommand(
            $container->get('doctrine_mongodb.odm.test_document_manager'),
            $container->get('doctrine_mongodb.odm.default_document_manager'),
            $container->get('doctrine_mongodb.odm.wellness_document_manager'),
            $container->get('doctrine_mongodb.odm.maternity_document_manager'),
            $container->get(LoggerInterface::class)
        );
    }

    private function createTestNotifications(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $notification = new Notification(
                "user-{$i}",
                NotificationTypes::INFO->value,
                "Test Notification {$i}",
                "Test body {$i}",
                NotificationServiceName::DIABETES->value,
                ['test' => true]
            );

            $this->testDm->persist($notification);
        }

        $this->testDm->flush();
    }

    private function createInvalidTestNotifications(int $count): void
    {
        $collection = $this->testDm->getDocumentCollection(Notification::class);

        for ($i = 0; $i < $count; $i++) {
            // Insert documents directly to bypass validation
            $collection->insertOne([
                'userId' => "user-invalid-{$i}",
                'title' => "Invalid Notification {$i}",
                'body' => "Invalid body {$i}",
                // Missing: type, status, serviceName, data
                'createdAt' => new \MongoDB\BSON\UTCDateTime(),
            ]);
        }
    }

    private function getNotificationCount(): int
    {
        $collection = $this->testDm->getDocumentCollection(Notification::class);
        return $collection->count();
    }

    private function cleanDatabase(): void
    {
        try {
            // Use DocumentManager to clear all notifications
            $qb = $this->testDm->createQueryBuilder(Notification::class);
            $qb->remove()->getQuery()->execute();

            // Also drop backup collection if it exists using the MongoDB client directly
            $client = $this->testDm->getClient();
            $database = $client->selectDatabase('test_db');

            try {
                $database->dropCollection('notifications_backup');
            } catch (\Exception $e) {
                // Collection might not exist, ignore
            }
        } catch (\Exception $e) {
            // If anything fails, database might not be initialized yet, ignore
        }
    }
}
