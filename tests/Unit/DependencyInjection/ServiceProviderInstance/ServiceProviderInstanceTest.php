<?php

namespace App\Tests\Unit\DependencyInjection\ServiceProviderInstance;

use App\DependencyInjection\ServiceProviderInstance\ServiceProviderInstance;
use App\DependencyInjection\ServiceProviderInstance\ServiceProviderInstanceSettings;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class ServiceProviderInstanceTest extends TestCase
{
    protected Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testEntity(): void
    {
        $entity = new ServiceProviderInstance(
            instanceId: $instanceId = Uuid::uuid1(),
            serviceId: $serviceId = Uuid::uuid1(),
            profileId: $profileId = Uuid::uuid1(),
            status: $status = ServiceProviderInstanceStatus::deployed,
            type: $type = ServiceProviderType::atlassianSSO,
            name: $name = $this->faker->word(),
            settings: $settings = new ServiceProviderInstanceSettings(),
        );

        self::assertEquals($instanceId->toString(), $entity->getInstanceId());
        self::assertEquals($serviceId->toString(), $entity->getServiceId());
        self::assertEquals($profileId->toString(), $entity->getProfileId());
        self::assertEquals($status, $entity->getStatus());
        self::assertEquals($type, $entity->getServiceType());
        self::assertEquals($name, $entity->getName());
        self::assertEquals($settings, $entity->getSettings());
    }

}
