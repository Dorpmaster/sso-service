<?php

namespace App\DependencyInjection;

use Dorpm\EntrypointMessages\Message\Dispatcher\EventBusMessageDispatcherInterface;
use Dorpm\EntrypointMessages\Message\Factory\EventBusMessageFactoryInterface;
use Dorpm\EntrypointMessages\Message\Registry\EventBusMessageRegistryClientInterface;
use Psr\Log\LoggerInterface;

abstract class EventDispatcherAwareService extends BaseLoggableService
{
    public function __construct(
        LoggerInterface $logger,
        protected readonly EventBusMessageFactoryInterface $messageFactory,
        protected readonly EventBusMessageDispatcherInterface $dispatcher,
        protected readonly EventBusMessageRegistryClientInterface $client,
    ) {
        parent::__construct($logger);
    }
}
