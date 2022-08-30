<?php

namespace App\Domain\ServiceProvider;

interface AtlassianSsoSettingsInterface extends ServiceProviderSettingsInterface
{
    public function getAssertionConsumerServiceUrl(): string;

    public function getAudienceUrl(): string;

    public function getX509Certificate(): string;

    public function getPrivateKey(): string;
}
