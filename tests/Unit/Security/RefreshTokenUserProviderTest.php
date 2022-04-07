<?php

namespace App\Tests\Unit\Security;

use App\Query\RefreshToken\GetSsoUserByRefreshTokenQuery;
use App\Security\RefreshTokenUserProvider;
use App\Security\SsoUser;
use App\Security\SsoUserInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class RefreshTokenUserProviderTest extends TestCase
{
    protected LoggerInterface $logger;
    protected GetSsoUserByRefreshTokenQuery $query;
    protected SsoUserInterface $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new SsoUser('Test', 'Test', 'Test');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->query = $this->createMock(GetSsoUserByRefreshTokenQuery::class);
        $this->query->method('__invoke')
            ->with('token')
            ->willReturn($this->user);
    }

    public function testRefreshUser()
    {
        $provider = new RefreshTokenUserProvider(
            $this->logger,
            $this->query
        );

        self::assertSame($this->user, $provider->refreshUser($this->user));
    }

    public function testSupports()
    {
        $provider = new RefreshTokenUserProvider(
            $this->logger,
            $this->query
        );

        self::assertTrue($provider->supportsClass(SsoUser::class));
        self::assertFalse($provider->supportsClass('wrong class'));
    }

    public function testLoadUserByIdentifier()
    {
        $provider = new RefreshTokenUserProvider(
            $this->logger,
            $this->query
        );

        self::assertSame($this->user, $provider->loadUserByIdentifier('token'));
    }

    public function testUserNotFound()
    {
        $this->query = $this->createMock(GetSsoUserByRefreshTokenQuery::class);
        $this->query->method('__invoke')
            ->willThrowException(new UserNotFoundException());

        $provider = new RefreshTokenUserProvider(
            $this->logger,
            $this->query
        );

        self::expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('token');
    }
}
