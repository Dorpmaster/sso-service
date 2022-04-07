<?php

namespace App\Security;

use App\DependencyInjection\BaseLoggableService;
use App\Query\RefreshToken\GetSsoUserByRefreshTokenQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RefreshTokenUserProvider extends BaseLoggableService implements UserProviderInterface
{
    public function __construct(
        LoggerInterface $logger,
        protected GetSsoUserByRefreshTokenQuery $query,
    ) {
        parent::__construct($logger);
    }


    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return SsoUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return ($this->query)($identifier);
    }
}
