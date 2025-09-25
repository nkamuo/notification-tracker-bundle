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
use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationImportance;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'notification-tracker:send-bulk-email',
    description: 'Send an email to multiple recipients with CC/BCC support'
)]
class SendEmailCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'Email subject')
            ->addArgument('content', InputArgument::REQUIRED, 'Email content (text)')
            
            // Recipients
            ->addOption('to', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'TO recipients (can be used multiple times)')
            ->addOption('cc', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'CC recipients (can be used multiple times)')
            ->addOption('bcc', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'BCC recipients (can be used multiple times)')
            
            // Email settings
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Sender email address', 'test@notification-tracker.local')
            ->addOption('reply-to', 'r', InputOption::VALUE_REQUIRED, 'Reply-to email address')
            ->addOption('html', null, InputOption::VALUE_REQUIRED, 'HTML content (if different from text content)')
            
            // Notification settings
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Notification type', 'email')
            ->addOption('importance', 'i', InputOption::VALUE_REQUIRED, 'Importance level (low,normal,high,urgent)', 'normal')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Message priority (1-10)', '5')
            
            // Mode options
            ->addOption('draft', 'd', InputOption::VALUE_NONE, 'Save as draft instead of sending')
            ->addOption('schedule', 's', InputOption::VALUE_REQUIRED, 'Schedule for later (ISO 8601 format: 2024-12-25T10:00:00)')
            
            // Advanced options
            ->addOption('campaign', 'c', InputOption::VALUE_OPTIONAL, 'Campaign identifier')
            ->addOption('template', null, InputOption::VALUE_OPTIONAL, 'Template identifier')
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'Email provider', 'email')
            ->addOption('metadata', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional metadata as key=value pairs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be sent without actually sending')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Get inputs
        $subject = $input->getArgument('subject');
        $content = $input->getArgument('content');
        
        $toRecipients = $input->getOption('to') ?: [];
        $ccRecipients = $input->getOption('cc') ?: [];
        $bccRecipients = $input->getOption('bcc') ?: [];
        
        $from = $input->getOption('from');
        $replyTo = $input->getOption('reply-to');
        $htmlContent = $input->getOption('html');
        
        $type = $input->getOption('type');
        $importance = $input->getOption('importance');
        $priority = (int) $input->getOption('priority');
        
        $isDraft = $input->getOption('draft');
        $scheduleTime = $input->getOption('schedule');
        
        $campaign = $input->getOption('campaign');
        $template = $input->getOption('template');
        $provider = $input->getOption('provider');
        $dryRun = $input->getOption('dry-run');
        
        // Validate inputs
        if (empty($toRecipients) && empty($ccRecipients) && empty($bccRecipients)) {
            $io->error('At least one recipient must be specified (--to, --cc, or --bcc)');
            return Command::FAILURE;
        }

        try {
            NotificationImportance::from($importance);
        } catch (\ValueError $e) {
            $io->error("Invalid importance level: {$importance}. Valid options: " . implode(', ', NotificationImportance::values()));
            return Command::FAILURE;
        }

        // Parse schedule time if provided
        $scheduledAt = null;
        if ($scheduleTime) {
            try {
                $scheduledAt = new \DateTimeImmutable($scheduleTime);
            } catch (\Exception $e) {
                $io->error("Invalid schedule time format: {$scheduleTime}. Use ISO 8601 format (e.g., 2024-12-25T10:00:00)");
                return Command::FAILURE;
            }
        }

        // Parse metadata
        $metadata = [];
        foreach ($input->getOption('metadata') as $pair) {
            if (strpos($pair, '=') === false) {
                $io->warning("Ignoring invalid metadata format: {$pair}. Use key=value format.");
                continue;
            }
            [$key, $value] = explode('=', $pair, 2);
            $metadata[trim($key)] = trim($value);
        }

        // Build recipients array
        $recipients = [];
        foreach ($toRecipients as $email) {
            $recipients[] = ['email' => $email, 'type' => 'to'];
        }
        foreach ($ccRecipients as $email) {
            $recipients[] = ['email' => $email, 'type' => 'cc'];
        }
        foreach ($bccRecipients as $email) {
            $recipients[] = ['email' => $email, 'type' => 'bcc'];
        }

        // Determine status
        $status = NotificationStatus::DRAFT;
        if (!$isDraft) {
            if ($scheduledAt) {
                $status = NotificationStatus::SCHEDULED;
            } else {
                $status = NotificationStatus::QUEUED;
            }
        }

        // Display summary
        $io->title('📧 Email Summary');
        $this->displayEmailSummary($io, [
            'subject' => $subject,
            'content' => $content,
            'htmlContent' => $htmlContent,
            'from' => $from,
            'replyTo' => $replyTo,
            'recipients' => $recipients,
            'type' => $type,
            'importance' => $importance,
            'priority' => $priority,
            'status' => $status,
            'scheduledAt' => $scheduledAt,
            'campaign' => $campaign,
            'template' => $template,
            'provider' => $provider,
            'metadata' => $metadata,
        ], $isDraft, $dryRun);
        
        if ($dryRun) {
            $io->success('✅ Dry run completed - email would be processed with the above settings');
            return Command::SUCCESS;
        }

        if (!$io->confirm($isDraft ? 'Create this email draft?' : 'Send this email?', true)) {
            $io->info('Email operation cancelled.');
            return Command::SUCCESS;
        }

        try {
            if ($isDraft || $scheduledAt) {
                // Create notification entity for drafts and scheduled emails
                $notification = $this->createNotificationEntity([
                    'type' => $type,
                    'subject' => $subject,
                    'content' => $content,
                    'channels' => ['email'],
                    'recipients' => $recipients,
                    'importance' => $importance,
                    'status' => $status,
                    'scheduledAt' => $scheduledAt,
                    'channelSettings' => [
                        'email' => array_filter([
                            'from_email' => $from,
                            'reply_to' => $replyTo,
                            'html_content' => $htmlContent,
                        ])
                    ],
                    'metadata' => array_merge($metadata, array_filter([
                        'campaign' => $campaign,
                        'template' => $template,
                        'provider' => $provider,
                        'priority' => $priority,
                    ]))
                ]);

                if ($isDraft) {
                    $io->success("✅ Email saved as draft!");
                    $io->info("Notification ID: {$notification->getId()}");
                    $io->note("To send later, update the status to QUEUED or SCHEDULED");
                } else {
                    $io->success("✅ Email scheduled for {$scheduledAt->format('Y-m-d H:i:s T')}!");
                    $io->info("Notification ID: {$notification->getId()}");
                }
            } else {
                // Send immediately via messenger
                $this->sendImmediateEmail([
                    'subject' => $subject,
                    'content' => $content,
                    'htmlContent' => $htmlContent,
                    'from' => $from,
                    'replyTo' => $replyTo,
                    'recipients' => $recipients,
                    'campaign' => $campaign,
                    'template' => $template,
                    'provider' => $provider,
                    'priority' => $priority,
                ]);

                $io->success('✅ Email dispatched for immediate sending!');
                $io->note([
                    'Track messages at: http://localhost:8001/api/notification-tracker/messages',
                    'Start consumer: php bin/console messenger:consume notification -vv'
                ]);
            }

        } catch (\Exception $e) {
            $io->error('Failed to process email: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function createNotificationEntity(array $data): Notification
    {
        $notification = new Notification();
        $notification->setType($data['type']);
        $notification->setSubject($data['subject']);
        $notification->setContent($data['content']);
        $notification->setChannels($data['channels']);
        $notification->setRecipients($data['recipients']);
        $notification->setImportance($data['importance']);
        $notification->setStatus($data['status']);
        $notification->setDirection(NotificationDirection::OUTBOUND);
        $notification->setChannelSettings($data['channelSettings']);
        $notification->setMetadata($data['metadata']);

        if ($data['scheduledAt']) {
            $notification->setScheduledAt($data['scheduledAt']);
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    private function sendImmediateEmail(array $data): void
    {
        // Create email for each TO recipient (CC and BCC would need to be handled per message)
        foreach ($data['recipients'] as $recipient) {
            if ($recipient['type'] === 'to') {
                $email = (new Email())
                    ->from($data['from'])
                    ->to($recipient['email'])
                    ->subject($data['subject'])
                    ->text($data['content']);

                if ($data['htmlContent']) {
                    $email->html($data['htmlContent']);
                }

                if ($data['replyTo']) {
                    $email->replyTo($data['replyTo']);
                }

                // Add CC and BCC recipients to this message
                foreach ($data['recipients'] as $ccRecipient) {
                    if ($ccRecipient['type'] === 'cc') {
                        $email->cc($ccRecipient['email']);
                    } elseif ($ccRecipient['type'] === 'bcc') {
                        $email->bcc($ccRecipient['email']);
                    }
                }

                // Create stamps
                $stamps = [
                    new NotificationProviderStamp($data['provider'], $data['priority']),
                    new NotificationTrackingStamp((string) new Ulid())
                ];

                if ($data['campaign']) {
                    $stamps[] = new NotificationCampaignStamp($data['campaign']);
                }

                if ($data['template']) {
                    $stamps[] = new NotificationTemplateStamp($data['template']);
                }

                $this->messageBus->dispatch(new SendEmailMessage($email), $stamps);
                break; // Only send one email with all recipients
            }
        }
    }

    private function displayEmailSummary(SymfonyStyle $io, array $data, bool $isDraft, bool $isDryRun): void
    {
        if ($isDryRun) {
            $io->note('🔍 DRY RUN - No email will be sent');
        }

        $statusLabel = $isDraft ? 'Draft' : ($data['scheduledAt'] ? 'Scheduled' : 'Ready to Send');
        
        $io->section('Basic Information');
        $io->table(['Property', 'Value'], [
            ['Subject', $data['subject']],
            ['Content', strlen($data['content']) > 100 ? substr($data['content'], 0, 97) . '...' : $data['content']],
            ['From', $data['from']],
            ['Reply-To', $data['replyTo'] ?: 'None'],
            ['Type', $data['type']],
            ['Importance', ucfirst($data['importance'])],
            ['Priority', $data['priority']],
            ['Status', $statusLabel],
            ['Provider', $data['provider']],
        ]);

        if ($data['htmlContent']) {
            $io->section('HTML Content');
            $io->text('📄 HTML content provided (length: ' . strlen($data['htmlContent']) . ' characters)');
        }

        if ($data['scheduledAt']) {
            $io->section('Scheduling');
            $io->text("⏰ Scheduled for: {$data['scheduledAt']->format('Y-m-d H:i:s T')}");
        }

        $io->section('Recipients');
        $recipientsByType = [];
        foreach ($data['recipients'] as $recipient) {
            $type = strtoupper($recipient['type']);
            $recipientsByType[$type][] = $recipient['email'];
        }
        
        foreach ($recipientsByType as $type => $emails) {
            $io->text("• {$type}: " . implode(', ', $emails));
        }

        if (!empty($data['metadata'])) {
            $io->section('Metadata');
            foreach ($data['metadata'] as $key => $value) {
                if ($value) {
                    $io->text("• {$key}: {$value}");
                }
            }
        }
    }
}
