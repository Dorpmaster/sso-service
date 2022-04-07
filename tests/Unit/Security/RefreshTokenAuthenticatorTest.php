<?php

namespace App\Tests\Unit\Security;

use App\Command\RefreshToken\CreateRefreshTokenCommand;
use App\Security\RefreshTokenAuthenticator;
use App\Security\SsoUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class RefreshTokenAuthenticatorTest extends TestCase
{
    protected JWTTokenManagerInterface $manager;
    protected CreateRefreshTokenCommand $command;
    protected int $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = time();
        $this->manager = $this->createMock(JWTTokenManagerInterface::class);
        $this->manager->method('create')
            ->willReturn('Test');
        $this->manager->method('decode')
            ->willReturn(['exp' => $this->now]);
        $this->command = $this->createMock(CreateRefreshTokenCommand::class);
        $this->command->method('__invoke')
            ->willReturn('Test');
    }

    public function testSupports()
    {
        $request = $this->createMock(Request::class);
        $request->method('toArray')
            ->willReturn(['refreshToken' => 'test']);

        $authenticator = new RefreshTokenAuthenticator($this->manager, $this->command);

        self::assertTrue($authenticator->supports($request));
    }

    public function testNotSupports()
    {
        $request = $this->createMock(Request::class);
        $request->method('toArray')
            ->willThrowException(new \JsonException());

        $authenticator = new RefreshTokenAuthenticator($this->manager, $this->command);

        self::assertFalse($authenticator->supports($request));
    }

    public function testAuthenticate()
    {
        $request = $this->createMock(Request::class);
        $request->method('toArray')
            ->willReturn(['refreshToken' => 'test']);

        $authenticator = new RefreshTokenAuthenticator($this->manager, $this->command);
        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticationSuccess()
    {
        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')
            ->willReturn(new SsoUser('Test', 'Test', 'Test'));

        $authenticator = new RefreshTokenAuthenticator($this->manager, $this->command);
        $response = $authenticator->onAuthenticationSuccess($request, $token, 'test');

        self::assertInstanceOf(JsonResponse::class, $response);
        $content = json_decode($response->getContent(), true);
        self::assertSame('Test', $content['accessToken']);
        self::assertSame('Test', $content['refreshToken']);
        self::assertSame((new \DateTimeImmutable('@'.$this->now))->format(\DateTimeInterface::ISO8601), $content['expiresAt']);
    }

    public function testAuthenticationFailure()
    {
        $request = $this->createMock(Request::class);

        $authenticator = new RefreshTokenAuthenticator($this->manager, $this->command);
        $response = $authenticator->onAuthenticationFailure($request, new AuthenticationException());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
