<?php

namespace App\DependencyInjection\Exception;

use Dorpm\EntrypointMessages\Event\Error\ErrorEventInterface;
use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class ErrorEventException extends Exception
{
    #[Pure] public function __construct(
        protected ErrorEventInterface $errorEvent,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorEvent(): ErrorEventInterface
    {
        return $this->errorEvent;
    }

}
