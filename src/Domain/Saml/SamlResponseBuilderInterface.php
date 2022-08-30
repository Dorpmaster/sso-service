<?php

namespace App\Domain\Saml;

use Symfony\Component\HttpFoundation\Response;

interface SamlResponseBuilderInterface
{
    public const IDP_NAME = 'entrypoint';

    public function buildSaml2PostResponse(
        string $messageId,
        string $userId,
        string $userEmail,
        string $issuer,
        string $assertionConsumerServiceUrl,
        string $certificate,
        string $privateKey,
        string $relateState,
    ): Response;
}
