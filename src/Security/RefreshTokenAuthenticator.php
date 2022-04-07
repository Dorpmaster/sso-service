<?php

namespace App\Security;

use App\Command\RefreshToken\CreateRefreshTokenCommand;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Throwable;

class RefreshTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        protected JWTTokenManagerInterface $manager,
        protected CreateRefreshTokenCommand $command,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        try {
            $request->toArray();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $refreshToken = $request->toArray()['refreshToken'];

        return new SelfValidatingPassport(new UserBadge($refreshToken));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $accessToken = $this->manager->create($user);
        $refreshToken = ($this->command)($user);

        // Getting expiration data from the access token
        $jwt = new JWTUserToken();
        $jwt->setRawToken($accessToken);
        $decodedToken = $this->manager->decode($jwt);
        $exp = $decodedToken['exp'];
        $expirationDate = new \DateTimeImmutable("@$exp");

        return new JsonResponse([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresAt' => $expirationDate->format(\DateTimeInterface::ISO8601),
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(null, Response::HTTP_UNAUTHORIZED);
    }
}
