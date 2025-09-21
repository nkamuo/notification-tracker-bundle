<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'notification-tracker:queue-status',
    description: 'Show notification transport queue status and statistics'
)]
class QueueStatusCommand extends Command
{
    public function __construct(
        private MessageRepository $messageRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch mode - refresh every 5 seconds')
            ->addOption('details', 'd', InputOption::VALUE_NONE, 'Show detailed message information')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of recent messages to show', '10')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $watch = $input->getOption('watch');
        $showDetails = $input->getOption('details');
        $limit = (int) $input->getOption('limit');

        if ($watch) {
            $io->info('👀 Watching queue status (Ctrl+C to stop)...');
            while (true) {
                $output->write("\033[2J\033[;H"); // Clear screen
                $this->displayQueueStatus($io, $showDetails, $limit);
                sleep(5);
            }
        } else {
            $this->displayQueueStatus($io, $showDetails, $limit);
        }

        return Command::SUCCESS;
    }

    private function displayQueueStatus(SymfonyStyle $io, bool $showDetails, int $limit): void
    {
        $io->title('📊 Notification Transport Queue Status');

        // Get queue statistics
        $stats = $this->getQueueStatistics();
        
        $io->section('Queue Statistics');
        $io->table(['Status', 'Count'], [
            ['Total Messages', $stats['total']],
            ['Pending', $stats['pending']],
            ['Queued', $stats['queued']],
            ['Sent', $stats['sent']],
            ['Failed', $stats['failed']],
            ['Processing', $stats['processing']],
        ]);

        // Show recent messages
        $recentMessages = $this->messageRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );

        if (empty($recentMessages)) {
            $io->info('No messages in queue.');
            return;
        }

        $io->section("Recent Messages (Last {$limit})");
        
        if ($showDetails) {
            // Detailed view
            foreach ($recentMessages as $message) {
                $io->table(['Property', 'Value'], [
                    ['ID', $message->getId()->toString()],
                    ['Type', $message->getType()],
                    ['Status', $message->getStatus()],
                    ['Transport', $message->getTransportName() ?: 'None'],
                    ['Created', $message->getCreatedAt()?->format('Y-m-d H:i:s')],
                    ['Updated', $message->getUpdatedAt()?->format('Y-m-d H:i:s')],
                    ['Retry Count', $message->getRetryCount()],
                    ['Failure Reason', $message->getFailureReason() ? substr($message->getFailureReason(), 0, 100) . '...' : 'None'],
                ]);
                $io->newLine();
            }
        } else {
            // Summary view
            $rows = [];
            foreach ($recentMessages as $message) {
                $rows[] = [
                    substr($message->getId()->toString(), 0, 8) . '...',
                    $message->getType(),
                    $message->getStatus(),
                    $message->getTransportName() ?: 'None',
                    $message->getCreatedAt()?->format('H:i:s'),
                    $message->getRetryCount(),
                ];
            }

            $io->table([
                'ID', 'Type', 'Status', 'Transport', 'Created', 'Retries'
            ], $rows);
        }

        // Show API endpoints
        $io->section('🔗 API Endpoints');
        $io->text([
            '• Queue Messages: curl http://localhost:8001/api/notification-tracker/queue/messages',
            '• Queue Stats: curl http://localhost:8001/api/notification-tracker/queue/stats',
            '• Queue Health: curl http://localhost:8001/api/notification-tracker/queue/health',
            '• All Messages: curl http://localhost:8001/api/notification-tracker/messages',
        ]);

        $io->info('Last updated: ' . date('Y-m-d H:i:s'));
    }

    private function getQueueStatistics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Get counts by status
        $results = $qb
            ->select('m.status, COUNT(m.id) as count')
            ->from('Nkamuo\NotificationTrackerBundle\Entity\Message', 'm')
            ->groupBy('m.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'queued' => 0,
            'sent' => 0,
            'failed' => 0,
            'processing' => 0,
        ];

        foreach ($results as $result) {
            $status = $result['status'];
            $count = (int) $result['count'];
            
            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }
            
            $stats['total'] += $count;
        }

        return $stats;
    }
}
