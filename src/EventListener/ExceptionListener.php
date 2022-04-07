<?php

namespace App\EventListener;

use App\DependencyInjection\Http\ApiExceptionResponse;
use App\Domain\Exception\ApiExceptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionListener implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $request = $event->getRequest();
        if (!in_array('application/json', $request->getAcceptableContentTypes())) {
            return;
        }

        $exception = $event->getThrowable();
        $httpStatusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $issues = $exception instanceof ApiExceptionInterface
            ? $exception->getIssues()
            : [];

        $errorCode = match (true) {
            $exception instanceof ApiExceptionInterface => $exception->getErrorCode(),
            $exception instanceof AccessDeniedHttpException => (string)Response::HTTP_FORBIDDEN,
            default => (string)$exception->getCode(),
        };

        $response = new ApiExceptionResponse(
            $httpStatusCode,
            $exception->getMessage(),
            $errorCode,
            $issues,
        );

        $event->setResponse($response);
    }
}
