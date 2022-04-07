<?php

namespace App\EventListener;

use App\Security\SsoUserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JwtCreatedListener implements EventSubscriberInterface
{

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => ['onJwtCreated', 0],
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event)
    {
        $payload = $event->getData();
        if (!empty($payload['userIdentifier'])) {
            unset($payload['userIdentifier']);
        }

        $user = $event->getUser();
        if ($user instanceof SsoUserInterface) {
            $payload['sub'] = $user->getUserIdentifier();
            $payload['displayName'] = $user->getDisplayName();
            $payload['email'] = $user->getEmail();
        }

        $event->setData($payload);
    }
}
