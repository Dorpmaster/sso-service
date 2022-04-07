<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface SsoUserInterface extends UserInterface, PasswordAuthenticatedUserInterface
{
    public function getDisplayName(): ?string;

    public function getEmail(): string;
}
