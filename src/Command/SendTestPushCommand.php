<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Uid\Ulid;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;

#[AsCommand(
    name: 'notification-tracker:send-push',
    description: 'Send a test push notification through the notification transport'
)]
class SendTestPushCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'Push notification title/subject')
            ->addArgument('content', InputArgument::REQUIRED, 'Push notification content/body')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'Push provider (firebase, apns)', 'push')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Send asynchronously through transport')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $subject = $input->getArgument('subject');
        $content = $input->getArgument('content');
        $priority = (int) $input->getOption('priority');
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $provider = $input->getOption('provider');
        $async = $input->getOption('async');

        $io->title('📱 Sending Test Push Notification via Notification Transport');

        // Create push message
        $pushMessage = new PushMessage($subject, $content);

        // Create stamps
        $stamps = [
            new NotificationProviderStamp($provider, $priority),
            // Always add a tracking stamp for consistent tracking regardless of sync/async mode
            new NotificationTrackingStamp((string) new \Symfony\Component\Uid\Ulid())
        ];

        if ($campaign) {
            $stamps[] = new NotificationCampaignStamp($campaign);
        }

        if ($template) {
            $stamps[] = new NotificationTemplateStamp($template);
        }

        // Display push notification details
        $io->section('Push Notification Details');
        $io->table(['Property', 'Value'], [
            ['Subject/Title', $subject],
            ['Content/Body', $content],
            ['Priority', $priority],
            ['Provider', $provider],
            ['Campaign', $campaign ?: 'None'],
            ['Template', $template ?: 'None'],
            ['Transport Mode', $async ? 'Asynchronous (via transport)' : 'Direct (bypasses transport)'],
        ]);

        $io->section('Stamps Applied');
        foreach ($stamps as $stamp) {
            $io->text('• ' . get_class($stamp));
        }

        if ($io->confirm('Send this push notification?', true)) {
            try {
                if ($async) {
                    // Send through transport (asynchronous)
                    $this->messageBus->dispatch($pushMessage, $stamps);
                    $io->success('✅ Push notification dispatched through notification transport!');
                    
                    $io->note([
                        'The push notification has been queued for processing.',
                        'Check queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
                        'Start consumer: php bin/console messenger:consume notification -vv'
                    ]);
                } else {
                    // Send directly (synchronous) - will still be tracked by event subscribers
                    $this->messageBus->dispatch($pushMessage, $stamps);
                    $io->success('✅ Push notification sent directly (tracked by event subscribers)!');
                }

                $io->info('💡 Check tracking data at: http://localhost:8001/api/notification-tracker/messages');

            } catch (\Exception $e) {
                $io->error('Failed to send push notification: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $io->info('Push notification sending cancelled.');
        }

        return Command::SUCCESS;
    }
}
