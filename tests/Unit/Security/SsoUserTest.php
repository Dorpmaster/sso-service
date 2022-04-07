<?php

namespace App\Tests\Unit\Security;

use App\Security\SsoUser;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

class SsoUserTest extends TestCase
{
    protected Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testUser()
    {
        $userId = $this->faker->uuid();
        $email = $this->faker->email();
        $passwordHash = $this->faker->password();
        $displayName = $this->faker->name();
        $permissions = ['TEST_1', 'TEST_2'];

        $user = new SsoUser($userId, $email, $passwordHash, $displayName, $permissions);
        self::assertSame($userId, $user->getUserIdentifier());
        self::assertSame($email, $user->getEmail());
        self::assertSame($passwordHash, $user->getPassword());
        self::assertSame($displayName, $user->getDisplayName());
        self::assertSame($permissions, $user->getRoles());
    }

    public function testDefaults()
    {
        $userId = $this->faker->uuid();
        $email = $this->faker->email();
        $passwordHash = $this->faker->password();

        $user = new SsoUser($userId, $email, $passwordHash);
        self::assertSame($userId, $user->getUserIdentifier());
        self::assertSame($email, $user->getEmail());
        self::assertSame($passwordHash, $user->getPassword());
        self::assertNull($user->getDisplayName());
        self::assertIsArray($user->getRoles());
        self::assertCount(0, $user->getRoles());
    }

}
