<?php

namespace App\Command\RefreshToken;

use App\DependencyInjection\BaseLoggableService;
use App\Security\SsoUserInterface;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class CreateRefreshTokenCommand extends BaseLoggableService
{
    public function __construct(
        LoggerInterface $logger,
        protected CacheItemPoolInterface $cacheRefreshToken,
        protected int $ttl,
    ) {
        parent::__construct($logger);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function __invoke(SsoUserInterface $user): string
    {
        $this->logger->info('Creating a new refresh token for SSO user', [
            'id' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
        ]);

        $token = bin2hex(random_bytes(50));

        $item = $this->cacheRefreshToken->getItem($token);
        $item->expiresAfter($this->ttl);

        $item->set($user);
        $this->cacheRefreshToken->save($item);

        $this->logger->info('Token has successfully saved to cache', [
            'ttl' => $this->ttl,
        ]);

        return $token;
    }

}
