<?php

namespace App\Tests\Unit\Security;

use App\DependencyInjection\Exception\ApiException;
use App\Domain\Exception\ApiExceptionInterface;
use App\Security\JwtUserProvider;
use App\Security\SsoUser;
use App\Security\SsoUserInterface;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\UserAuthenticationDataEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\UserAuthenticationDataEventInterface;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\EventBusMessage;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactory;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\SsoService;
use Dorpm\EntrypointMessages\Service\UserDataService;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class JwtUserProviderTest extends TestCase
{
    protected LoggerInterface $logger;
    protected EventBusMessageFactoryInterface $messageFactory;
    protected EventBusMessageDispatcherInterface $dispatcher;
    protected EventBusMessageRegistryClientInterface $client;
    protected Generator $faker;
    protected UserAuthenticationDataEventInterface $authenticationDataEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageFactory = new EventBusMessageFactory();

        $this->authenticationDataEvent = UserAuthenticationDataEvent::create([
            'userId' => Uuid::uuid4()->toString(),
            'email' => $this->faker->email(),
            'passwordHash' => $this->faker->password(),
            'displayName' => $this->faker->name(),
            'permissions' => [],
        ]);

        $message = $this->messageFactory->create(
            UserDataService::ROUTE,
            SsoService::ROUTE,
            Uuid::uuid4()->getHex(),
            $this->authenticationDataEvent
        );

        $this->dispatcher = $this->createMock(EventBusMessageDispatcherInterface::class);
        $this->dispatcher->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn($message);
    }

    public function testSsoUser()
    {
        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        $user = $provider->loadUserByIdentifier($this->faker->email());
        self::assertInstanceOf(SsoUserInterface::class, $user);
        self::assertSame($this->authenticationDataEvent->getUserId(), $user->getUserIdentifier());
        self::assertSame($this->authenticationDataEvent->getEmail(), $user->getEmail());
        self::assertSame($this->authenticationDataEvent->getPasswordHash(), $user->getPassword());
        self::assertSame($this->authenticationDataEvent->getDisplayName(), $user->getDisplayName());
        self::assertSame($this->authenticationDataEvent->getPermissions(), $user->getRoles());
    }

    public function testTimeout()
    {
        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willThrowException(new EventBusMessageRegistryTimeoutException());

        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::expectException(ApiException::class);
        $provider->loadUserByIdentifier($this->faker->email());
        /**
         * @var ApiExceptionInterface $exception
         */
        $exception = self::getExpectedException();
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
    }

    public function testBrokenMessage()
    {
        $message = new EventBusMessage(
            UserDataService::ROUTE,
            SsoService::ROUTE,
            Uuid::uuid4()->getHex(),
            new \DateTimeImmutable(),
            []
        );

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn($message);

        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::expectException(ApiException::class);
        $provider->loadUserByIdentifier($this->faker->email());
        /**
         * @var ApiExceptionInterface $exception
         */
        $exception = self::getExpectedException();
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
    }

    public function testUnexpectedMessageType()
    {
        $message = new EventBusMessage(
            UserDataService::ROUTE,
            SsoService::ROUTE,
            Uuid::uuid4()->getHex(),
            new \DateTimeImmutable(),
            [
                'type' => 'wrong',
            ]
        );

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn($message);

        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::expectException(ApiException::class);
        $provider->loadUserByIdentifier($this->faker->email());
        /**
         * @var ApiExceptionInterface $exception
         */
        $exception = self::getExpectedException();
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
    }

    public function testErrorMessage()
    {
        $event = new ErrorEvent('Test');
        $message = $this->messageFactory->create(
            UserDataService::ROUTE,
            SsoService::ROUTE,
            Uuid::uuid4()->getHex(),
            $event
        );

        $this->client = $this->createMock(EventBusMessageRegistryClientInterface::class);
        $this->client->method('waitingForMessage')
            ->willReturn($message);

        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier($this->faker->email());
    }

    public function testRefreshUser()
    {
        $user = new SsoUser('Test', 'Test', 'Test');

        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::assertSame($user, $provider->refreshUser($user));
    }

    public function testSupportsClass()
    {
        $provider = new JwtUserProvider(
            $this->logger,
            $this->messageFactory,
            $this->dispatcher,
            $this->client
        );

        self::assertTrue($provider->supportsClass(SsoUser::class));
        self::assertFalse($provider->supportsClass('AnotherClass'));
    }
}
