<?php

namespace App\Tests\Functional\Query\RefreshToken;

use App\Query\RefreshToken\GetSsoUserByRefreshTokenQuery;
use App\Security\SsoUser;
use App\Security\SsoUserInterface;
use App\Tests\Functional\AbstractKernelTestCase;

class GetSsoUserByRefreshTokenQueryTest extends AbstractKernelTestCase
{
    public function testQuery()
    {
        $cache = self::getContainer()->get('cache.refresh_token');
        $query = self::getContainer()->get(GetSsoUserByRefreshTokenQuery::class);

        $user = new SsoUser('Test', 'Test', 'Test');
        $token = 'test_token';
        $item = $cache->getItem($token);
        $item->set($user);
        $cache->save($item);

        $cachedUser = $query($token);
        self::assertInstanceOf(SsoUserInterface::class, $cachedUser);
        self::assertSame('Test', $cachedUser->getUserIdentifier());
    }
}
