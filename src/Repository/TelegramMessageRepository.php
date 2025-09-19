<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nkamuo\NotificationTrackerBundle\Entity\TelegramMessage;

/**
 * @extends ServiceEntityRepository<TelegramMessage>
 * @method TelegramMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method TelegramMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method TelegramMessage[]    findAll()
 * @method TelegramMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TelegramMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramMessage::class);
    }

    /**
     * @return TelegramMessage[]
     */
    public function findByChatId(string $chatId): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.chatId = :chatId')
            ->setParameter('chatId', $chatId)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findByMessageThreadId(int $messageThreadId): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.messageThreadId = :messageThreadId')
            ->setParameter('messageThreadId', $messageThreadId)
            ->orderBy('tm.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findByParseMode(string $parseMode): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.parseMode = :parseMode')
            ->setParameter('parseMode', $parseMode)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findWithDisabledNotification(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.disableNotification = :disabled')
            ->setParameter('disabled', true)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findWithNotificationEnabled(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.disableNotification = :disabled')
            ->setParameter('disabled', false)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findWithReplyMarkup(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.replyMarkup IS NOT NULL')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findInThread(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.messageThreadId IS NOT NULL')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TelegramMessage[]
     */
    public function findDirectMessages(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.messageThreadId IS NULL')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in message content
     * @return TelegramMessage[]
     */
    public function searchInContent(string $searchTerm): array
    {
        return $this->createQueryBuilder('tm')
            ->leftJoin('tm.content', 'mc')
            ->andWhere('mc.bodyText LIKE :search OR mc.bodyHtml LIKE :search OR JSON_EXTRACT(tm.replyMarkup, \'$\') LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with specific reply markup type
     * @return TelegramMessage[]
     */
    public function findByReplyMarkupType(string $markupType): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('JSON_EXTRACT(tm.replyMarkup, \'$.type\') = :markupType')
            ->setParameter('markupType', $markupType)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics by chat ID
     * @return array<string, int>
     */
    public function getStatsByChatId(): array
    {
        $result = $this->createQueryBuilder('tm')
            ->select('tm.chatId, COUNT(tm.id) as count')
            ->groupBy('tm.chatId')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['chatId']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get statistics by parse mode
     * @return array<string, int>
     */
    public function getStatsByParseMode(): array
    {
        $result = $this->createQueryBuilder('tm')
            ->select('tm.parseMode, COUNT(tm.id) as count')
            ->andWhere('tm.parseMode IS NOT NULL')
            ->groupBy('tm.parseMode')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['parseMode']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get notification settings statistics
     * @return array{enabled: int, disabled: int}
     */
    public function getNotificationStats(): array
    {
        $enabled = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.disableNotification = :disabled')
            ->setParameter('disabled', false)
            ->getQuery()
            ->getSingleScalarResult();

        $disabled = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.disableNotification = :disabled')
            ->setParameter('disabled', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'enabled' => (int) $enabled,
            'disabled' => (int) $disabled
        ];
    }

    /**
     * Get thread statistics
     * @return array{threaded: int, direct: int}
     */
    public function getThreadStats(): array
    {
        $threaded = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.messageThreadId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $direct = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.messageThreadId IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'threaded' => (int) $threaded,
            'direct' => (int) $direct
        ];
    }

    /**
     * Get reply markup statistics
     * @return array{withMarkup: int, withoutMarkup: int}
     */
    public function getReplyMarkupStats(): array
    {
        $withMarkup = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.replyMarkup IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $withoutMarkup = $this->createQueryBuilder('tm')
            ->select('COUNT(tm.id)')
            ->andWhere('tm.replyMarkup IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'withMarkup' => (int) $withMarkup,
            'withoutMarkup' => (int) $withoutMarkup
        ];
    }

    /**
     * Find most active chats
     * @return array<string, int>
     */
    public function getMostActiveChats(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('tm')
            ->select('tm.chatId, COUNT(tm.id) as count')
            ->groupBy('tm.chatId')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['chatId']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find messages in chat by date range
     * @return TelegramMessage[]
     */
    public function findByChatIdAndDateRange(string $chatId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.chatId = :chatId')
            ->andWhere('tm.sentAt >= :from')
            ->andWhere('tm.sentAt <= :to')
            ->setParameter('chatId', $chatId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find conversation thread by thread ID
     * @return TelegramMessage[]
     */
    public function findConversationThread(int $messageThreadId): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('tm.messageThreadId = :messageThreadId')
            ->setParameter('messageThreadId', $messageThreadId)
            ->orderBy('tm.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count messages per chat in date range
     * @return array<string, int>
     */
    public function getChatActivityInDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $result = $this->createQueryBuilder('tm')
            ->select('tm.chatId, COUNT(tm.id) as count')
            ->andWhere('tm.sentAt >= :from')
            ->andWhere('tm.sentAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('tm.chatId')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['chatId']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find messages with inline keyboards
     * @return TelegramMessage[]
     */
    public function findWithInlineKeyboards(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('JSON_EXTRACT(tm.replyMarkup, \'$.inline_keyboard\') IS NOT NULL')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find messages with reply keyboards
     * @return TelegramMessage[]
     */
    public function findWithReplyKeyboards(): array
    {
        return $this->createQueryBuilder('tm')
            ->andWhere('JSON_EXTRACT(tm.replyMarkup, \'$.keyboard\') IS NOT NULL')
            ->orderBy('tm.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
