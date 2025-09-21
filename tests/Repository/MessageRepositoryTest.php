<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nkamuo\NotificationTrackerBundle\Entity\EmailMessage;
use Nkamuo\NotificationTrackerBundle\Entity\Message;
use Nkamuo\NotificationTrackerBundle\Repository\MessageRepository;
use PHPUnit\Framework\TestCase;

class MessageRepositoryTest extends TestCase
{
    private MessageRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new MessageRepository($this->createMock(\Doctrine\Persistence\ManagerRegistry::class));
        
        // Use reflection to inject the mocked entity manager
        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('_em');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->entityManager);
    }

    public function testFindByStampId(): void
    {
        $stampId = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';
        $expectedMessage = new EmailMessage();
        $expectedMessage->setMessengerStampId($stampId);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('m')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(Message::class, 'm')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('m.messengerStampId = :stampId')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('stampId', $stampId)
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($expectedMessage);

        $result = $this->repository->findByStampId($stampId);

        $this->assertSame($expectedMessage, $result);
    }

    public function testFindByStampIdReturnsNullWhenNotFound(): void
    {
        $stampId = 'non-existent-stamp';

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Configure mocks to return null
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $result = $this->repository->findByStampId($stampId);

        $this->assertNull($result);
    }

    public function testFindByFingerprint(): void
    {
        $fingerprint = 'sha256:abcd1234567890efgh';
        $expectedMessages = [
            $this->createMessageWithFingerprint($fingerprint),
            $this->createMessageWithFingerprint($fingerprint),
        ];

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedMessages);

        $result = $this->repository->findByFingerprint($fingerprint);

        $this->assertSame($expectedMessages, $result);
        $this->assertCount(2, $result);
    }

    public function testExistsByStampIdReturnsTrue(): void
    {
        $stampId = '01HKQM7Y8N2XC4T6B9F3E8Z5V1';

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(1); // Count > 0

        $result = $this->repository->existsByStampId($stampId);

        $this->assertTrue($result);
    }

    public function testExistsByStampIdReturnsFalse(): void
    {
        $stampId = 'non-existent-stamp';

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(0); // Count = 0

        $result = $this->repository->existsByStampId($stampId);

        $this->assertFalse($result);
    }

    private function createMessageWithFingerprint(string $fingerprint): EmailMessage
    {
        $message = new EmailMessage();
        $message->setContentFingerprint($fingerprint);
        return $message;
    }
}
