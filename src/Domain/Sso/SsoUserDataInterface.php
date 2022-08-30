<?php

namespace App\Domain\Sso;

use Ramsey\Uuid\UuidInterface;

interface SsoUserDataInterface
{
    public function getProfileId(): UuidInterface;

    public function getUserId(): UuidInterface;

    public function getEmail(): string;

    public function getPasswordHash(): string;
}
