<?php

namespace App\Domain\Login;

interface LoginDtoInterface
{
    public function getEmail(): ?string;

    public function getPassword(): ?string;

    public function getIssuer(): ?string;

    public function getRelayState(): ?string;

    public function getRequestId(): ?string;

    public function setEmail(?string $email): void;

    public function setPassword(?string $password): void;

    public function setIssuer(?string $issuer): void;

    public function setRelayState(?string $relayState): void;

    public function setRequestId(?string $id): void;
}
