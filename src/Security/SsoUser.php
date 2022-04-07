<?php

namespace App\Security;

class SsoUser implements SsoUserInterface
{
    public function __construct(
        private string $userId,
        private string $email,
        private string $passwordHash,
        private ?string $displayName = null,
        private array $permissions = [],
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getRoles(): array
    {
        return $this->permissions;
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userId;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }
}
