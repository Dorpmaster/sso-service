<?php

namespace App\Domain\ServiceProviderInstance;

use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Ramsey\Uuid\UuidInterface;

interface ServiceProviderInstanceInterface
{
    public function getInstanceId(): UuidInterface;

    public function getServiceId(): UuidInterface;

    public function getProfileId(): UuidInterface;

    public function getStatus(): ServiceProviderInstanceStatus;

    public function getServiceType(): ServiceProviderType;

    public function getName(): string;

    public function getSettings(): ServiceProviderInstanceSettingsInterface;
}
