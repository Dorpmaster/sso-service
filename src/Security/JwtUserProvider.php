<?php

namespace App\Security;

use App\DependencyInjection\BaseLoggableService;
use App\DependencyInjection\Exception\ApiException;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\Error\ErrorEventInterface;
use Dorpm\EntrypointMessages\Event\UserDataService\GetUserAuthenticationDataEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\UserAuthenticationDataEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\UserAuthenticationDataEventInterface;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistry;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\SsoService;
use Dorpm\EntrypointMessages\Service\UserDataService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtUserProvider extends BaseLoggableService implements UserProviderInterface
{
    public function __construct(
        LoggerInterface $logger,
        protected EventBusMessageFactoryInterface $messageFactory,
        protected EventBusMessageDispatcherInterface $dispatcher,
        protected EventBusMessageRegistryClientInterface $client,
    ) {
        parent::__construct($logger);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return SsoUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $this->logger->info('Got user email as an identifier', ['email' => $identifier]);
        $this->logger->info('Creating an "Get User Authentication Data" event');
        $event = GetUserAuthenticationDataEvent::create([
            'email' => $identifier,
        ]);
        $this->logger->info('"Get User Authentication Data" event has successfully created');

        $this->logger->info('Creating an EventBus message');
        $message = $this->messageFactory->create(SsoService::ROUTE, UserDataService::ROUTE, Uuid::uuid4()->getHex(), $event);
        $this->logger->info('Message has successfully created', [
            'source' => $message->getSource(),
            'destination' => $message->getDestination(),
            'id' => $message->getMessageId(),
            'payload' => $message->getPayload(),
        ]);

        $this->logger->info('Dispatching the message');
        $this->dispatcher->dispatch($message);

        $this->logger->info('Waiting for response');
        try {
            $regId = EventBusMessageRegistry::getRegistrationId($message);
            $this->logger->info('Registry', ['id' => $regId]);
            $responseMessage = $this->client->waitingForMessage($regId);
        } catch (EventBusMessageRegistryTimeoutException $exception) {
            $this->logger->error('Waiting time is out');
            throw new ApiException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'The service is experiencing problems processing messages'
            );
        }

        $this->logger->info('Got response message', [
            'source' => $responseMessage->getSource(),
            'destination' => $responseMessage->getDestination(),
            'id' => $responseMessage->getMessageId(),
            'payload' => $responseMessage->getPayload(),
        ]);

        $payload = $responseMessage->getPayload();
        if (!array_key_exists('type', $payload)) {
            $this->logger->error('Missing "type" field in the message payload');
            throw new ApiException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'The service is experiencing problems processing messages'
            );
        }

        $event = null;
        try {
            switch ($payload['type']) {
                case ErrorEventInterface::EVENT_TYPE:
                    $event = ErrorEvent::create($payload);
                    break;
                case UserAuthenticationDataEventInterface::EVENT_TYPE:
                    $event = UserAuthenticationDataEvent::create($payload);
                    break;
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'code' => $exception->getCode(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new ApiException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'The service is experiencing problems processing messages'
            );
        }

        if (null === $event) {
            $this->logger->error('Unexpected event. Skipping');
            throw new ApiException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'The service is experiencing problems processing messages'
            );
        }

        if ($event instanceof ErrorEventInterface) {
            $this->logger->info('Got Error event.', [
                'code' => $event->getCode(),
                'message' => $event->getMessage(),
            ]);

            throw new UserNotFoundException();
        }

        $this->logger->info('Creating SSO User');

        return new SsoUser(
            $event->getUserId(),
            $event->getEmail(),
            $event->getPasswordHash(),
            $event->getDisplayName(),
            $event->getPermissions()
        );
    }
}
