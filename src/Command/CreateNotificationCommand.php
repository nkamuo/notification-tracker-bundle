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
use Nkamuo\NotificationTrackerBundle\Service\NotificationService;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationStatus;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationDirection;
use Nkamuo\NotificationTrackerBundle\Enum\NotificationImportance;
use Doctrine\ORM\EntityManagerInterface;
use Nkamuo\NotificationTrackerBundle\Entity\Notification;

#[AsCommand(
    name: 'notification-tracker:create-notification',
    description: 'Create a notification with support for multiple recipients, channels, and draft mode'
)]
class CreateNotificationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Notification type (e.g., welcome, alert, newsletter)')
            ->addArgument('subject', InputArgument::REQUIRED, 'Notification subject')
            ->addArgument('content', InputArgument::REQUIRED, 'Notification content/message')
            
            // Recipients
            ->addOption('to', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Primary recipients (can be used multiple times)')
            ->addOption('cc', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'CC recipients (can be used multiple times)')
            ->addOption('bcc', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'BCC recipients (can be used multiple times)')
            
            // Channels
            ->addOption('channels', 'c', InputOption::VALUE_REQUIRED, 'Comma-separated channels (email,sms,slack,telegram,push)', 'email')
            
            // Notification settings
            ->addOption('importance', 'i', InputOption::VALUE_REQUIRED, 'Importance level (low,normal,high,urgent)', 'normal')
            ->addOption('draft', 'd', InputOption::VALUE_NONE, 'Save as draft instead of sending immediately')
            ->addOption('schedule', 's', InputOption::VALUE_REQUIRED, 'Schedule for later (ISO 8601 format: 2024-12-25T10:00:00)')
            
            // Email-specific options
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Sender email address')
            ->addOption('reply-to', 'r', InputOption::VALUE_REQUIRED, 'Reply-to email address')
            ->addOption('html', null, InputOption::VALUE_REQUIRED, 'HTML content (if different from text content)')
            
            // SMS-specific options  
            ->addOption('sms-from', null, InputOption::VALUE_REQUIRED, 'SMS sender number/ID')
            
            // Advanced options
            ->addOption('campaign', null, InputOption::VALUE_REQUIRED, 'Campaign identifier for tracking')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template name to use')
            ->addOption('metadata', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional metadata as key=value pairs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be created without actually creating')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Get inputs
        $type = $input->getArgument('type');
        $subject = $input->getArgument('subject');
        $content = $input->getArgument('content');
        
        $toRecipients = $input->getOption('to') ?: [];
        $ccRecipients = $input->getOption('cc') ?: [];
        $bccRecipients = $input->getOption('bcc') ?: [];
        $channels = array_map('trim', explode(',', $input->getOption('channels')));
        
        $importance = $input->getOption('importance');
        $isDraft = $input->getOption('draft');
        $scheduleTime = $input->getOption('schedule');
        $dryRun = $input->getOption('dry-run');
        
        // Validate inputs
        if (empty($toRecipients) && empty($ccRecipients) && empty($bccRecipients)) {
            $io->error('At least one recipient must be specified (--to, --cc, or --bcc)');
            return Command::FAILURE;
        }

        try {
            $importanceEnum = NotificationImportance::from($importance);
        } catch (\ValueError $e) {
            $io->error("Invalid importance level: {$importance}. Valid options: " . implode(', ', array_column(NotificationImportance::cases(), 'value')));
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
            $recipients[] = [
                'email' => $email,
                'type' => 'to'
            ];
        }
        
        foreach ($ccRecipients as $email) {
            $recipients[] = [
                'email' => $email,
                'type' => 'cc'
            ];
        }
        
        foreach ($bccRecipients as $email) {
            $recipients[] = [
                'email' => $email,
                'type' => 'bcc'
            ];
        }

        // Build channel settings
        $channelSettings = [];
        
        if (in_array('email', $channels)) {
            $emailSettings = [];
            if ($fromEmail = $input->getOption('from')) {
                $emailSettings['from_email'] = $fromEmail;
            }
            if ($replyTo = $input->getOption('reply-to')) {
                $emailSettings['reply_to'] = $replyTo;
            }
            if ($htmlContent = $input->getOption('html')) {
                $emailSettings['html_content'] = $htmlContent;
            }
            if (!empty($emailSettings)) {
                $channelSettings['email'] = $emailSettings;
            }
        }
        
        if (in_array('sms', $channels)) {
            $smsSettings = [];
            if ($smsFrom = $input->getOption('sms-from')) {
                $smsSettings['from_number'] = $smsFrom;
            }
            if (!empty($smsSettings)) {
                $channelSettings['sms'] = $smsSettings;
            }
        }

        // Determine status based on options
        $status = NotificationStatus::DRAFT;
        if (!$isDraft) {
            if ($scheduledAt) {
                $status = NotificationStatus::SCHEDULED;
            } else {
                $status = NotificationStatus::QUEUED; // Ready to send
            }
        }

        // Display summary
        $io->title('📧 Notification Summary');
        
        $this->displayNotificationSummary($io, [
            'type' => $type,
            'subject' => $subject,
            'content' => $content,
            'channels' => $channels,
            'recipients' => $recipients,
            'importance' => $importance,
            'status' => $status,
            'direction' => NotificationDirection::OUTBOUND,
            'channelSettings' => $channelSettings,
            'metadata' => $metadata,
            'scheduledAt' => $scheduledAt,
            'campaign' => $input->getOption('campaign'),
            'template' => $input->getOption('template'),
        ], $isDraft, $dryRun);
        
        if ($dryRun) {
            $io->success('✅ Dry run completed - notification would be created with the above settings');
            return Command::SUCCESS;
        }

        if (!$io->confirm('Create this notification?', true)) {
            $io->info('Notification creation cancelled.');
            return Command::SUCCESS;
        }

        try {
            // Create the notification entity
            $notification = new Notification();
            $notification->setType($type);
            $notification->setSubject($subject);
            $notification->setContent($content);
            $notification->setChannels($channels);
            $notification->setRecipients($recipients);
            $notification->setImportance($importance); // Use string value, not enum
            $notification->setStatus($status);
            $notification->setDirection(NotificationDirection::OUTBOUND); // Always outbound for sent notifications
            $notification->setChannelSettings($channelSettings);
            
            // Add campaign and template to metadata if provided
            $finalMetadata = $metadata;
            if ($campaign = $input->getOption('campaign')) {
                $finalMetadata['campaign'] = $campaign;
            }
            if ($template = $input->getOption('template')) {
                $finalMetadata['template'] = $template;
            }
            $notification->setMetadata($finalMetadata);
            
            if ($scheduledAt) {
                $notification->setScheduledAt($scheduledAt);
            }

            $this->entityManager->persist($notification);
            $this->entityManager->flush();
            
            if ($isDraft) {
                $io->success("✅ Notification saved as draft!");
                $io->info("Notification ID: {$notification->getId()}");
                $io->note([
                    'To send this draft later, update its status to QUEUED or SCHEDULED',
                    "Notification URL: /api/notification-tracker/notifications/{$notification->getId()}"
                ]);
            } elseif ($scheduledAt) {
                $io->success("✅ Notification scheduled for {$scheduledAt->format('Y-m-d H:i:s T')}!");
                $io->info("Notification ID: {$notification->getId()}");
            } else {
                $io->success("✅ Notification created and queued for sending!");
                $io->info("Notification ID: {$notification->getId()}");
                $io->note([
                    'The notification is now ready to be processed by message handlers',
                    'Track messages at: http://localhost:8001/api/notification-tracker/messages',
                ]);
            }

        } catch (\Exception $e) {
            $io->error('Failed to create notification: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayNotificationSummary(SymfonyStyle $io, array $data, bool $isDraft, bool $isDryRun): void
    {
        $statusLabel = $isDraft ? 'Draft' : ($data['scheduledAt'] ? 'Scheduled' : 'Ready to Send');
        
        if ($isDryRun) {
            $io->note('🔍 DRY RUN - No notification will be created');
        }
        
        $io->section('Basic Information');
        $io->table(['Property', 'Value'], [
            ['Type', $data['type']],
            ['Subject', $data['subject']],
            ['Content', strlen($data['content']) > 100 ? substr($data['content'], 0, 97) . '...' : $data['content']],
            ['Status', $statusLabel],
            ['Direction', ucfirst($data['direction']->value)],
            ['Importance', ucfirst($data['importance'])],
            ['Channels', implode(', ', $data['channels'])],
        ]);

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

        if (!empty($data['channelSettings'])) {
            $io->section('Channel Settings');
            foreach ($data['channelSettings'] as $channel => $settings) {
                $io->text("📧 " . ucfirst($channel) . ":");
                foreach ($settings as $key => $value) {
                    $io->text("  • {$key}: {$value}");
                }
            }
        }

        if (!empty($data['metadata'])) {
            $io->section('Metadata');
            foreach ($data['metadata'] as $key => $value) {
                $io->text("• {$key}: {$value}");
            }
        }

        if ($data['campaign']) {
            $io->section('Campaign');
            $io->text("📊 Campaign: {$data['campaign']}");
        }

        if ($data['template']) {
            $io->section('Template');
            $io->text("📄 Template: {$data['template']}");
        }
    }
}
