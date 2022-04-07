<?php

namespace App\Domain\Error;

interface IssueInterface
{
    public function getIssue(): string;

    public function getLocation(): ?string;
}
