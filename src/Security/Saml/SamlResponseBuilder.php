<?php

namespace App\Security\Saml;

use App\Domain\Saml\SamlResponseBuilderInterface;
use LightSaml\Binding\BindingFactory;
use LightSaml\ClaimTypes;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Credential\X509Certificate;
use LightSaml\Helper;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\AudienceRestriction;
use LightSaml\Model\Assertion\AuthnContext;
use LightSaml\Model\Assertion\AuthnStatement;
use LightSaml\Model\Assertion\Conditions;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Assertion\SubjectConfirmation;
use LightSaml\Model\Assertion\SubjectConfirmationData;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\XmlDSig\SignatureWriter;
use LightSaml\SamlConstants;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\HttpFoundation\Response;

class SamlResponseBuilder implements SamlResponseBuilderInterface
{

    public function buildSaml2PostResponse(
        string $messageId,
        string $userId,
        string $userEmail,
        string $issuer,
        string $assertionConsumerServiceUrl,
        string $certificate,
        string $privateKey,
        string $relateState,
    ): Response {
        $bindingFactory = new BindingFactory();

        $serializationContext = new SerializationContext();
        $response = new \LightSaml\Model\Protocol\Response();
        $response->addAssertion($assertion = new Assertion())
            ->setStatus(new Status(new StatusCode(SamlConstants::STATUS_SUCCESS)))
            ->setID(Helper::generateID())
            ->setIssueInstant(new \DateTime('now', new \DateTimeZone('UTC')))
            ->setDestination($assertionConsumerServiceUrl)
            ->setIssuer(new Issuer(self::IDP_NAME));

        $assertion->setId(Helper::generateID())
            ->setIssueInstant(new \DateTime('now', new \DateTimeZone('UTC')))
            ->setSubject(
                (new Subject())
                    ->setNameID(
                        new NameID(
                            $userId,
                            SamlConstants::NAME_ID_FORMAT_UNSPECIFIED
                        )
                    )
                    ->addSubjectConfirmation(
                        (new SubjectConfirmation())
                            ->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER)
                            ->setSubjectConfirmationData(
                                (new SubjectConfirmationData())
                                    ->setInResponseTo($messageId)
                                    ->setNotOnOrAfter(new \DateTime('+1 MINUTE', new \DateTimeZone('UTC')))
                                    ->setRecipient($assertionConsumerServiceUrl)
                            )
                    )
            )
            ->setConditions(
                (new Conditions())
                    ->setNotBefore(new \DateTime('-1 MINUTE', new \DateTimeZone('UTC')))
                    ->setNotOnOrAfter(new \DateTime('+1 MINUTE', new \DateTimeZone('UTC')))
                    ->addItem(new AudienceRestriction([$issuer]))
            )
            ->addItem(
                (new AttributeStatement())
                    ->addAttribute(new Attribute(ClaimTypes::EMAIL_ADDRESS, $userEmail))
            )
            ->addItem(
                (new AuthnStatement())
                    ->setAuthnInstant(new \DateTime('-10 MINUTE', new \DateTimeZone('UTC')))
                    ->setSessionIndex($assertion->getId())
                    ->setAuthnContext(
                        (new AuthnContext())
                            ->setAuthnContextClassRef(SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT)
                    )
            );

        $secretKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $secretKey->loadKey($privateKey);

        $response->setSignature(
            new SignatureWriter(
                (new X509Certificate())->loadPem($certificate),
                $secretKey
            )
        );

        $response->serialize($serializationContext->getDocument(), $serializationContext);
        $response->setDestination($assertionConsumerServiceUrl);
        $response->setRelayState($relateState);

        $postBinding = $bindingFactory->create(SamlConstants::BINDING_SAML2_HTTP_POST);
        $respContext = new MessageContext();
        $respContext->setMessage($response);

        return $postBinding->send($respContext);
    }
}
