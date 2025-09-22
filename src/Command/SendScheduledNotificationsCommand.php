<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Service\NotificationSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification-tracker:send-scheduled',
    description: 'Send scheduled notifications that are due',
)]
class SendScheduledNotificationsCommand extends Command
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
        
        $repository = $this->entityManager->getRepository(Notification::class);
        
        // Find scheduled notifications that are due
        $scheduledNotifications = $repository->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt <= :now')
            ->setParameter('status', Notification::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        if (empty($scheduledNotifications)) {
            $io->success('No scheduled notifications found to send.');
            return Command::SUCCESS;
        }

        $processed = 0;
        $successful = 0;
        $failed = 0;

        $io->progressStart(count($scheduledNotifications));

        foreach ($scheduledNotifications as $notification) {
            try {
                $io->text("Processing notification: {$notification->getSubject()}");
                
                // Update status to sending to prevent duplicate processing
                $notification->setStatus(Notification::STATUS_SENDING);
                $this->entityManager->flush();

                // Send the notification
                $result = $this->notificationSender->sendNotification($notification);
                
                if ($result['success']) {
                    $notification->setStatus(Notification::STATUS_SENT);
                    $successful++;
                    $io->text("✅ Sent successfully");
                } else {
                    $notification->setStatus(Notification::STATUS_FAILED);
                    $failed++;
                    $io->text("❌ Failed to send");
                }

                $this->entityManager->flush();
                $processed++;

            } catch (\Exception $e) {
                $this->logger->error('Failed to send scheduled notification', [
                    'notification_id' => (string) $notification->getId(),
                    'subject' => $notification->getSubject(),
                    'scheduled_at' => $notification->getScheduledAt()?->format('Y-m-d H:i:s'),
                    'error' => $e->getMessage(),
                ]);

                $notification->setStatus(Notification::STATUS_FAILED);
                $this->entityManager->flush();
                $failed++;
                $processed++;

                $io->text("❌ Exception: {$e->getMessage()}");
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success([
            'Scheduled notification processing completed!',
            "Processed: {$processed}",
            "Successful: {$successful}",
            "Failed: {$failed}",
        ]);

        return Command::SUCCESS;
    }
}
