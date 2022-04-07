<?php

namespace App\DependencyInjection\Error;

use App\Domain\Error\IssueInterface;

class Issue implements IssueInterface
{
    public function __construct(
        protected string $issue,
        protected ?string $location = null,
    ) {
    }

    public function getIssue(): string
    {
        return $this->issue;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }
}
