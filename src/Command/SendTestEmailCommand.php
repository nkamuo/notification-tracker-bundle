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
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Ulid;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationProviderStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationCampaignStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTemplateStamp;
use Nkamuo\NotificationTrackerBundle\Messenger\Stamp\NotificationTrackingStamp;

#[AsCommand(
    name: 'notification-tracker:send-email',
    description: 'Send a test email through the notification transport'
)]
class SendTestEmailCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Recipient email address')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Sender email address', 'test@notification-tracker.local')
            ->addOption('subject', null, InputOption::VALUE_OPTIONAL, 'Email subject')
            ->addOption('body', null, InputOption::VALUE_OPTIONAL, 'Email body text')
            ->addOption('html', null, InputOption::VALUE_OPTIONAL, 'Email HTML body')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'Email provider', 'email')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Send asynchronously through transport')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $to = $input->getArgument('to');
        $from = $input->getOption('from');
        $subject = $input->getOption('subject') ?? 'Test Email - ' . date('Y-m-d H:i:s');
        $bodyText = $input->getOption('body') ?? 'This is a test email sent through the notification tracker transport.';
        $bodyHtml = $input->getOption('html');
        $priority = (int) $input->getOption('priority');
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $provider = $input->getOption('provider');
        $async = $input->getOption('async');

        $io->title('📧 Sending Test Email via Notification Transport');

        // Create email
        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->text($bodyText);

        if ($bodyHtml) {
            $email->html($bodyHtml);
        }

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

        // Display email details
        $io->section('Email Details');
        $io->table(['Property', 'Value'], [
            ['From', $from],
            ['To', $to],
            ['Subject', $subject],
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

        if ($io->confirm('Send this email?', true)) {
            try {
                if ($async) {
                    // Send through transport (asynchronous)
                    $this->messageBus->dispatch(new SendEmailMessage($email), $stamps);
                    $io->success('✅ Email dispatched through notification transport!');
                    
                    $io->note([
                        'The email has been queued for processing.',
                        'Check queue: curl http://localhost:8001/api/notification-tracker/queue/messages',
                        'Start consumer: php bin/console messenger:consume notification -vv'
                    ]);
                } else {
                    // Send directly (synchronous) - will still be tracked by event subscribers
                    $this->messageBus->dispatch(new SendEmailMessage($email), $stamps);
                    $io->success('✅ Email sent directly (tracked by event subscribers)!');
                }

                $io->info('💡 Check tracking data at: http://localhost:8001/api/notification-tracker/messages');

            } catch (\Exception $e) {
                $io->error('Failed to send email: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $io->info('Email sending cancelled.');
        }

        return Command::SUCCESS;
    }
}
