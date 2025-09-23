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
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Messenger\SentMessage;
use Symfony\Component\Uid\Ulid;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;

#[AsCommand(
    name: 'notification-tracker:send-sms',
    description: 'Send a test SMS through the notification transport'
)]
class SendTestSmsCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Recipient phone number (with country code)')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Sender phone number or name')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'SMS message text')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'SMS provider', 'sms')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Send asynchronously through transport')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $to = $input->getArgument('to');
        $from = $input->getOption('from');
        $messageText = $input->getOption('message') ?? 'Test SMS sent via notification tracker transport at ' . date('Y-m-d H:i:s');
        $priority = (int) $input->getOption('priority');
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $provider = $input->getOption('provider');
        $async = $input->getOption('async');

        $io->title('📱 Sending Test SMS via Notification Transport');

        // Create SMS message
        $smsMessage = new SmsMessage($to, $messageText, $from);

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

        // Display SMS details
        $io->section('SMS Details');
        $io->table(['Property', 'Value'], [
            ['To', $to],
            ['From', $from ?: 'Default'],
            ['Message', $messageText],
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

        if ($io->confirm('Send this SMS?', true)) {
            try {
                if ($async) {
                    // Send through transport (asynchronous)
                    $this->messageBus->dispatch($smsMessage, $stamps);
                    $io->success('✅ SMS dispatched through notification transport!');
                    
                    $io->note([
                        'The SMS has been queued for processing.',
                        'Check queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
                        'Start consumer: php bin/console messenger:consume notification -vv'
                    ]);
                } else {
                    // Send directly (synchronous) - will still be tracked by event subscribers
                    $this->messageBus->dispatch($smsMessage, $stamps);
                    $io->success('✅ SMS sent directly (tracked by event subscribers)!');
                }

                $io->info('💡 Check tracking data at: http://localhost:8001/api/notification-tracker/messages');

            } catch (\Exception $e) {
                $io->error('Failed to send SMS: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $io->info('SMS sending cancelled.');
        }

        return Command::SUCCESS;
    }
}
