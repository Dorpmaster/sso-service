<?php

namespace App\Query\Sso;

use App\DependencyInjection\EventDispatcherAwareService;
use App\DependencyInjection\Exception\EntityNotFoundException;
use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\Sso\SsoUserDataDto;
use App\Domain\Sso\SsoUserDataInterface;
use Dorpm\EntrypointMessages\Event\Error\ErrorCode;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\Error\ErrorEventInterface;
use Dorpm\EntrypointMessages\Event\UserDataService\GetSsoUserDataEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\SsoUserDataEvent;
use Dorpm\EntrypointMessages\Event\UserDataService\SsoUserDataEventInterface;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistry;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\SsoService;
use Dorpm\EntrypointMessages\Service\UserDataService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

class GetSsoUserDataQuery extends EventDispatcherAwareService
{
    public function __construct(
        LoggerInterface $logger,
        EventBusMessageFactoryInterface $messageFactory,
        EventBusMessageDispatcherInterface $dispatcher,
        EventBusMessageRegistryClientInterface $client,
        protected readonly CacheItemPoolInterface $cacheUserData,
    ) {
        parent::__construct($logger, $messageFactory, $dispatcher, $client);
    }

    /**
     * @throws EventBusMessageRegistryTimeoutException
     * @throws InvalidArgumentException
     * @throws IncorrectEventBusMessageException
     * @throws UnexpectedEventBusEventException
     * @throws ErrorEventException
     * @throws EntityNotFoundException
     * @throws Throwable
     */
    public function __invoke(UuidInterface $teamId, string $email): SsoUserDataInterface
    {
        $cacheKey = md5($teamId->toString().$email);
        $item = $this->cacheUserData->getItem($cacheKey);
        if ($item->isHit()) {
            return $item->get();
        }

        $this->logger->debug('Requesting a SSO User Data by the Team ID and Email', [
            'Team ID' => $teamId->toString(),
            'Email' => $email,
        ]);

        $this->logger->debug('Creating an "Get SSO User Data" event');
        $event = GetSsoUserDataEvent::create([
            'email' => $email,
            'teamId' => $teamId->toString(),
        ]);
        $this->logger->debug('"Get SSO User Data" event has successfully created');

        $this->logger->debug('Creating an EventBus message');
        $message = $this->messageFactory->create(
            SsoService::ROUTE,
            UserDataService::ROUTE,
            Uuid::uuid4()->getHex(),
            $event
        );
        $this->logger->debug('Message has successfully created', [
            'source' => $message->getSource(),
            'destination' => $message->getDestination(),
            'id' => $message->getMessageId(),
            'payload' => $message->getPayload(),
        ]);

        $this->logger->debug('Dispatching the message');
        $this->dispatcher->dispatch($message);

        $this->logger->debug('Waiting for response');
        $regId = EventBusMessageRegistry::getRegistrationId($message);
        $this->logger->debug('Registry', ['id' => $regId]);
        $responseMessage = $this->client->waitingForMessage($regId);

        $this->logger->debug('Got response message', [
            'source' => $responseMessage->getSource(),
            'destination' => $responseMessage->getDestination(),
            'id' => $responseMessage->getMessageId(),
            'payload' => $responseMessage->getPayload(),
        ]);

        $payload = $responseMessage->getPayload();
        if (!array_key_exists('type', $payload)) {
            $this->logger->error('Missing "type" field in the message payload');
            throw new IncorrectEventBusMessageException('Missing "type" field in the message payload');
        }

        $event = match ($payload['type']) {
            ErrorEventInterface::EVENT_TYPE => ErrorEvent::create($payload),
            SsoUserDataEventInterface::EVENT_TYPE => SsoUserDataEvent::create($payload),
            default => null,
        };

        if (null === $event) {
            $this->logger->error('Unexpected event. Skipping');
            throw new UnexpectedEventBusEventException();
        }

        if ($event instanceof ErrorEventInterface) {
            $this->logger->error('Got Error event.');
            if (ErrorCode::ENTITY_NOT_FOUND->value === $event->getCode()) {
                throw new EntityNotFoundException($event);
            }

            throw new ErrorEventException($event);
        }

        $this->logger->debug('Creating "SSO User Data" entity from the event');
        try {
            $userData = new SsoUserDataDto(
                profileId: Uuid::fromString($event->getProfileId()),
                userId: Uuid::fromString($event->getUserId()),
                email: $event->getEmail(),
                passwordHash: $event->getPasswordHash(),
            );
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }

        $item->set($userData);
        $this->cacheUserData->save($item);

        return $userData;
    }
}
