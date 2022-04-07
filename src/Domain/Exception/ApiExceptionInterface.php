<?php

namespace App\Domain\Exception;

use App\Domain\Error\IssueInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

interface ApiExceptionInterface extends HttpExceptionInterface
{
    public function getErrorCode(): ?string;

    /**
     * @return array<int, IssueInterface>
     */
    public function getIssues(): array;
}
