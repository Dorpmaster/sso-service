<?php

namespace App\DependencyInjection;

use Psr\Log\LoggerInterface;
use Throwable;

abstract class BaseLoggableService
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    protected function logException(Throwable $exception, $message = 'An error has occurred'): void
    {
        $this->logger->error($message, [
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

}
