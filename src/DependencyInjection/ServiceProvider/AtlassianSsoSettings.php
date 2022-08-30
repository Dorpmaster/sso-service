<?php

namespace App\DependencyInjection\ServiceProvider;

use App\Domain\ServiceProvider\AtlassianSsoSettingsInterface;

class AtlassianSsoSettings implements AtlassianSsoSettingsInterface
{
    public function __construct(
        protected readonly string $assertionConsumerServiceUrl,
        protected readonly string $audienceUrl,
        protected readonly string $x509Certificate,
        protected readonly string $privateKey,
    ) {
    }

    public function getAssertionConsumerServiceUrl(): string
    {
        return $this->assertionConsumerServiceUrl;
    }

    public function getAudienceUrl(): string
    {
        return $this->audienceUrl;
    }

    public function getX509Certificate(): string
    {
        return $this->x509Certificate;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

}
