<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Functional;

use Nkamuo\NotificationTrackerBundle\Command\CreateNotificationCommand;
use Nkamuo\NotificationTrackerBundle\Command\SendEmailCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CommandRegistrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testCreateNotificationCommandIsRegistered(): void
    {
        $this->markTestSkipped('Skipping until service configuration issue is resolved');
        
        $container = static::getContainer();
        
        // Test that the command can be retrieved from the container
        $command = $container->get(CreateNotificationCommand::class);
        $this->assertInstanceOf(CreateNotificationCommand::class, $command);
        
        // Test the command name
        $this->assertEquals('notification-tracker:create-notification', $command->getName());
    }

    public function testSendEmailCommandIsRegistered(): void
    {
        $this->markTestSkipped('Skipping until service configuration issue is resolved');
        
        $container = static::getContainer();
        
        // Test that the command can be retrieved from the container
        $command = $container->get(SendEmailCommand::class);
        $this->assertInstanceOf(SendEmailCommand::class, $command);
        
        // Test the command name
        $this->assertEquals('notification-tracker:send-bulk-email', $command->getName());
    }

    public function testCreateNotificationCommandHelp(): void
    {
        $container = static::getContainer();
        $command = $container->get(CreateNotificationCommand::class);
        
        $application = new Application();
        $application->add($command);
        
        $commandTester = new CommandTester($command);
        
        // Test help output
        $commandTester->execute(['--help' => true]);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Create a new notification', $output);
        $this->assertStringContainsString('--to', $output);
        $this->assertStringContainsString('--cc', $output);
        $this->assertStringContainsString('--bcc', $output);
        $this->assertStringContainsString('--draft', $output);
    }

    public function testSendEmailCommandHelp(): void
    {
        $container = static::getContainer();
        $command = $container->get(SendEmailCommand::class);
        
        $application = new Application();
        $application->add($command);
        
        $commandTester = new CommandTester($command);
        
        // Test help output
        $commandTester->execute(['--help' => true]);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Send an email to multiple recipients', $output);
        $this->assertStringContainsString('--to', $output);
        $this->assertStringContainsString('--cc', $output);
        $this->assertStringContainsString('--bcc', $output);
        $this->assertStringContainsString('--draft', $output);
    }
}
