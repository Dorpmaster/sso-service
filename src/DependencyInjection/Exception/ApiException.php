<?php

namespace App\DependencyInjection\Exception;

use App\Domain\Exception\ApiExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException implements ApiExceptionInterface
{
    public function __construct(
        int $statusCode,
        string $message = '',
        protected ?string $errorCode = null,
        protected array $issues = [],
        \Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getIssues(): array
    {
        return $this->issues;
    }
}
