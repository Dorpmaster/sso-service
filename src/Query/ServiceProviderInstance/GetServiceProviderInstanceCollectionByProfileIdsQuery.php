<?php

namespace App\Query\ServiceProviderInstance;

use App\DependencyInjection\EventDispatcherAwareService;
use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\ServiceProviderInstance\ServiceProviderInstance;
use App\DependencyInjection\ServiceProviderInstance\ServiceProviderInstanceSettings;
use App\Domain\ServiceProviderInstance\ServiceProviderInstanceInterface;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\Error\ErrorEventInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\GetServiceProviderInstanceInstanceCollectionByProfileIdsEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceCollectionEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceCollectionEventInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistry;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\ResourceService;
use Dorpm\EntrypointMessages\Service\SsoService;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

class GetServiceProviderInstanceCollectionByProfileIdsQuery extends EventDispatcherAwareService
{
    /**
     * @return iterable<int, ServiceProviderInstanceInterface>
     * @throws EventBusMessageRegistryTimeoutException
     * @throws InvalidArgumentException
     * @throws IncorrectEventBusMessageException
     * @throws UnexpectedEventBusEventException
     * @throws ErrorEventException
     * @var array<int, UuidInterface> $profileIds
     */
    public function __invoke(array $profileIds): iterable
    {
        $ids = array_map(function (UuidInterface $id) {
            return $id->toString();
        }, $profileIds);
        $this->logger->debug('Requesting a Service Provider Instance Collection by Profile IDs', ['profileIds' => $ids]);

        $this->logger->debug('Creating an "Get Service Provider Instance Collection By Profile Ids" event');
        $event = GetServiceProviderInstanceInstanceCollectionByProfileIdsEvent::create(['profileIds' => $ids]);
        $this->logger->debug('"Get Service Provider Instance Collection By Profile Ids" event has successfully created');

        $this->logger->debug('Creating an EventBus message');
        $message = $this->messageFactory->create(
            SsoService::ROUTE,
            ResourceService::ROUTE,
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
            ServiceProviderInstanceCollectionEventInterface::EVENT_TYPE => ServiceProviderInstanceCollectionEvent::create($payload),
            default => null,
        };

        if (null === $event) {
            $this->logger->error('Unexpected event. Skipping');
            throw new UnexpectedEventBusEventException();
        }

        if ($event instanceof ErrorEventInterface) {
            $this->logger->debug('Got Error event.');

            throw new ErrorEventException($event);
        }

        $this->logger->debug('Creating a collection of "Service Provider Instance" entities from the event');

        $collection = [];
        foreach ($event->getCollection() as $item) {
            try {
                $collection[] = new ServiceProviderInstance(
                    instanceId: Uuid::fromString($item['instanceId']),
                    serviceId: Uuid::fromString($item['serviceId']),
                    profileId: Uuid::fromString($item['profileId']),
                    status: ServiceProviderInstanceStatus::tryFromStringValue($item['status']),
                    type: ServiceProviderType::tryFromStringValue($item['spType']),
                    name: $item['name'],
                    settings: new ServiceProviderInstanceSettings(),
                );
            } catch (Throwable $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

        return $collection;
    }
}
