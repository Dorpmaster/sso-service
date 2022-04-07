<?php

namespace App\EventBus\Handler;

use App\DependencyInjection\BaseLoggableService;
use Dorpm\EntrypointMessages\Message\EventBusMessageInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EventBusHandler extends BaseLoggableService
{
    public function __construct(
        LoggerInterface $logger,
        protected EventBusMessageRegistryInterface $registry,
    ) {
        parent::__construct($logger);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(EventBusMessageInterface $message)
    {
        $this->logger->info('Got a message', [
            'source' => $message->getSource(),
            'destination' => $message->getDestination(),
            'id' => $message->getMessageId(),
            'event' => $message->getPayload(),
        ]);

        $this->registry->register($message);
    }

}
