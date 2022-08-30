<?php

namespace App\Tests\Unit\DependencyInjection\ServiceProvider;

use App\DependencyInjection\ServiceProvider\AtlassianSsoServiceProvider;
use App\DependencyInjection\ServiceProvider\AtlassianSsoSettings;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class AtlassianSsoServiceProviderTest extends TestCase
{
    protected Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testProvider(): void
    {
        $provider = new AtlassianSsoServiceProvider(
            $serviceId = Uuid::uuid1(),
            $teamId = Uuid::uuid1(),
            $serviceType = ServiceProviderType::atlassianSSO,
            $name = $this->faker->word(),
            $settings = new AtlassianSsoSettings(
                $this->faker->url(),
                $this->faker->url(),
                $this->faker->sha256(),
                $this->faker->sha256(),
            ),
        );

        self::assertEquals($serviceId->toString(), $provider->getServiceId()->toString());
        self::assertEquals($teamId->toString(), $provider->getTeamId()->toString());
        self::assertEquals($serviceType, $provider->getServiceType());
        self::assertEquals($name, $provider->getName());
        self::assertEquals($settings, $provider->getSettings());
    }

}
