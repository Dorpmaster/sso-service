<?php

namespace App\Tests\Unit\Query\Sso;

use App\DependencyInjection\Exception\EntityNotFoundException;
use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\Sso\SsoUserDataDto;
use App\Domain\Sso\SsoUserDataInterface;
use App\Query\Sso\GetSsoUserDataQuery;
use DateTimeImmutable;
use Dorpm\EntrypointMessages\Event\Error\ErrorCode;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\SsoUserDataEvent;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\EventBusMessage;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\ResourceService;
use Dorpm\EntrypointMessages\Service\SsoService;
use Dorpm\EntrypointMessages\Service\UserDataService;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Messenger\Envelope;

class GetSsoUserDataQueryTest extends TestCase
{
    protected LoggerInterface $logger;
    protected Generator $faker;
    protected EventBusMessageFactoryInterface $messageFactory;
    protected EventBusMessageDispatcherInterface $dispatcher;
    protected EventBusMessageRegistryClientInterface $client;
    protected CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->faker = Factory::create();
        $this->messageFactory = $this->createMock(EventBusMessageFactoryInterface::class);
        $this->dispatcher = $this->createMock(EventBusMessageDispatcherInterface::class);
        $this->dispatcher->method('dispatch')
            ->willReturn(new Envelope(new EventBusMessage('test', 'test', 'test', new DateTimeImmutable(), [])));
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );

        $item = $createCacheItem('test', null, false);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn($item);
    }

    public function testQuery()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $event = SsoUserDataEvent::create([
            'profileId' => Uuid::uuid1()->toString(),
            'userId' => Uuid::uuid1()->toString(),
            'email' => $this->faker->email(),
            'passwordHash' => $this->faker->sha256(),
        ]);

        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $dto = $query(Uuid::uuid1(), $event->getEmail());
        self::assertInstanceOf(SsoUserDataInterface::class, $dto);
        self::assertEquals($event->getProfileId(), $dto->getProfileId()->toString());
        self::assertEquals($event->getUserId(), $dto->getUserId()->toString());
        self::assertEquals($event->getEmail(), $dto->getEmail());
        self::assertEquals($event->getPasswordHash(), $dto->getPasswordHash());
    }

    public function testCachedQuery()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $event = SsoUserDataEvent::create([
            'profileId' => Uuid::uuid1()->toString(),
            'userId' => Uuid::uuid1()->toString(),
            'email' => $this->faker->email(),
            'passwordHash' => $this->faker->sha256(),
        ]);

        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );

        $userData = new SsoUserDataDto(
            profileId: Uuid::fromString($event->getProfileId()),
            userId: Uuid::fromString($event->getUserId()),
            email: $event->getEmail(),
            passwordHash: $event->getPasswordHash(),
        );

        $item = $createCacheItem('test', $userData, false);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn($item);

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $dto = $query(Uuid::uuid1(), $event->getEmail());
        self::assertInstanceOf(SsoUserDataInterface::class, $dto);
        self::assertEquals($event->getProfileId(), $dto->getProfileId()->toString());
        self::assertEquals($event->getUserId(), $dto->getUserId()->toString());
        self::assertEquals($event->getEmail(), $dto->getEmail());
        self::assertEquals($event->getPasswordHash(), $dto->getPasswordHash());
    }

    public function testTimeoutException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willThrowException(new EventBusMessageRegistryTimeoutException());

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(EventBusMessageRegistryTimeoutException::class);
        $query(Uuid::uuid1(), $this->faker->email());
    }

    public function testIncorrectEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    ResourceService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    [],
                )
            );

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(IncorrectEventBusMessageException::class);
        $query(Uuid::uuid1(), $this->faker->email());
    }

    public function testUnexpectedEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    [
                        'type' => 'wrong',
                    ],
                )
            );

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(UnexpectedEventBusEventException::class);
        $query(Uuid::uuid1(), $this->faker->email());
    }

    public function testErrorEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $event = new ErrorEvent('Test');
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(ErrorEventException::class);
        $query(Uuid::uuid1(), $this->faker->email());
    }

    public function testEntityNotFoundException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $event = new ErrorEvent('Test', ErrorCode::ENTITY_NOT_FOUND->value);
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetSsoUserDataQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(EntityNotFoundException::class);
        $query(Uuid::uuid1(), $this->faker->email());
    }
}
