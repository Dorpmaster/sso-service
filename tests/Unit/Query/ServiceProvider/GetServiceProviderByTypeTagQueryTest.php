<?php

namespace App\Tests\Unit\Query\ServiceProvider;

use App\DependencyInjection\Exception\EntityNotFoundException;
use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\ServiceProvider\AtlassianSsoServiceProvider;
use App\DependencyInjection\ServiceProvider\AtlassianSsoSettings;
use App\Domain\ServiceProvider\AtlassianSsoServiceProviderInterface;
use App\Domain\ServiceProvider\AtlassianSsoSettingsInterface;
use App\Query\ServiceProvider\GetServiceProviderByTypeTagQuery;
use DateTimeImmutable;
use Dorpm\EntrypointMessages\Event\Error\ErrorCode;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\EventBusMessage;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\BomApiService;
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

class GetServiceProviderByTypeTagQueryTest extends TestCase
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
        $name = $this->faker->word();
        $settings = [
            'assertionConsumerServiceUrl' => $this->faker->url(),
            'audienceUrl' => $this->faker->url(),
        ];

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $event = ServiceProviderEvent::create([
            'serviceId' => Uuid::uuid1()->toString(),
            'teamId' => Uuid::uuid1()->toString(),
            'spType' => ServiceProviderType::atlassianSSO->name,
            'name' => $name,
            'settings' => [
                'assertionConsumerServiceUrl' => $settings['assertionConsumerServiceUrl'],
                'audienceUrl' => $settings['audienceUrl'],
                'x509Certificate' => $this->faker->sha256(),
                'privateKey' => $this->faker->sha256(),
            ],
        ]);

        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    UserDataService::ROUTE,
                    BomApiService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $serviceProvider = $query(ServiceProviderType::atlassianSSO, 'Test');
        self::assertInstanceOf(AtlassianSsoServiceProviderInterface::class, $serviceProvider);
        self::assertEquals($event->getTeamId(), $serviceProvider->getTeamId()->toString());
        self::assertEquals($event->getServiceId(), $serviceProvider->getServiceId()->toString());
        self::assertEquals(ServiceProviderType::atlassianSSO, $serviceProvider->getServiceType());
        self::assertSame($name, $serviceProvider->getName());
        self::assertInstanceOf(AtlassianSsoSettingsInterface::class, $serviceProvider->getSettings());
        self::assertSame($settings['assertionConsumerServiceUrl'], $serviceProvider->getSettings()->getAssertionConsumerServiceUrl());
        self::assertSame($settings['audienceUrl'], $serviceProvider->getSettings()->getAudienceUrl());
        self::assertNotEmpty($serviceProvider->getSettings()->getX509Certificate());
    }

    public function testCache()
    {
        $cachedSP = new AtlassianSsoServiceProvider(
            Uuid::uuid1(),
            Uuid::uuid1(),
            ServiceProviderType::atlassianSSO,
            $this->faker->word(),
            new AtlassianSsoSettings(
                $this->faker->url(),
                $this->faker->url(),
                $this->faker->sha256(),
                $this->faker->sha256(),
            ),
        );

        $this->dispatcher = $this->createMock(EventBusMessageDispatcherInterface::class);
        $this->dispatcher->expects(self::never())->method('dispatch');

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

        $item = $createCacheItem('test', $cachedSP, true);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn($item);

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $serviceProvider = $query(ServiceProviderType::atlassianSSO, 'Test');
        self::assertInstanceOf(AtlassianSsoServiceProviderInterface::class, $serviceProvider);
        self::assertEquals($cachedSP->getTeamId()->toString(), $serviceProvider->getTeamId()->toString());
        self::assertEquals($cachedSP->getServiceId()->toString(), $serviceProvider->getServiceId()->toString());
        self::assertEquals(ServiceProviderType::atlassianSSO, $serviceProvider->getServiceType());
        self::assertEquals($cachedSP->getName(), $serviceProvider->getName());
        self::assertInstanceOf(AtlassianSsoSettingsInterface::class, $serviceProvider->getSettings());
        self::assertEquals($cachedSP->getSettings()->getAssertionConsumerServiceUrl(), $serviceProvider->getSettings()->getAssertionConsumerServiceUrl());
        self::assertEquals($cachedSP->getSettings()->getAudienceUrl(), $serviceProvider->getSettings()->getAudienceUrl());
        self::assertEquals($cachedSP->getSettings()->getX509Certificate(), $serviceProvider->getSettings()->getX509Certificate());
    }

    public function testTimeoutException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willThrowException(new EventBusMessageRegistryTimeoutException());

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(EventBusMessageRegistryTimeoutException::class);
        $query(ServiceProviderType::atlassianSSO, 'Test');
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

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(IncorrectEventBusMessageException::class);
        $query(ServiceProviderType::atlassianSSO, 'Test');
    }

    public function testUnexpectedEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    ResourceService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    [
                        'type' => 'wrong',
                    ],
                )
            );

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(UnexpectedEventBusEventException::class);
        $query(ServiceProviderType::atlassianSSO, 'Test');
    }

    public function testErrorEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $event = new ErrorEvent('Test');
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    ResourceService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(ErrorEventException::class);
        $query(ServiceProviderType::atlassianSSO, 'Test');
    }

    public function testEntityNotFoundException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $event = new ErrorEvent('Test', ErrorCode::ENTITY_NOT_FOUND->value);
        $this->client->method('waitingForMessage')
            ->willReturn(
                new EventBusMessage(
                    ResourceService::ROUTE,
                    SsoService::ROUTE,
                    Uuid::uuid4()->getHex(),
                    new DateTimeImmutable(),
                    $event->toArray(),
                )
            );

        $query = new GetServiceProviderByTypeTagQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client,
            $this->cache,
        );

        $this->expectException(EntityNotFoundException::class);
        $query(ServiceProviderType::atlassianSSO, 'Test');
    }
}
