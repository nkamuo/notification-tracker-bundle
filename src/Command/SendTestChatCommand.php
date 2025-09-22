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
use Symfony\Component\Notifier\Message\ChatMessage;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;

#[AsCommand(
    name: 'notification-tracker:send-chat',
    description: 'Send a test chat message (Slack/Teams/Discord) through the notification transport'
)]
class SendTestChatCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('message', InputArgument::REQUIRED, 'Chat message text')
            ->addOption('channel', null, InputOption::VALUE_OPTIONAL, 'Chat channel/room (e.g., #general)')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Message subject/title')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'Chat provider (slack, teams, discord)', 'slack')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Send asynchronously through transport')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $messageText = $input->getArgument('message');
        $channel = $input->getOption('channel');
        $subject = $input->getOption('subject');
        $priority = (int) $input->getOption('priority');
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $provider = $input->getOption('provider');
        $async = $input->getOption('async');

        $io->title('💬 Sending Test Chat Message via Notification Transport');

        // Create chat message
        $chatMessage = new ChatMessage($messageText);
        
        if ($subject) {
            $chatMessage->subject($subject);
        }

        // Create stamps
        $stamps = [
            new NotificationProviderStamp($provider, $priority)
        ];

        if ($campaign) {
            $stamps[] = new NotificationCampaignStamp($campaign);
        }

        if ($template) {
            $stamps[] = new NotificationTemplateStamp($template);
        }

        // Display chat message details
        $io->section('Chat Message Details');
        $io->table(['Property', 'Value'], [
            ['Message', $messageText],
            ['Channel', $channel ?: 'Default'],
            ['Subject', $subject ?: 'None'],
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

        if ($io->confirm('Send this chat message?', true)) {
            try {
                if ($async) {
                    // Send through transport (asynchronous)
                    $this->messageBus->dispatch($chatMessage, $stamps);
                    $io->success('✅ Chat message dispatched through notification transport!');
                    
                    $io->note([
                        'The chat message has been queued for processing.',
                        'Check queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
                        'Start consumer: php bin/console messenger:consume notification -vv'
                    ]);
                } else {
                    // Send directly (synchronous) - will still be tracked by event subscribers
                    $this->messageBus->dispatch($chatMessage, $stamps);
                    $io->success('✅ Chat message sent directly (tracked by event subscribers)!');
                }

                $io->info('💡 Check tracking data at: http://localhost:8001/api/notification-tracker/messages');

            } catch (\Exception $e) {
                $io->error('Failed to send chat message: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $io->info('Chat message sending cancelled.');
        }

        return Command::SUCCESS;
    }
}
