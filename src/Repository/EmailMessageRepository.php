<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;

/**
 * @extends ServiceEntityRepository<EmailMessage>
 * @method EmailMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailMessage[]    findAll()
 * @method EmailMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailMessage::class);
    }

    /**
     * @return EmailMessage[]
     */
    public function findByFromEmail(string $fromEmail): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.fromEmail = :fromEmail')
            ->setParameter('fromEmail', $fromEmail)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findBySubject(string $subject): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findBySubjectPattern(string $pattern): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.subject LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findByMessageId(string $messageId): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.messageId = :messageId')
            ->setParameter('messageId', $messageId)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findByFromEmailAndSubject(string $fromEmail, string $subject): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.fromEmail = :fromEmail')
            ->andWhere('em.subject = :subject')
            ->setParameter('fromEmail', $fromEmail)
            ->setParameter('subject', $subject)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findWithReplyTo(): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.replyTo IS NOT NULL')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findByFromName(string $fromName): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.fromName = :fromName')
            ->setParameter('fromName', $fromName)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailMessage[]
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('em.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in email subject and content
     * @return EmailMessage[]
     */
    public function searchInSubjectAndContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('em')
            ->leftJoin('em.content', 'mc')
            ->andWhere('em.subject LIKE :search OR mc.bodyText LIKE :search OR mc.bodyHtml LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emails with specific headers
     * @return EmailMessage[]
     */
    public function findWithHeader(string $headerName): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('JSON_EXTRACT(em.headers, :headerPath) IS NOT NULL')
            ->setParameter('headerPath', '$."' . $headerName . '"')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emails with specific header value
     * @return EmailMessage[]
     */
    public function findByHeaderValue(string $headerName, string $headerValue): array
    {
        return $this->createQueryBuilder('em')
            ->andWhere('JSON_EXTRACT(em.headers, :headerPath) = :headerValue')
            ->setParameter('headerPath', '$."' . $headerName . '"')
            ->setParameter('headerValue', $headerValue)
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bounced emails
     * @return EmailMessage[]
     */
    public function findBounced(): array
    {
        return $this->createQueryBuilder('em')
            ->innerJoin('em.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'bounced')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emails that were opened
     * @return EmailMessage[]
     */
    public function findOpened(): array
    {
        return $this->createQueryBuilder('em')
            ->innerJoin('em.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'opened')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics by from email domain
     * @return array<string, int>
     */
    public function getStatsByFromEmailDomain(): array
    {
        $result = $this->createQueryBuilder('em')
            ->select('SUBSTRING(em.fromEmail, LOCATE(\'@\', em.fromEmail) + 1) as domain, COUNT(em.id) as count')
            ->groupBy('domain')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['domain']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by priority
     * @return array<string, int>
     */
    public function getStatsByPriority(): array
    {
        $result = $this->createQueryBuilder('em')
            ->select('em.priority, COUNT(em.id) as count')
            ->andWhere('em.priority IS NOT NULL')
            ->groupBy('em.priority')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['priority']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find emails with attachments
     * @return EmailMessage[]
     */
    public function findWithAttachments(): array
    {
        return $this->createQueryBuilder('em')
            ->innerJoin('em.attachments', 'ma')
            ->orderBy('em.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count emails by delivery status
     * @return array{delivered: int, bounced: int, pending: int}
     */
    public function getDeliveryStats(): array
    {
        $delivered = $this->createQueryBuilder('em')
            ->select('COUNT(DISTINCT em.id)')
            ->innerJoin('em.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'delivered')
            ->getQuery()
            ->getSingleScalarResult();

        $bounced = $this->createQueryBuilder('em')
            ->select('COUNT(DISTINCT em.id)')
            ->innerJoin('em.events', 'me')
            ->andWhere('me.eventType = :eventType')
            ->setParameter('eventType', 'bounced')
            ->getQuery()
            ->getSingleScalarResult();

        $total = $this->createQueryBuilder('em')
            ->select('COUNT(em.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'delivered' => (int) $delivered,
            'bounced' => (int) $bounced,
            'pending' => (int) $total - (int) $delivered - (int) $bounced
        ];
    }
}
