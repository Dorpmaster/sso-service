<?php

namespace App\Tests\Functional\Command\RefreshToken;

use App\Command\RefreshToken\CreateRefreshTokenCommand;
use App\Security\SsoUser;
use App\Security\SsoUserInterface;
use App\Tests\Functional\AbstractKernelTestCase;

class CreateRefreshTokenCommandTest extends AbstractKernelTestCase
{
    public function testCommand()
    {
        $cache = self::getContainer()->get('cache.refresh_token');
        $command = self::getContainer()->get(CreateRefreshTokenCommand::class);

        $user = new SsoUser('Test', 'Test', 'Test');
        $token = $command($user);

        self::assertNotEmpty($token);
        $item = $cache->getItem($token);
        self::assertTrue($item->isHit());
        self::assertInstanceOf(SsoUserInterface::class, $item->get());
    }
}
