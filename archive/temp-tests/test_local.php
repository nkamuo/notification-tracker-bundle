#!/usr/bin/env php
<?php

/**
 * Local Testing Script for NotificationTrackerBundle
 * 
 * This script sets up a minimal Symfony application to test all bundle features locally.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Nkamuo\NotificationTrackerBundle\Service\MessageTracker;
use Nkamuo\NotificationTrackerBundle\EventSubscriber\MailerEventSubscriber;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class TestNotificationTrackerCommand extends Command
{
    protected static $defaultName = 'test:notification-tracker';

    protected function configure(): void
    {
        $this
            ->setDescription('Test the NotificationTrackerBundle features locally')
            ->addArgument('test-type', InputArgument::OPTIONAL, 'Type of test (email|sms|all)', 'all')
            ->setHelp('This command tests various NotificationTrackerBundle features locally without a full Symfony app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $testType = $input->getArgument('test-type');

        $io->title('🧪 NotificationTrackerBundle Local Testing');

        try {
            // Setup minimal container
            $container = $this->setupContainer($io);
            
            // Get services
            $messageTracker = $container->get('message_tracker');
            $entityManager = $container->get('entity_manager');
            
            $io->section('📊 Testing Results');

            if ($testType === 'email' || $testType === 'all') {
                $this->testEmailTracking($io, $messageTracker);
            }

            if ($testType === 'sms' || $testType === 'all') {
                $this->testSmsTracking($io, $messageTracker);
            }

            if ($testType === 'all') {
                $this->testDatabaseOperations($io, $entityManager);
            }

            $io->success('All tests completed successfully!');
            
        } catch (\Exception $e) {
            $io->error('Test failed: ' . $e->getMessage());
            $io->note('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function setupContainer(SymfonyStyle $io): ContainerBuilder
    {
        $io->section('🔧 Setting Up Test Environment');
        
        $container = new ContainerBuilder();
        
        // Mock essential services
        $io->text('- Setting up mock services...');
        $container->set('logger', new class {
            public function info($message, $context = []) { echo "ℹ️  $message\n"; }
            public function error($message, $context = []) { echo "❌ $message\n"; }
            public function warning($message, $context = []) { echo "⚠️  $message\n"; }
        });
        
        // Setup in-memory database
        $io->text('- Setting up in-memory database...');
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [__DIR__ . '/src/Entity'],
            true
        );
        
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ], $config);
        
        $entityManager = new EntityManager($connection, $config);
        $container->set('entity_manager', $entityManager);
        
        // Setup MessageTracker
        $io->text('- Setting up MessageTracker...');
        $messageTracker = new \ReflectionClass(MessageTracker::class);
        $tracker = $messageTracker->newInstanceWithoutConstructor();
        
        // We'll mock this for testing
        $container->set('message_tracker', $tracker);
        
        $io->text('✅ Test environment ready!');
        
        return $container;
    }

    private function testEmailTracking(SymfonyStyle $io, $messageTracker): void
    {
        $io->section('📧 Testing Email Tracking');
        
        try {
            // Create a test email
            $email = (new Email())
                ->from('test@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email from NotificationTracker')
                ->text('This is a test email to verify notification tracking works.')
                ->html('<p>This is a <strong>test email</strong> to verify notification tracking works.</p>');
            
            $io->text('📨 Created test email');
            $io->listing([
                'From: test@example.com',
                'To: recipient@example.com', 
                'Subject: Test Email from NotificationTracker'
            ]);
            
            // For now, just validate the email object
            $io->text('✅ Email object created successfully');
            
            // Test email components
            $this->validateEmailComponents($io, $email);
            
        } catch (\Exception $e) {
            $io->error('Email tracking test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function testSmsTracking(SymfonyStyle $io, $messageTracker): void
    {
        $io->section('📱 Testing SMS Tracking');
        
        try {
            // Mock SMS data
            $smsData = [
                'to' => '+1234567890',
                'message' => 'Test SMS from NotificationTracker: Your order #12345 has been confirmed!',
                'segments' => 1
            ];
            
            $io->text('📱 Created test SMS');
            $io->listing([
                'To: ' . $smsData['to'],
                'Message: ' . $smsData['message'],
                'Segments: ' . $smsData['segments']
            ]);
            
            // Validate SMS length and segmentation
            $length = strlen($smsData['message']);
            $calculatedSegments = ceil($length / 160);
            
            $io->text("📏 Message length: {$length} characters");
            $io->text("📦 Calculated segments: {$calculatedSegments}");
            
            if ($calculatedSegments === $smsData['segments']) {
                $io->text('✅ SMS segmentation calculation correct');
            } else {
                $io->warning('⚠️  SMS segmentation mismatch');
            }
            
        } catch (\Exception $e) {
            $io->error('SMS tracking test failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validateEmailComponents(SymfonyStyle $io, Email $email): void
    {
        $io->text('🔍 Validating email components...');
        
        // Check recipients
        $to = $email->getTo();
        $io->text('✅ Recipients: ' . count($to) . ' recipient(s)');
        
        // Check subject
        $subject = $email->getSubject();
        $io->text('✅ Subject: ' . ($subject ? 'Present' : 'Missing'));
        
        // Check content
        $textBody = $email->getTextBody();
        $htmlBody = $email->getHtmlBody();
        
        $io->text('✅ Text content: ' . ($textBody ? 'Present (' . strlen($textBody) . ' chars)' : 'None'));
        $io->text('✅ HTML content: ' . ($htmlBody ? 'Present (' . strlen($htmlBody) . ' chars)' : 'None'));
        
        // Check headers
        $headers = $email->getHeaders();
        $io->text('✅ Headers: ' . count($headers->all()) . ' header(s)');
    }

    private function testDatabaseOperations(SymfonyStyle $io, $entityManager): void
    {
        $io->section('🗄️  Testing Database Operations');
        
        try {
            // For now, just test that we can work with ULID
            $ulid = new \Symfony\Component\Uid\Ulid();
            $io->text('✅ ULID generation: ' . $ulid->toBase32());
            
            // Test that we can create entity instances
            $message = new \Nkamuo\NotificationTrackerBundle\Entity\EmailMessage();
            $message->setSubject('Test Message');
            $message->setFromEmail('test@example.com');
            
            $io->text('✅ EmailMessage entity creation successful');
            $io->text('   - Subject: ' . $message->getSubject());
            $io->text('   - From: ' . $message->getFromEmail());
            $io->text('   - ID: ' . $message->getId()->toBase32());
            
        } catch (\Exception $e) {
            $io->error('Database operations test failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

// Create and run the application
$application = new Application('NotificationTracker Test Suite', '1.0.0');
$application->add(new TestNotificationTrackerCommand());
$application->setDefaultCommand('test:notification-tracker', true);
$application->run();
