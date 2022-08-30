<?php

namespace App\Domain\ServiceProvider;

interface AtlassianSsoServiceProviderInterface extends ServiceProviderInterface
{
    public function getSettings(): AtlassianSsoSettingsInterface;
}
