<?php

namespace App\Domain\Error;

interface ErrorInterface
{
    public function getErrorMessage(): string;

    public function getErrorCode(): ?string;

    /**
     * @return array<int, IssueInterface>
     */
    public function getIssues(): array;
}
