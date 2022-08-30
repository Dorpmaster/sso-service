<?php

namespace App\Tests\Unit\Query\ServiceProviderInstance;

use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\ServiceProviderInstance\ServiceProviderInstanceSettings;
use App\Domain\ServiceProviderInstance\ServiceProviderInstanceInterface;
use App\Query\ServiceProviderInstance\GetServiceProviderInstanceCollectionByProfileIdsQuery;
use DateTimeImmutable;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceCollectionEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\EventBusMessage;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\ResourceService;
use Dorpm\EntrypointMessages\Service\UserDataService;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;

class GetServiceProviderInstanceCollectionByProfileIdsQueryTest extends TestCase
{
    protected LoggerInterface $logger;
    protected Generator $faker;
    protected EventBusMessageFactoryInterface $messageFactory;
    protected EventBusMessageDispatcherInterface $dispatcher;
    protected EventBusMessageRegistryClientInterface $client;

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
    }

    public function testQuery()
    {
        $instanceId = Uuid::uuid1();
        $serviceId = Uuid::uuid1();
        $profileId = Uuid::uuid1();
        $status = ServiceProviderInstanceStatus::deployed;
        $type = ServiceProviderType::atlassianSSO;
        $name = $this->faker->word();

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);

        $event = ServiceProviderInstanceCollectionEvent::create([
            'collection' => [
                [
                    'instanceId' => $instanceId->toString(),
                    'serviceId' => $serviceId->toString(),
                    'profileId' => $profileId->toString(),
                    'status' => $status->name,
                    'spType' => $type->name,
                    'name' => $name,
                    'settings' => [],
                ],
            ],
        ]);

        $this->client->method('waitingForMessage')
            ->willReturn(new EventBusMessage(
                UserDataService::ROUTE,
                ResourceService::ROUTE,
                Uuid::uuid4()->getHex(),
                new DateTimeImmutable(),
                $event->toArray(),
            ));

        $query = new GetServiceProviderInstanceCollectionByProfileIdsQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $collection = $query([$profileId]);
        self::assertCount(1, $collection);
        $instance = $collection[0];
        self::assertInstanceOf(ServiceProviderInstanceInterface::class, $instance);
        self::assertEquals($instanceId->toString(), $instance->getInstanceId()->toString());
        self::assertEquals($serviceId->toString(), $instance->getServiceId()->toString());
        self::assertEquals($profileId->toString(), $instance->getProfileId()->toString());
        self::assertEquals(ServiceProviderInstanceStatus::deployed, $instance->getStatus());
        self::assertEquals(ServiceProviderType::atlassianSSO, $instance->getServiceType());
        self::assertSame($name, $instance->getName());
        self::assertInstanceOf(ServiceProviderInstanceSettings::class, $instance->getSettings());
    }

    public function testTimeoutException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willThrowException(new EventBusMessageRegistryTimeoutException());

        $query = new GetServiceProviderInstanceCollectionByProfileIdsQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $this->expectException(EventBusMessageRegistryTimeoutException::class);
        $query([Uuid::uuid1()]);
    }

    public function testIncorrectEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn(new EventBusMessage(
                UserDataService::ROUTE,
                ResourceService::ROUTE,
                Uuid::uuid4()->getHex(),
                new DateTimeImmutable(),
                [],
            ));

        $query = new GetServiceProviderInstanceCollectionByProfileIdsQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $this->expectException(IncorrectEventBusMessageException::class);
        $query([Uuid::uuid1()]);
    }

    public function testUnexpectedEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn(new EventBusMessage(
                UserDataService::ROUTE,
                ResourceService::ROUTE,
                Uuid::uuid4()->getHex(),
                new DateTimeImmutable(),
                [
                    'type' => 'wrong',
                ],
            ));

        $query = new GetServiceProviderInstanceCollectionByProfileIdsQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $this->expectException(UnexpectedEventBusEventException::class);
        $query([Uuid::uuid1()]);
    }

    public function testErrorEventException()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $event = new ErrorEvent('Test');
        $this->client->method('waitingForMessage')
            ->willReturn(new EventBusMessage(
                UserDataService::ROUTE,
                ResourceService::ROUTE,
                Uuid::uuid4()->getHex(),
                new DateTimeImmutable(),
                $event->toArray(),
            ));

        $query = new GetServiceProviderInstanceCollectionByProfileIdsQuery(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $this->expectException(ErrorEventException::class);
        $query([Uuid::uuid1()]);
    }
}
