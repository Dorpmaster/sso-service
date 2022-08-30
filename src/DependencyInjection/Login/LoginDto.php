<?php

namespace App\DependencyInjection\Login;

use App\Domain\Login\LoginDtoInterface;
use App\Validator as SsoValidator;
use Symfony\Component\Validator\Constraints as Assert;

#[SsoValidator\AtlassianSsoConstraint]
class LoginDto implements LoginDtoInterface
{
    private const ERROR_MESSAGE = 'Something went wrong. Looks like we\'ve got a broken SAML request.';
    #[Assert\NotBlank]
    #[Assert\Email]
    protected ?string $email = null;

    #[Assert\NotBlank]
    protected ?string $password = null;

    #[Assert\NotBlank(message: self::ERROR_MESSAGE)]
    protected ?string $issuer = null;

    #[Assert\NotBlank(message: self::ERROR_MESSAGE)]
    protected ?string $requestId = null;

    #[Assert\NotBlank(message: self::ERROR_MESSAGE)]
    protected ?string $relayState = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function getRelayState(): ?string
    {
        return $this->relayState;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function setIssuer(?string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function setRelayState(?string $relayState): void
    {
        $this->relayState = $relayState;
    }

    public function setRequestId(?string $id): void
    {
        $this->requestId = $id;
    }
}
