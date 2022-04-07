<?php

namespace App\EventListener;

use App\Command\RefreshToken\CreateRefreshTokenCommand;
use App\Security\SsoUserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;

class JwtAuthenticationSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        protected JWTTokenManagerInterface $jwtTokenManager,
        protected Security $security,
        protected CreateRefreshTokenCommand $command,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => ['onAuthenticationSuccess', 0],
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        $payload = $event->getData();
        if (!empty($payload['token'])) {
            $payload['accessToken'] = $payload['token'];
            unset($payload['token']);
        }

        $payload['refreshToken'] = null;
        $user = $this->security->getUser();
        if ($user instanceof SsoUserInterface) {
            $payload['refreshToken'] = ($this->command)($user);
        }

        // Getting expiration data from the access token
        $token = new JWTUserToken();
        $token->setRawToken($payload['accessToken']);
        $decodedToken = $this->jwtTokenManager->decode($token);
        $exp = $decodedToken['exp'];
        $expirationDate = new \DateTimeImmutable("@$exp");
        $payload['expiresAt'] = $expirationDate->format(\DateTimeInterface::ISO8601);

        $event->setData($payload);
    }
}
