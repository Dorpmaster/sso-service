<?php

namespace App\Controller;

use App\DependencyInjection\Login\LoginDto;
use App\Domain\Login\LoginDtoInterface;
use App\Domain\Saml\SamlResponseBuilderInterface;
use App\Form\Type\LoginType;
use App\Query\ServiceProvider\GetServiceProviderByTypeTagQuery;
use App\Query\Sso\GetSsoUserDataQuery;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/saml', name: 'saml_')]
class SamlController extends AbstractController
{
    #[Route(path: '/sso', name: 'sso', methods: ['GET', 'POST'])]
    public function loginForm(
        Request $request,
        GetServiceProviderByTypeTagQuery $serviceProviderQuery,
        GetSsoUserDataQuery $ssoUserDataQuery,
        SamlResponseBuilderInterface $samlResponseBuilder,
    ): Response {
        $samlRequest = $request->query->get('SAMLRequest');
        $relayState = $request->query->get('RelayState');

        if (empty($samlRequest) || empty($relayState)) {
            return $this->render('saml/wrong_request.html.twig');
        }

        // Workaround to get a correct binding type
        $clone = clone($request);
        $clone->setMethod(Request::METHOD_GET);
        $bindingFactory = new BindingFactory();
        $binding = $bindingFactory->getBindingByRequest($clone);

        $messageContext = new MessageContext();
        $binding->receive($request, $messageContext);

        $issuer = $messageContext->getMessage()->getIssuer()->getValue();
        $requestId = $messageContext->getMessage()->getID();

        $loginDto = new LoginDto();
        $loginDto->setRelayState($relayState);
        $loginDto->setIssuer($issuer);
        $loginDto->setRequestId($requestId);

        $form = $this->createForm(LoginType::class, $loginDto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var LoginDtoInterface $loginDto
             */
            $loginDto = $form->getData();

            // Getting the Service Provider
            $serviceProvider = ($serviceProviderQuery)(ServiceProviderType::atlassianSSO, $loginDto->getIssuer());

            // Getting the SSO User Data
            $ssoUserData = ($ssoUserDataQuery)($serviceProvider->getTeamId(), $loginDto->getEmail());

            return $samlResponseBuilder->buildSaml2PostResponse(
                $loginDto->getRequestId(),
                $ssoUserData->getUserId()->toString(),
                $loginDto->getEmail(),
                $loginDto->getIssuer(),
                $serviceProvider->getSettings()->getAssertionConsumerServiceUrl(),
                $serviceProvider->getSettings()->getX509Certificate(),
                $serviceProvider->getSettings()->getPrivateKey(),
                $loginDto->getRelayState(),
            );
        }

//        if (!$form->isSubmitted()) {
//            try {
//                $logger->debug('Getting Issuer from SAML request');
//                $issuer = $getIssuerQuery($request);
//                $logger->debug('Got the Issuer', ['issuer' => $issuer]);
//
//                $logger->debug('Looking for a Service Provider for this issuer', ['issuer' => $issuer]);
////            $serviceProvider = $serviceProviderByTypeTagQuery(ServiceProviderType::atlassianSSO, $issuer);
////            $logger->debug('Got the Service Provider', [
////                'ID' => $serviceProvider->getServiceId()->toString(),
////                'Name' => $serviceProvider->getName(),
////                'Team ID' => $serviceProvider->getTeamId()->toString(),
////            ]);
//            } catch (\Throwable $exception) {
//                $logger->error($exception->getMessage());
//                return $this->render('saml/unknown_provider.html.twig');
//            }
//        }

        return $this->renderForm('saml/login_form.html.twig', [
            'form' => $form,
        ]);
    }
}
