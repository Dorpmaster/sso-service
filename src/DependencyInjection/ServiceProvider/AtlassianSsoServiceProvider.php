<?php

namespace App\DependencyInjection\ServiceProvider;

use App\Domain\ServiceProvider\AtlassianSsoServiceProviderInterface;
use App\Domain\ServiceProvider\AtlassianSsoSettingsInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Ramsey\Uuid\UuidInterface;

class AtlassianSsoServiceProvider implements AtlassianSsoServiceProviderInterface
{
    public function __construct(
        protected UuidInterface $serviceId,
        protected UuidInterface $teamId,
        protected ServiceProviderType $serviceType,
        protected string $name,
        protected AtlassianSsoSettingsInterface $settings,
    ) {
    }

    public function getSettings(): AtlassianSsoSettingsInterface
    {
        return $this->settings;
    }

    public function getServiceId(): UuidInterface
    {
        return $this->serviceId;
    }

    public function getTeamId(): UuidInterface
    {
        return $this->teamId;
    }

    public function getServiceType(): ServiceProviderType
    {
        return $this->serviceType;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
