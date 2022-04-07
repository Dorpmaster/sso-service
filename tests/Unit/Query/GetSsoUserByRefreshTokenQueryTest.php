<?php

namespace App\Tests\Unit\Query;

use App\Query\RefreshToken\GetSsoUserByRefreshTokenQuery;
use App\Security\SsoUser;
use App\Security\SsoUserInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class GetSsoUserByRefreshTokenQueryTest extends TestCase
{
    protected LoggerInterface $logger;
    protected CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );

        $item = $createCacheItem('test', new SsoUser('Test', 'Test', 'Test'), true);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn($item);
    }

    public function testQuery()
    {
        $query = new GetSsoUserByRefreshTokenQuery($this->logger, $this->cache);

        $user = $query('token');
        self::assertInstanceOf(SsoUserInterface::class, $user);
    }

    public function testUserNotFound()
    {
        $createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );

        $item = $createCacheItem('test', null, false);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn($item);

        $query = new GetSsoUserByRefreshTokenQuery($this->logger, $this->cache);

        self::expectException(UserNotFoundException::class);
        $query('token');
    }
}
