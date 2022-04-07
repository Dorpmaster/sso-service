<?php

namespace App\Tests\Unit\Command\RefreshToken;

use App\Command\RefreshToken\CreateRefreshTokenCommand;
use App\Security\SsoUser;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;

class CreateRefreshTokenCommandTest extends TestCase
{
    protected LoggerInterface $logger;
    protected CacheItemPoolInterface $cache;
    protected int $ttl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttl = 1440;
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')
            ->willReturn(new CacheItem());
        $this->cache->expects(self::once())->method('save');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testCommand()
    {
        $command = new CreateRefreshTokenCommand(
            $this->logger,
            $this->cache,
            $this->ttl
        );

        $token = $command(new SsoUser('Test', 'Test', 'Test'));
        self::assertNotEmpty($token);
    }
}
