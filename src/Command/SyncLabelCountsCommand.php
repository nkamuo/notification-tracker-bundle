<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Label;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification-tracker:sync-label-counts',
    description: 'Synchronize label notification counts with actual data'
)]
class SyncLabelCountsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Synchronizing Label Notification Counts');
        
        $labelRepository = $this->entityManager->getRepository(Label::class);
        $labels = $labelRepository->findAll();
        
        $totalLabels = count($labels);
        $updatedCount = 0;
        
        $io->progressStart($totalLabels);
        
        foreach ($labels as $label) {
            $actualCount = $label->getNotifications()->count();
            $currentCount = $label->getNotificationCount();
            
            if ($currentCount !== $actualCount) {
                $label->setNotificationCount($actualCount);
                $updatedCount++;
                
                $io->writeln(sprintf(
                    '<comment>Updated label "%s": %d -> %d notifications</comment>',
                    $label->getName(),
                    $currentCount,
                    $actualCount
                ), OutputInterface::VERBOSITY_VERBOSE);
            }
            
            $io->progressAdvance();
        }
        
        if ($updatedCount > 0) {
            $this->entityManager->flush();
            $io->progressFinish();
            $io->success(sprintf('Updated %d out of %d labels', $updatedCount, $totalLabels));
        } else {
            $io->progressFinish();
            $io->success('All label counts are already synchronized!');
        }
        
        return Command::SUCCESS;
    }
}
