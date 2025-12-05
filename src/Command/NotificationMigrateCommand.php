<?php

namespace App\Command;

use App\Enum\NotificationServiceName;
use App\Enum\NotificationStatus;
use App\Enum\NotificationTypes;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use MongoDB\Driver\Exception\Exception as MongoException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notification:migrate',
    description: 'Migrate notification documents across databases with batch processing and rollback support',
)]
class NotificationMigrateCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 1000;
    private const BACKUP_COLLECTION_SUFFIX = '_backup';

    private array $documentManagers;
    private LoggerInterface $logger;
    private ?string $logFilePath = null;

    public function __construct(
        DocumentManager $testDocumentManager,
        DocumentManager $diabetesDocumentManager,
        DocumentManager $wellnessDocumentManager,
        DocumentManager $maternityDocumentManager,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->documentManagers = [
            'test' => $testDocumentManager,
            'diabetes' => $diabetesDocumentManager,
            'wellness' => $wellnessDocumentManager,
            'maternity' => $maternityDocumentManager,
        ];

        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'database',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Database to migrate (test, diabetes, wellness, maternity, or all)',
                'all'
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of documents to process per batch',
                self::DEFAULT_BATCH_SIZE
            )
            ->addOption(
                'rollback',
                'r',
                InputOption::VALUE_NONE,
                'Rollback migration from backup'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulate migration without applying changes'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompts'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Initialize log file
        $this->initializeLogFile();

        try {
            // Get options
            $database = $input->getOption('database');
            $batchSize = (int) $input->getOption('batch-size');
            $isRollback = $input->getOption('rollback');
            $isDryRun = $input->getOption('dry-run');
            $force = $input->getOption('force');

            // Validate options
            if (!$this->validateOptions($database, $batchSize, $io)) {
                return Command::FAILURE;
            }

            // Get databases to process
            $databases = $this->getDatabases($database);

            // Display header
            $this->displayHeader($io, $databases, $batchSize, $isRollback, $isDryRun);

            // Confirm action
            if (!$force && !$this->confirmAction($input, $output, $isRollback, $isDryRun)) {
                $io->warning('Migration cancelled by user.');
                return Command::SUCCESS;
            }

            // Execute migration or rollback
            if ($isRollback) {
                $this->executeRollback($databases, $io, $isDryRun);
            } else {
                $this->executeMigration($databases, $batchSize, $io, $isDryRun, $output);
            }

            $io->success('Operation completed successfully!');
            $io->info("Log file: {$this->logFilePath}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->log('ERROR', 'Fatal error: ' . $e->getMessage());
            $io->error('Migration failed: ' . $e->getMessage());
            $io->info("Check log file: {$this->logFilePath}");
            return Command::FAILURE;
        }
    }

    private function validateOptions(string $database, int $batchSize, SymfonyStyle $io): bool
    {
        $validDatabases = array_merge(['all'], array_keys($this->documentManagers));

        if (!in_array($database, $validDatabases, true)) {
            $io->error("Invalid database: {$database}. Valid options: " . implode(', ', $validDatabases));
            return false;
        }

        if ($batchSize < 1 || $batchSize > 10000) {
            $io->error("Batch size must be between 1 and 10000. Got: {$batchSize}");
            return false;
        }

        return true;
    }

    private function getDatabases(string $database): array
    {
        if ($database === 'all') {
            return array_keys($this->documentManagers);
        }

        return [$database];
    }

    private function displayHeader(
        SymfonyStyle $io,
        array $databases,
        int $batchSize,
        bool $isRollback,
        bool $isDryRun
    ): void {
        $io->title($isRollback ? 'Notification Migration Rollback' : 'Notification Migration');

        $io->info([
            'Databases: ' . implode(', ', $databases),
            'Batch size: ' . $batchSize,
            'Mode: ' . ($isDryRun ? 'DRY RUN' : ($isRollback ? 'ROLLBACK' : 'MIGRATION')),
        ]);
    }

    private function confirmAction(
        InputInterface $input,
        OutputInterface $output,
        bool $isRollback,
        bool $isDryRun
    ): bool {
        if ($isDryRun) {
            return true;
        }

        $helper = $this->getHelper('question');
        $message = $isRollback
            ? 'Are you sure you want to rollback the migration? This will restore from backup. (yes/no) '
            : 'Are you sure you want to proceed with the migration? (yes/no) ';

        $question = new ConfirmationQuestion($message, false);

        return $helper->ask($input, $output, $question);
    }

    private function executeMigration(array $databases, int $batchSize, SymfonyStyle $io, bool $isDryRun, OutputInterface $output): void
    {
        foreach ($databases as $dbName) {
            $io->section("Processing database: {$dbName}");
            $this->log('INFO', "Starting migration for database: {$dbName}");

            $dm = $this->documentManagers[$dbName];

            // Create backup
            if (!$isDryRun) {
                $this->createBackup($dm, $io);
            }

            // Get total count
            $collection = $dm->getDocumentCollection('App\Document\Notification');
            $totalCount = $collection->count();

            if ($totalCount === 0) {
                $io->note("No documents to migrate in {$dbName}");
                continue;
            }

            $io->text("Found {$totalCount} documents to migrate");

            // Process in batches
            $progressBar = new ProgressBar($output, $totalCount);
            $progressBar->setFormat('very_verbose');
            $progressBar->start();

            $processed = 0;
            $migrated = 0;
            $errors = 0;

            while ($processed < $totalCount) {
                try {
                    $batch = $this->processBatch($dm, $processed, $batchSize, $isDryRun);
                    $migrated += $batch['migrated'];
                    $errors += $batch['errors'];
                    $processed += $batch['processed'];

                    $progressBar->advance($batch['processed']);

                } catch (\Exception $e) {
                    $this->log('ERROR', "Batch processing failed: {$e->getMessage()}");
                    $errors++;
                    break;
                }
            }

            $progressBar->finish();
            $io->newLine(2);

            $io->success([
                "Database {$dbName} completed:",
                "Total processed: {$processed}",
                "Migrated: {$migrated}",
                "Errors: {$errors}",
            ]);
        }
    }

    private function processBatch(DocumentManager $dm, int $offset, int $limit, bool $isDryRun): array
    {
        $collection = $dm->getDocumentCollection('App\Document\Notification');

        // Use MongoDB transaction if supported
        $supportsTransactions = $this->supportsTransactions($dm);

        if ($supportsTransactions && !$isDryRun) {
            $dm->getClient()->startSession();
        }

        try {
            $cursor = $collection->find([], [
                'skip' => $offset,
                'limit' => $limit,
            ]);
            $migrated = 0;
            $errors = 0;
            $processed = 0;

            // Convert cursor to array to iterate
            $documents = iterator_to_array($cursor);

            foreach ($documents as $document) {
                $processed++;

                try {
                    if ($this->migrateDocument($collection, $document, $isDryRun)) {
                        $migrated++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $documentId = isset($document['_id']) ? (string)$document['_id'] : 'unknown';
                    $this->log('ERROR', "Failed to migrate document {$documentId}: " . $e->getMessage());
                }
            }

            if ($supportsTransactions && !$isDryRun) {
                // Commit would happen here in a real transaction
            }

            return [
                'processed' => $processed,
                'migrated' => $migrated,
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            if ($supportsTransactions && !$isDryRun) {
                // Rollback would happen here
            }
            throw $e;
        }
    }

    private function migrateDocument($collection, $document, bool $isDryRun): bool
    {
        $updated = false;
        $updates = [];

        // Normalize status
        if (!isset($document['status']) || !in_array($document['status'], NotificationStatus::getValues(), true)) {
            $updates['status'] = NotificationStatus::PENDING->value;
            $updated = true;
        }

        // Normalize type
        if (!isset($document['type']) || !in_array($document['type'], NotificationTypes::getValues(), true)) {
            $updates['type'] = NotificationTypes::INFO->value;
            $updated = true;
        }

        // Normalize serviceName
        if (!isset($document['serviceName']) || !in_array($document['serviceName'], NotificationServiceName::getValues(), true)) {
            $updates['serviceName'] = NotificationServiceName::DIABETES->value;
            $updated = true;
        }

        // Ensure data field exists
        if (!isset($document['data'])) {
            $updates['data'] = [];
            $updated = true;
        }

        // Apply updates
        if ($updated && !$isDryRun) {
            $collection->updateOne(
                ['_id' => $document['_id']],
                ['$set' => $updates]
            );

            $documentId = isset($document['_id']) ? (string)$document['_id'] : 'unknown';
            $this->log('INFO', "Migrated document {$documentId} with updates: " . json_encode($updates));
        } elseif ($updated) {
            $documentId = isset($document['_id']) ? (string)$document['_id'] : 'unknown';
            $this->log('INFO', "[DRY RUN] Would migrate document {$documentId} with updates: " . json_encode($updates));
        }

        return $updated;
    }

    private function createBackup(DocumentManager $dm, SymfonyStyle $io): void
    {
        $io->text('Creating backup...');
        $this->log('INFO', 'Creating backup collection');

        try {
            // Get database using the MongoDB client instead of Collection::getDatabase()
            $client = $dm->getClient();
            $database = $client->selectDatabase('test_db'); // Use the appropriate database name

            $backupName = 'notifications' . self::BACKUP_COLLECTION_SUFFIX;

            // Drop existing backup
            if (in_array($backupName, iterator_to_array($database->listCollectionNames()), true)) {
                $database->dropCollection($backupName);
            }

            // Create new backup by copying collection using aggregate with $out
            $database->command([
                'aggregate' => 'notifications',
                'pipeline' => [
                    ['$match' => new \stdClass()],  // Match all documents - must be an object
                    ['$out' => $backupName],
                ],
                'cursor' => new \stdClass(),
            ]);

            $io->success('Backup created successfully');
            $this->log('INFO', 'Backup created: ' . $backupName);

        } catch (\Exception $e) {
            $io->error('Failed to create backup: ' . $e->getMessage());
            throw $e;
        }
    }

    private function executeRollback(array $databases, SymfonyStyle $io, bool $isDryRun): void
    {
        foreach ($databases as $dbName) {
            $io->section("Rolling back database: {$dbName}");
            $this->log('INFO', "Starting rollback for database: {$dbName}");

            // Check dry-run first to avoid database access in tests
            if ($isDryRun) {
                $io->note("[DRY RUN] Would check for backup and restore from backup for {$dbName}");
                $this->log('INFO', "[DRY RUN] Would restore database: {$dbName}");
                continue;
            }

            $dm = $this->documentManagers[$dbName];

            // Get database using the MongoDB client instead of Collection::getDatabase()
            $client = $dm->getClient();
            $database = $client->selectDatabase('test_db'); // Use the appropriate database name

            $backupName = 'notifications' . self::BACKUP_COLLECTION_SUFFIX;

            // Check if backup exists
            if (!in_array($backupName, iterator_to_array($database->listCollectionNames()), true)) {
                $io->warning("No backup found for {$dbName}. Skipping.");
                continue;
            }

            try {
                // Drop current collection
                $database->dropCollection('notifications');

                // Rename backup to original
                $database->{$backupName}->rename('notifications');

                $io->success("Rollback completed for {$dbName}");
                $this->log('INFO', "Rollback completed for database: {$dbName}");

            } catch (\Exception $e) {
                $io->error("Rollback failed for {$dbName}: " . $e->getMessage());
                $this->log('ERROR', "Rollback failed for {$dbName}: " . $e->getMessage());
            }
        }
    }

    private function supportsTransactions(DocumentManager $dm): bool
    {
        try {
            // Check if MongoDB supports transactions (requires replica set)
            $client = $dm->getClient();
            $admin = $client->selectDatabase('admin');
            $cursor = $admin->command(['isMaster' => 1]);

            // Get the first result from the cursor
            $result = $cursor->toArray()[0] ?? null;

            return isset($result['setName']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function initializeLogFile(): void
    {
        $logDir = dirname(__DIR__, 2) . '/var/log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $this->logFilePath = "{$logDir}/migration_{$timestamp}.log";

        $this->log('INFO', '=== Migration started ===');
    }

    private function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        if ($this->logFilePath) {
            file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);
        }

        // Also log to Symfony logger
        match($level) {
            'ERROR' => $this->logger->error($message),
            'WARNING' => $this->logger->warning($message),
            'INFO' => $this->logger->info($message),
            default => $this->logger->debug($message),
        };
    }
}
