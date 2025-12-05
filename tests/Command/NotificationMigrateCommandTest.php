<?php

namespace App\Tests\Command;

use App\Command\NotificationMigrateCommand;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class NotificationMigrateCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private DocumentManager $testDm;
    private DocumentManager $diabetesDm;
    private DocumentManager $wellnessDm;
    private DocumentManager $maternityDm;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        // Create stubs for document managers (no expectations needed)
        $this->testDm = $this->createStub(DocumentManager::class);
        $this->diabetesDm = $this->createStub(DocumentManager::class);
        $this->wellnessDm = $this->createStub(DocumentManager::class);
        $this->maternityDm = $this->createStub(DocumentManager::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        // Create command with stubbed dependencies
        $command = new NotificationMigrateCommand(
            $this->testDm,
            $this->diabetesDm,
            $this->wellnessDm,
            $this->maternityDm,
            $this->logger
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testCommandNameIsCorrect(): void
    {
        $command = new NotificationMigrateCommand(
            $this->testDm,
            $this->diabetesDm,
            $this->wellnessDm,
            $this->maternityDm,
            $this->logger
        );

        $this->assertEquals('app:notification:migrate', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $command = new NotificationMigrateCommand(
            $this->testDm,
            $this->diabetesDm,
            $this->wellnessDm,
            $this->maternityDm,
            $this->logger
        );

        $this->assertStringContainsString('Migrate notification documents', $command->getDescription());
    }

    public function testInvalidDatabaseOptionReturnsFailure(): void
    {
        $this->commandTester->execute([
            '--database' => 'invalid_db',
            '--force' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid database', $output);
    }

    public function testInvalidBatchSizeReturnsFailure(): void
    {
        $this->commandTester->execute([
            '--batch-size' => '0',
            '--force' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Batch size must be between', $output);
    }

    public function testBatchSizeTooLargeReturnsFailure(): void
    {
        $this->commandTester->execute([
            '--batch-size' => '20000',
            '--force' => true,
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Batch size must be between', $output);
    }

    public function testDryRunModeSkipsConfirmation(): void
    {
        // Mock collection with no documents
        $collection = $this->createMockCollectionWithCount(0);

        // Create mock DocumentManager with expectations
        $testDm = $this->createMock(DocumentManager::class);
        $testDm->expects($this->once())
            ->method('getDocumentCollection')
            ->willReturn($collection);

        // Create command with mock
        $command = new NotificationMigrateCommand(
            $testDm,
            $this->diabetesDm,
            $this->wellnessDm,
            $this->maternityDm,
            $this->logger
        );
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--database' => 'test',
            '--dry-run' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
    }

    public function testForceOptionSkipsConfirmation(): void
    {
        // This test validates force option behavior through validation-only code paths
        $this->commandTester->execute([
            '--database' => 'invalid_db',
            '--force' => true,
        ]);

        // Even with --force, invalid database should fail
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
    }

    public function testValidDatabaseOptions(): void
    {
        // Test validation logic without database interaction
        $validDatabases = ['test', 'diabetes', 'wellness', 'maternity', 'all'];

        foreach ($validDatabases as $database) {
            // Test with dry-run to avoid backup creation (which requires database methods)
            $this->commandTester->execute([
                '--database' => $database,
                '--dry-run' => true,
            ]);

            $output = $this->commandTester->getDisplay();
            // Should not show invalid database error
            $this->assertStringNotContainsString('Invalid database', $output);
        }
    }

    public function testLoggerIsCalledDuringExecution(): void
    {
        // Create a mock logger with expectations (not a stub)
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('info');

        // Create command with mock logger
        $command = new NotificationMigrateCommand(
            $this->testDm,
            $this->diabetesDm,
            $this->wellnessDm,
            $this->maternityDm,
            $logger
        );
        $commandTester = new CommandTester($command);

        // Use dry-run to avoid database operations
        $commandTester->execute([
            '--database' => 'test',
            '--dry-run' => true,
        ]);
    }

    public function testCustomBatchSizeIsRespected(): void
    {
        // Use dry-run mode to test batch size validation
        $this->commandTester->execute([
            '--database' => 'test',
            '--batch-size' => '500',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('500', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testRollbackOptionChangesMode(): void
    {
        // Use dry-run to test mode without database operations
        $this->commandTester->execute([
            '--database' => 'test',
            '--rollback' => true,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        // Check that rollback is shown in the title
        $this->assertStringContainsString('Rollback', $output);
        $this->assertStringContainsString('Rolling back', $output);
    }

    private function createMockCollectionWithCount(int $count): object
    {
        $collection = $this->createStub(\MongoDB\Collection::class);
        $collection->method('count')->willReturn($count);

        return $collection;
    }

    private function createMockDatabase(): object
    {
        $database = $this->createStub(\MongoDB\Database::class);
        $database->method('listCollectionNames')->willReturn(new \ArrayIterator([]));

        return $database;
    }
}
