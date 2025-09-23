<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Nkamuo\NotificationTrackerBundle\Entity\Notification;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Service for extracting and managing references from email headers and notifications
 */
class ReferenceExtractor
{
    private const HEADER_PREFIX = 'X-Notification-Ref-';
    
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Extract references from email headers
     * 
     * @param Email $email
     * @return array Array of key => value references
     */
    public function extractFromEmail(Email $email): array
    {
        $refs = [];
        $headers = $email->getHeaders();
        
        foreach ($headers->all() as $header) {
            $name = $header->getName();
            
            if (str_starts_with($name, self::HEADER_PREFIX)) {
                $refKey = substr($name, strlen(self::HEADER_PREFIX));
                $refValue = $header->getBodyAsString();
                
                if (!empty($refKey) && !empty($refValue)) {
                    $refs[$refKey] = $refValue;
                    
                    $this->logger->debug('Extracted reference from email header', [
                        'header' => $name,
                        'key' => $refKey,
                        'value' => $refValue
                    ]);
                }
            }
        }
        
        return $refs;
    }

    /**
     * Extract references from notification context
     * 
     * @param array $context
     * @return array Array of key => value references
     */
    public function extractFromContext(array $context): array
    {
        $refs = [];
        
        // Look for direct refs in context
        if (isset($context['refs']) && is_array($context['refs'])) {
            $refs = array_merge($refs, $context['refs']);
        }
        
        // Look for email in context and extract from headers
        if (isset($context['email']) && $context['email'] instanceof Email) {
            $emailRefs = $this->extractFromEmail($context['email']);
            $refs = array_merge($refs, $emailRefs);
        }
        
        // Look for headers in context
        if (isset($context['headers']) && is_array($context['headers'])) {
            foreach ($context['headers'] as $name => $value) {
                if (str_starts_with($name, self::HEADER_PREFIX)) {
                    $refKey = substr($name, strlen(self::HEADER_PREFIX));
                    if (!empty($refKey) && !empty($value)) {
                        $refs[$refKey] = (string) $value;
                    }
                }
            }
        }
        
        return $refs;
    }

    /**
     * Apply references to a notification
     * 
     * @param Notification $notification
     * @param array $refs
     * @return int Number of references applied
     */
    public function applyToNotification(Notification $notification, array $refs): int
    {
        $count = 0;
        
        foreach ($refs as $key => $value) {
            $notification->setRef($key, (string) $value);
            $count++;
            
            try {
                $notificationId = (string) $notification->getId();
            } catch (\Exception $e) {
                $notificationId = 'uninitialized';
            }
            
            $this->logger->debug('Applied reference to notification', [
                'notification_id' => $notificationId,
                'ref_key' => $key,
                'ref_value' => $value
            ]);
        }
        
        return $count;
    }

    /**
     * Apply references to a message
     * 
     * @param Message $message
     * @param array $refs
     * @return int Number of references applied
     */
    public function applyToMessage(Message $message, array $refs): int
    {
        $count = 0;
        
        foreach ($refs as $key => $value) {
            $message->setRef($key, (string) $value);
            $count++;
            
            try {
                $messageId = (string) $message->getId();
            } catch (\Exception $e) {
                $messageId = 'uninitialized';
            }
            
            $this->logger->debug('Applied reference to message', [
                'message_id' => $messageId,
                'ref_key' => $key,
                'ref_value' => $value
            ]);
        }
        
        return $count;
    }

    /**
     * Add references to email headers
     * 
     * @param Email $email
     * @param array $refs
     * @return Email Modified email with reference headers
     */
    public function addToEmail(Email $email, array $refs): Email
    {
        foreach ($refs as $key => $value) {
            $headerName = self::HEADER_PREFIX . $key;
            $email->getHeaders()->addTextHeader($headerName, (string) $value);
            
            $this->logger->debug('Added reference header to email', [
                'header' => $headerName,
                'value' => $value
            ]);
        }
        
        return $email;
    }

    /**
     * Create headers array for manual addition
     * 
     * @param array $refs
     * @return array Array of header name => value
     */
    public function createHeaders(array $refs): array
    {
        $headers = [];
        
        foreach ($refs as $key => $value) {
            $headers[self::HEADER_PREFIX . $key] = (string) $value;
        }
        
        return $headers;
    }

    /**
     * Get the header prefix used for references
     * 
     * @return string
     */
    public function getHeaderPrefix(): string
    {
        return self::HEADER_PREFIX;
    }

    /**
     * Validate reference key format
     * 
     * @param string $key
     * @return bool
     */
    public function isValidRefKey(string $key): bool
    {
        // Allow alphanumeric, underscore, dash, and dot
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $key) === 1;
    }

    /**
     * Sanitize reference key
     * 
     * @param string $key
     * @return string
     */
    public function sanitizeRefKey(string $key): string
    {
        // Replace invalid characters with underscores
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
    }
}
