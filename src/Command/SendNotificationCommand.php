<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Stamp\NotificationTemplateStamp;

#[AsCommand(
    name: 'notification-tracker:send-notification',
    description: 'Interactive notification sender - choose type and send through transport'
)]
class SendNotificationCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Notification type (email, sms, chat, push)')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Send asynchronously through transport')
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🚀 Notification Tracker - Interactive Sender');
        
        // Choose notification type
        $type = $input->getOption('type');
        if (!$type) {
            $type = $io->choice('What type of notification would you like to send?', [
                'email' => 'Email Message',
                'sms' => 'SMS Message',
                'chat' => 'Chat Message (Slack/Teams/Discord)',
                'push' => 'Push Notification'
            ]);
        }

        $async = $input->getOption('async');
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $priority = (int) $input->getOption('priority');

        // Get additional options
        if (!$async) {
            $async = $io->confirm('Send asynchronously through transport?', true);
        }

        if (!$campaign) {
            $campaign = $io->ask('Campaign identifier (optional)');
        }

        if (!$template) {
            $template = $io->ask('Template identifier (optional)');
        }

        $io->section("Sending {$type} notification");

        try {
            switch ($type) {
                case 'email':
                    return $this->sendEmail($io, $async, $campaign, $template, $priority);
                
                case 'sms':
                    return $this->sendSms($io, $async, $campaign, $template, $priority);
                
                case 'chat':
                    return $this->sendChat($io, $async, $campaign, $template, $priority);
                
                case 'push':
                    return $this->sendPush($io, $async, $campaign, $template, $priority);
                
                default:
                    $io->error("Unknown notification type: {$type}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Failed to send notification: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function sendEmail(SymfonyStyle $io, bool $async, ?string $campaign, ?string $template, int $priority): int
    {
        $to = $io->ask('Recipient email address', 'test@example.com');
        $from = $io->ask('Sender email address', 'noreply@notification-tracker.local');
        $subject = $io->ask('Email subject', 'Test Email - ' . date('Y-m-d H:i:s'));
        $body = $io->ask('Email body', 'This is a test email sent through the notification tracker transport.');

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->text($body);

        $stamps = $this->createStamps('email', $priority, $campaign, $template);

        $this->messageBus->dispatch(new SendEmailMessage($email), $stamps);
        
        $io->success('✅ Email dispatched!');
        $this->showTrackingInfo($io, $async);
        
        return Command::SUCCESS;
    }

    private function sendSms(SymfonyStyle $io, bool $async, ?string $campaign, ?string $template, int $priority): int
    {
        $to = $io->ask('Recipient phone number (with country code)', '+1234567890');
        $from = $io->ask('Sender phone number or name (optional)');
        $message = $io->ask('SMS message', 'Test SMS sent via notification tracker at ' . date('Y-m-d H:i:s'));

        $smsMessage = new SmsMessage($to, $message, $from);
        $stamps = $this->createStamps('sms', $priority, $campaign, $template);

        $this->messageBus->dispatch($smsMessage, $stamps);
        
        $io->success('✅ SMS dispatched!');
        $this->showTrackingInfo($io, $async);
        
        return Command::SUCCESS;
    }

    private function sendChat(SymfonyStyle $io, bool $async, ?string $campaign, ?string $template, int $priority): int
    {
        $message = $io->ask('Chat message', 'Test chat message sent via notification tracker at ' . date('Y-m-d H:i:s'));
        $subject = $io->ask('Message subject/title (optional)');
        $provider = $io->choice('Chat provider', ['slack', 'teams', 'discord'], 'slack');

        $chatMessage = new ChatMessage($message);
        if ($subject) {
            $chatMessage->subject($subject);
        }

        $stamps = $this->createStamps($provider, $priority, $campaign, $template);

        $this->messageBus->dispatch($chatMessage, $stamps);
        
        $io->success('✅ Chat message dispatched!');
        $this->showTrackingInfo($io, $async);
        
        return Command::SUCCESS;
    }

    private function sendPush(SymfonyStyle $io, bool $async, ?string $campaign, ?string $template, int $priority): int
    {
        $subject = $io->ask('Push notification title', 'Test Push Notification');
        $content = $io->ask('Push notification content', 'This is a test push notification sent at ' . date('Y-m-d H:i:s'));

        $pushMessage = new PushMessage($subject, $content);
        $stamps = $this->createStamps('push', $priority, $campaign, $template);

        $this->messageBus->dispatch($pushMessage, $stamps);
        
        $io->success('✅ Push notification dispatched!');
        $this->showTrackingInfo($io, $async);
        
        return Command::SUCCESS;
    }

    private function createStamps(string $provider, int $priority, ?string $campaign, ?string $template): array
    {
        $stamps = [
            new NotificationProviderStamp($provider, $priority)
        ];

        if ($campaign) {
            $stamps[] = new NotificationCampaignStamp($campaign);
        }

        if ($template) {
            $stamps[] = new NotificationTemplateStamp($template);
        }

        return $stamps;
    }

    private function showTrackingInfo(SymfonyStyle $io, bool $async): void
    {
        if ($async) {
            $io->note([
                'The notification has been queued for processing.',
                'Check queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
                'Start consumer: php bin/console messenger:consume notification -vv'
            ]);
        }
        
        $io->info('💡 Check tracking data at: http://localhost:8001/api/notification-tracker/messages');
    }
}
