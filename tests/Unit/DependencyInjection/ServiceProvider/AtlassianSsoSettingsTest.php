<?php

namespace App\Tests\Unit\DependencyInjection\ServiceProvider;

use App\DependencyInjection\ServiceProvider\AtlassianSsoSettings;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

class AtlassianSsoSettingsTest extends TestCase
{
    protected Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testSettings(): void
    {
        $settings = new AtlassianSsoSettings(
            $acs = $this->faker->url(),
            $au = $this->faker->url(),
            $certificate = $this->faker->sha256(),
            $privateKey = $this->faker->sha256(),
        );

        self::assertEquals($acs, $settings->getAssertionConsumerServiceUrl());
        self::assertEquals($au, $settings->getAudienceUrl());
        self::assertEquals($certificate, $settings->getX509Certificate());
        self::assertEquals($privateKey, $settings->getPrivateKey());
    }
}
