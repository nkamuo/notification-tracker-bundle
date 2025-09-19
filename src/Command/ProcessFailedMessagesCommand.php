<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Service\MessageRetryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification-tracker:process-failed',
    description: 'Process and retry failed messages',
)]
class ProcessFailedMessagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageRetryService $retryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of messages to process', 100)
            ->addOption('older-than', 'o', InputOption::VALUE_REQUIRED, 'Process messages older than X hours', 1)
            ->addOption('max-retries', 'm', InputOption::VALUE_REQUIRED, 'Skip messages with more than X retries', 5)
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by message type (email, sms, slack, telegram, push)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without actually retrying messages')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $olderThan = (int) $input->getOption('older-than');
        $maxRetries = (int) $input->getOption('max-retries');
        $type = $input->getOption('type');
        $dryRun = $input->getOption('dry-run');

        $io->title('Processing Failed Messages');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No messages will be actually retried');
        }

        $messages = $this->findFailedMessages($limit, $olderThan, $maxRetries, $type);

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

    private function findFailedMessages(int $limit, int $olderThanHours, int $maxRetries, ?string $type): array
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

        if ($type) {
            $qb->andWhere('TYPE(m) = :type')
               ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }
}