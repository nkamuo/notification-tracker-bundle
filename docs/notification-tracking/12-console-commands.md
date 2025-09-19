# Console Commands for Management

## Process Failed Messages Command

```php
<?php
// src/Command/Communication/ProcessFailedMessagesCommand.php

namespace App\Command\Communication;

use App\Entity\Communication\Message;
use App\Service\Communication\MessageRetryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:messages:process-failed',
    description: 'Process and retry failed messages',
)]
class ProcessFailedMessagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageRetryService $retryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of messages to process', 100)
            ->addOption('older-than', 'o', InputOption::VALUE_REQUIRED, 'Process messages older than X hours', 1)
            ->addOption('max-retries', 'm', InputOption::VALUE_REQUIRED, 'Skip messages with more than X retries', 5)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without actually retrying messages')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $olderThan = (int) $input->getOption('older-than');
        $maxRetries = (int) $input->getOption('max-retries');
        $dryRun = $input->getOption('dry-run');

        $io->title('Processing Failed Messages');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No messages will be actually retried');
        }

        // Find failed messages
        $messages = $this->findFailedMessages($limit, $olderThan, $maxRetries);

        if (empty($messages)) {
            $io->success('No failed messages to process');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d failed messages to process', count($messages)));

        $progressBar = $io->createProgressBar(count($messages));
        $progressBar->start();

        $stats = [
            'retried' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($messages as $message) {
            try {
                if (!$dryRun) {
                    $result = $this->retryService->retryMessage($message);
                    
                    if ($result) {
                        $stats['retried']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    $io->writeln(sprintf(
                        "\nWould retry message %s (type: %s, retries: %d)",
                        $message->getId(),
                        $message->getType(),
                        $message->getRetryCount()
                    ));
                    $stats['retried']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $io->error(sprintf(
                    'Failed to retry message %s: %s',
                    $message->getId(),
                    $e->getMessage()
                ));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Display statistics
        $io->table(
            ['Status', 'Count'],
            [
                ['Retried', $stats['retried']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        $io->success('Failed message processing completed');

        return Command::SUCCESS;
    }

    private function findFailedMessages(int $limit, int $olderThanHours, int $maxRetries): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('m')
            ->from(Message::class, 'm')
            ->where('m.status = :status')
            ->andWhere('m.retryCount < :maxRetries')
            ->andWhere('m.updatedAt < :olderThan')
            ->setParameter('status', Message::STATUS_FAILED)
            ->setParameter('maxRetries', $maxRetries)
            ->setParameter('olderThan', new \DateTimeImmutable("-{$olderThanHours} hours"))
            ->orderBy('m.updatedAt', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
```

## Message Analytics Command

```php
<?php
// src/Command/Communication/MessageAnalyticsCommand.php

namespace App\Command\Communication;

use App\Service\Communication\MessageAnalytics;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:messages:analytics',
    description: 'Display message analytics and statistics',
)]
class MessageAnalyticsCommand extends Command
{
    public function __construct(
        private MessageAnalytics $analytics
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'Time period (hour, day, week, month)', 'day')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Message type filter (email, sms, slack, telegram)')
            ->addOption('export', 'e', InputOption::VALUE_REQUIRED, 'Export to file (csv, json)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $input->getOption('period');
        $type = $input->getOption('type');
        $exportFormat = $input->getOption('export');

        $io->title('Message Analytics Report');

        // Get analytics data
        $stats = $this->analytics->getStatistics($period, $type);

        // Display overview
        $io->section('Overview');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Messages', $stats['total']],
                ['Sent', $stats['sent']],
                ['Delivered', $stats['delivered']],
                ['Failed', $stats['failed']],
                ['Pending', $stats['pending']],
                ['Delivery Rate', sprintf('%.2f%%', $stats['delivery_rate'])],
            ]
        );

        // Display by type
        if (!$type && !empty($stats['by_type'])) {
            $io->section('By Message Type');
            $typeData = [];
            foreach ($stats['by_type'] as $msgType => $typeStats) {
                $typeData[] = [
                    ucfirst($msgType),
                    $typeStats['total'],
                    $typeStats['sent'],
                    $typeStats['delivered'],
                    $typeStats['failed'],
                    sprintf('%.2f%%', $typeStats['delivery_rate']),
                ];
            }
            $io->table(
                ['Type', 'Total', 'Sent', 'Delivered', 'Failed', 'Delivery Rate'],
                $typeData
            );
        }

        // Display email-specific stats
        if (($type === 'email' || !$type) && !empty($stats['email_stats'])) {
            $io->section('Email Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Open Rate', sprintf('%.2f%%', $stats['email_stats']['open_rate'])],
                    ['Click Rate', sprintf('%.2f%%', $stats['email_stats']['click_rate'])],
                    ['Bounce Rate', sprintf('%.2f%%', $stats['email_stats']['bounce_rate'])],
                    ['Complaint Rate', sprintf('%.2f%%', $stats['email_stats']['complaint_rate'])],
                ]
            );
        }

        // Display top failures
        if (!empty($stats['top_failures'])) {
            $io->section('Top Failure Reasons');
            $failureData = [];
            foreach ($stats['top_failures'] as $reason => $count) {
                $failureData[] = [$reason, $count];
            }
            $io->table(['Reason', 'Count'], $failureData);
        }

        // Export if requested
        if ($exportFormat) {
            $this->exportData($stats, $exportFormat, $io);
        }

        $io->success('Analytics report generated successfully');

        return Command::SUCCESS;
    }

    private function exportData(array $stats, string $format, SymfonyStyle $io): void
    {
        $filename = sprintf('message_analytics_%s.%s', date('Y-m-d_H-i-s'), $format);

        switch ($format) {
            case 'json':
                file_put_contents($filename, json_encode($stats, JSON_PRETTY_PRINT));
                break;
            
            case 'csv':
                $this->exportToCsv($stats, $filename);
                break;
            
            default:
                $io->error(sprintf('Unsupported export format: %s', $format));
                return;
        }

        $io->success(sprintf('Data exported to %s', $filename));
    }

    private function exportToCsv(array $stats, string $filename): void
    {
        $fp = fopen($filename, 'w');
        
        // Write overview
        fputcsv($fp, ['Metric', 'Value']);
        foreach (['total', 'sent', 'delivered', 'failed', 'pending', 'delivery_rate'] as $key) {
            fputcsv($fp, [$key, $stats[$key] ?? 0]);
        }
        
        fclose($fp);
    }
}
```