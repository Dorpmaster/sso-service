<?php

namespace App\Domain\ServiceProvider;

use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Ramsey\Uuid\UuidInterface;

interface ServiceProviderInterface
{
    public function getServiceId(): UuidInterface;

    public function getTeamId(): UuidInterface;

    public function getServiceType(): ServiceProviderType;

    public function getName(): string;

    public function getSettings(): ServiceProviderSettingsInterface;
}
