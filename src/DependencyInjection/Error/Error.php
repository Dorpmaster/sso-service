<?php

namespace App\DependencyInjection\Error;

use App\Domain\Error\ErrorInterface;

class Error implements ErrorInterface
{
    public function __construct(
        protected string $message,
        protected ?string $code = null,
        protected array $issues = [],
    ) {
    }

    public function getErrorMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): ?string
    {
        return $this->code;
    }

    public function getIssues(): array
    {
        return $this->issues;
    }
}
