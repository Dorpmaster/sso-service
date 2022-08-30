<?php

namespace App\DependencyInjection\Sso;

use Ramsey\Uuid\UuidInterface;

class SsoUserDataDto implements \App\Domain\Sso\SsoUserDataInterface
{
    public function __construct(
        protected readonly UuidInterface $profileId,
        protected readonly UuidInterface $userId,
        protected readonly string $email,
        protected readonly string $passwordHash,
    ) {
    }

    public function getProfileId(): UuidInterface
    {
        return $this->profileId;
    }

    public function getUserId(): UuidInterface
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}
