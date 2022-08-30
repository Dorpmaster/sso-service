<?php

namespace App\Query\ServiceProvider;

use App\DependencyInjection\EventDispatcherAwareService;
use App\DependencyInjection\Exception\EntityNotFoundException;
use App\DependencyInjection\Exception\ErrorEventException;
use App\DependencyInjection\Exception\IncorrectEventBusMessageException;
use App\DependencyInjection\Exception\UnexpectedEventBusEventException;
use App\DependencyInjection\ServiceProvider\AtlassianSsoServiceProvider;
use App\DependencyInjection\ServiceProvider\AtlassianSsoSettings;
use App\Domain\ServiceProvider\ServiceProviderInterface;
use Dorpm\EntrypointMessages\Event\Error\ErrorCode;
use Dorpm\EntrypointMessages\Event\Error\ErrorEvent;
use Dorpm\EntrypointMessages\Event\Error\ErrorEventInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\GetServiceProviderByTypeTagEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderEvent;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderEventInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistry;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryTimeoutException;
use Dorpm\EntrypointMessages\Service\ResourceService;
use Dorpm\EntrypointMessages\Service\SsoService;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

class GetServiceProviderByTypeTagQuery extends EventDispatcherAwareService
{
    public function __construct(
        LoggerInterface $logger,
        EventBusMessageFactoryInterface $messageFactory,
        EventBusMessageDispatcherInterface $dispatcher,
        EventBusMessageRegistryClientInterface $client,
        protected readonly CacheItemPoolInterface $cacheServiceProvider,
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
    public function __invoke(ServiceProviderType $type, string $tag): ServiceProviderInterface
    {
        $cacheKey = md5($tag);
        $item = $this->cacheServiceProvider->getItem($cacheKey);
        if ($item->isHit()) {
            return $item->get();
        }

        $this->logger->debug('Requesting a Service Provider by Type and Tag', [
            'type' => $type->name,
            'tag' => $tag,
        ]);

        $this->logger->debug('Creating an "Get Service Provider By Type and Tag" event');
        $event = GetServiceProviderByTypeTagEvent::create([
            'spType' => $type->name,
            'tag' => $tag,
        ]);
        $this->logger->debug('"Get Service Provider By Type and Tag" event has successfully created');

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
            ServiceProviderEventInterface::EVENT_TYPE => ServiceProviderEvent::create($payload),
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

        $this->logger->debug('Creating "Service Provider" entity from the event');
        try {
            $spSettings = match ($event->getType()) {
                ServiceProviderType::atlassianSSO->name => new AtlassianSsoSettings(
                    $event->getSettings()['assertionConsumerServiceUrl'],
                    $event->getSettings()['audienceUrl'],
                    $event->getSettings()['x509Certificate'],
                    $event->getSettings()['privateKey'],
                ),
                default => throw new Exception('Could not get a correct Service Provider settings'),
            };

            $serviceProvider = match ($event->getType()) {
                ServiceProviderType::atlassianSSO->name => new AtlassianSsoServiceProvider(
                    serviceId: Uuid::fromString($event->getServiceId()),
                    teamId: Uuid::fromString($event->getTeamId()),
                    serviceType: ServiceProviderType::tryFromStringValue($event->getType()),
                    name: $event->getName(),
                    settings: $spSettings,
                ),
                default => throw new Exception('Could not create a correct Service Provider entity'),
            };

            $item->set($serviceProvider);
            $this->cacheServiceProvider->save($item);

            return $serviceProvider;
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }
    }
}
