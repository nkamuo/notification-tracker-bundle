<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\NotificationDraft;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification-tracker:send-scheduled',
    description: 'Send scheduled notification drafts'
)]
class SendScheduledDraftsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationSender $notificationSender,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Processing Scheduled Notifications');

        // Find all scheduled drafts that are due
        $dueDate = new \DateTimeImmutable();
        $repository = $this->entityManager->getRepository(NotificationDraft::class);
        
        $scheduledDrafts = $repository->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.scheduledAt <= :dueDate')
            ->setParameter('status', NotificationDraft::STATUS_SCHEDULED)
            ->setParameter('dueDate', $dueDate)
            ->getQuery()
            ->getResult();

        if (empty($scheduledDrafts)) {
            $io->success('No scheduled notifications to process.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d scheduled notifications to process.', count($scheduledDrafts)));

        $processed = 0;
        $failed = 0;

        foreach ($scheduledDrafts as $draft) {
            try {
                $io->text(sprintf('Sending notification: %s (ID: %s)', $draft->getSubject(), $draft->getId()));
                
                $draft->setStatus(NotificationDraft::STATUS_SENDING);
                $this->entityManager->flush();

                $result = $this->notificationSender->sendDraft($draft);
                
                $draft->setStatus(NotificationDraft::STATUS_SENT);
                $draft->setSentAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();
                
                $processed++;
                
                $io->text(sprintf(
                    '  ✓ Sent successfully: %d messages to %d recipients across %d channels',
                    $result['messages_created'],
                    $result['recipients_notified'],
                    count($result['channels_used'])
                ));

                $this->logger->info('Scheduled notification sent successfully', [
                    'draft_id' => (string) $draft->getId(),
                    'subject' => $draft->getSubject(),
                    'messages_created' => $result['messages_created'],
                    'recipients_notified' => $result['recipients_notified'],
                ]);
                
            } catch (\Exception $e) {
                $draft->setStatus(NotificationDraft::STATUS_FAILED);
                $draft->setFailureReason($e->getMessage());
                
                $this->entityManager->flush();
                
                $failed++;
                
                $io->error(sprintf('  ✗ Failed: %s', $e->getMessage()));
                
                $this->logger->error('Failed to send scheduled notification', [
                    'draft_id' => (string) $draft->getId(),
                    'subject' => $draft->getSubject(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($processed > 0) {
            $io->success(sprintf('Successfully processed %d notifications.', $processed));
        }
        
        if ($failed > 0) {
            $io->warning(sprintf('%d notifications failed to send.', $failed));
        }

        return Command::SUCCESS;
    }
}
