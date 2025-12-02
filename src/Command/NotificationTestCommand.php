<?php

namespace App\Command;

use App\Service\NotificationServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notification:test',
    description: 'Add a short description for your command',
)]
class NotificationTestCommand extends Command
{
    public function __construct(protected NotificationServiceInterface $notificationService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userId', InputArgument::REQUIRED, "Identifiant de l'utilisateur à notifier")
            ->addArgument('type', InputArgument::REQUIRED, 'Type de notification à envoyer')
            ->addOption("dry-run", "d", InputOption::VALUE_NONE, "Pour simuler l'envoi sans réellement envoyer")
            ->addUsage("bin/console app:notification:test un_userid alert");

        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('userId');
        $type = $input->getArgument('type');

        if ($input->getOption('dry-run')) {
            $this->notificationService->setDryRun(true);
        }

        try {
            $resultat = $this->notificationService->sendNotification($userId, $type, "Notification de test");
            if($resultat) {
                $io->success("La notification est bien partie");
            }else{
                $io->writeln("\033[35mIl y a eu une erreur lors de la notification\033[0m");
                $io->error("Notification non envoyée");
                return Command::FAILURE;
            }

        } catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }


        return Command::SUCCESS;
    }
}
