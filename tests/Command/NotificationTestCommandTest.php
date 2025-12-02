<?php

namespace App\Tests\Command;

use App\Command\NotificationTestCommand;
use App\Service\NotificationServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class NotificationTestCommandTest extends TestCase
{
    private NotificationServiceInterface $notificationService;
    private NotificationTestCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        // Mock du service de notification
        $this->notificationService = $this->createMock(NotificationServiceInterface::class);

        // Création de la commande avec le mock
        $this->command = new NotificationTestCommand($this->notificationService);

        // Configuration du CommandTester
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('app:notification:test');
        $this->commandTester = new CommandTester($command);
    }

    public function testCommandIsConfiguredCorrectly(): void
    {
        // Vérifie que le nom de la commande est correct
        $this->assertEquals('app:notification:test', $this->command->getName());

        // Vérifie que la description est présente
        $this->assertNotEmpty($this->command->getDescription());

        // Vérifie que les arguments sont définis
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('userId'));
        $this->assertTrue($definition->hasArgument('type'));

        // Vérifie que les arguments sont requis
        $this->assertTrue($definition->getArgument('userId')->isRequired());
        $this->assertTrue($definition->getArgument('type')->isRequired());

        // Vérifie que l'option dry-run existe
        $this->assertTrue($definition->hasOption('dry-run'));
    }

    public function testExecuteWithSuccessfulNotification(): void
    {
        // Configure le mock pour retourner true (succès)
        $this->notificationService
            ->expects($this->once())
            ->method('sendNotification')
            ->with('user123', 'info', 'Notification de test')
            ->willReturn(true);

        // N'attend pas d'appel à setDryRun car l'option n'est pas utilisée
        $this->notificationService
            ->expects($this->never())
            ->method('setDryRun');

        // Exécute la commande
        $exitCode = $this->commandTester->execute([
            'userId' => 'user123',
            'type' => 'info',
        ]);

        // Vérifie le code de sortie
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Vérifie que le message de succès est affiché
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('La notification est bien partie', $output);
    }

    public function testExecuteWithFailedNotification(): void
    {
        // Configure le mock pour retourner false (échec)
        $this->notificationService
            ->expects($this->once())
            ->method('sendNotification')
            ->with('user456', 'alert', 'Notification de test')
            ->willReturn(false);

        // Exécute la commande
        $exitCode = $this->commandTester->execute([
            'userId' => 'user456',
            'type' => 'alert',
        ]);

        // Vérifie le code de sortie
        $this->assertEquals(Command::FAILURE, $exitCode);

        // Vérifie que le message d'erreur est affiché
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Notification non envoyée', $output);
    }

    public function testExecuteWithDryRunOption(): void
    {
        // Configure le mock pour vérifier que setDryRun est appelé
        $this->notificationService
            ->expects($this->once())
            ->method('setDryRun')
            ->with(true);

        $this->notificationService
            ->expects($this->once())
            ->method('sendNotification')
            ->with('user789', 'warning', 'Notification de test')
            ->willReturn(true);

        // Exécute la commande avec l'option dry-run
        $exitCode = $this->commandTester->execute([
            'userId' => 'user789',
            'type' => 'warning',
            '--dry-run' => true,
        ]);

        // Vérifie le code de sortie
        $this->assertEquals(Command::SUCCESS, $exitCode);

        // Vérifie que le message de succès est affiché
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('La notification est bien partie', $output);
    }

    public function testExecuteWithDryRunShortOption(): void
    {
        // Configure le mock pour vérifier que setDryRun est appelé avec l'option courte -d
        $this->notificationService
            ->expects($this->once())
            ->method('setDryRun')
            ->with(true);

        $this->notificationService
            ->expects($this->once())
            ->method('sendNotification')
            ->willReturn(true);

        // Exécute la commande avec l'option -d
        $exitCode = $this->commandTester->execute([
            'userId' => 'user999',
            'type' => 'info',
            '-d' => true,
        ]);

        // Vérifie le code de sortie
        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithException(): void
    {
        // Configure le mock pour lancer une exception
        $exceptionMessage = 'Erreur lors de l\'envoi de la notification';
        $this->notificationService
            ->expects($this->once())
            ->method('sendNotification')
            ->willThrowException(new \Exception($exceptionMessage));

        // Exécute la commande
        $exitCode = $this->commandTester->execute([
            'userId' => 'user000',
            'type' => 'error',
        ]);

        // Vérifie le code de sortie
        $this->assertEquals(Command::FAILURE, $exitCode);

        // Vérifie que le message d'erreur est affiché
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString($exceptionMessage, $output);
    }

    public function testExecuteWithDifferentNotificationTypes(): void
    {
        $types = ['info', 'alert', 'reminder'];

        foreach ($types as $type) {
            $notificationService = $this->createMock(NotificationServiceInterface::class);
            $notificationService
                ->expects($this->once())
                ->method('sendNotification')
                ->with('user123', $type, 'Notification de test')
                ->willReturn(true);

            $command = new NotificationTestCommand($notificationService);

            $application = new Application();
            $application->add($command);

            $commandTester = new CommandTester($application->find('app:notification:test'));

            $exitCode = $commandTester->execute([
                'userId' => 'user123',
                'type' => $type,
            ]);

            $this->assertEquals(Command::SUCCESS, $exitCode);
        }
    }
}
