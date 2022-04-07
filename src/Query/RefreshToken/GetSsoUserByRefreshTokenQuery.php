<?php

namespace App\Query\RefreshToken;

use App\DependencyInjection\BaseLoggableService;
use App\Security\SsoUserInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class GetSsoUserByRefreshTokenQuery extends BaseLoggableService
{
    public function __construct(
        LoggerInterface $logger,
        protected CacheItemPoolInterface $cacheRefreshToken,
    ) {
        parent::__construct($logger);
    }

    public function __invoke(string $token): SsoUserInterface
    {
        try {
            $item = $this->cacheRefreshToken->getItem($token);
        } catch (InvalidArgumentException) {
            throw new UserNotFoundException();
        }

        if (!$item->isHit()) {
            throw new UserNotFoundException();
        }

        $user = $item->get();
        $this->cacheRefreshToken->deleteItem($token);

        return $user;
    }

}
