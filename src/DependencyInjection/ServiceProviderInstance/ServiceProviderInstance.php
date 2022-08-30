<?php

namespace App\DependencyInjection\ServiceProviderInstance;

use App\Domain\ServiceProviderInstance\ServiceProviderInstanceInterface;
use App\Domain\ServiceProviderInstance\ServiceProviderInstanceSettingsInterface;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Ramsey\Uuid\UuidInterface;

class ServiceProviderInstance implements ServiceProviderInstanceInterface
{
    public function __construct(
        protected UuidInterface $instanceId,
        protected UuidInterface $serviceId,
        protected UuidInterface $profileId,
        protected ServiceProviderInstanceStatus $status,
        protected ServiceProviderType $type,
        protected string $name,
        protected ServiceProviderInstanceSettingsInterface $settings,
    ) {
    }

    public function getInstanceId(): UuidInterface
    {
        return $this->instanceId;
    }

    public function getServiceId(): UuidInterface
    {
        return $this->serviceId;
    }

    public function getProfileId(): UuidInterface
    {
        return $this->profileId;
    }

    public function getStatus(): ServiceProviderInstanceStatus
    {
        return $this->status;
    }

    public function getServiceType(): ServiceProviderType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSettings(): ServiceProviderInstanceSettingsInterface
    {
        return $this->settings;
    }
}
