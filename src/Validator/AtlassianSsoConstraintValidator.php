<?php

namespace App\Validator;

use App\DependencyInjection\Exception\EntityNotFoundException;
use App\DependencyInjection\Login\LoginDto;
use App\Domain\Login\LoginDtoInterface;
use App\Domain\ServiceProviderInstance\ServiceProviderInstanceInterface;
use App\Query\ServiceProvider\GetServiceProviderByTypeTagQuery;
use App\Query\ServiceProviderInstance\GetServiceProviderInstanceCollectionByProfileIdsQuery;
use App\Query\Sso\GetSsoUserDataQuery;
use App\Security\SsoUser;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderInstanceStatus;
use Dorpm\EntrypointMessages\Event\ResourceService\ServiceProviderType;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Throwable;

class AtlassianSsoConstraintValidator extends ConstraintValidator
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly RequestStack $requestStack,
        protected readonly GetServiceProviderByTypeTagQuery $getServiceProviderQuery,
        protected readonly GetSsoUserDataQuery $ssoUserDataQuery,
        protected readonly GetServiceProviderInstanceCollectionByProfileIdsQuery $instanceQuery,
        protected readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof AtlassianSsoConstraint) {
            throw new UnexpectedTypeException($constraint, AtlassianSsoConstraint::class);
        }

        if (!$value instanceof LoginDto) {
            throw new UnexpectedValueException($value, LoginDto::class);
        }

        // Skip validation if DTO has empty value.
        if ($this->hasEmptyValues($value)) {
            return;
        }

        // Getting the Service Provider
        try {
            $serviceProvider = ($this->getServiceProviderQuery)(ServiceProviderType::atlassianSSO, $value->getIssuer());
        } catch (EntityNotFoundException) {
            $this->logger->error('Service provider is not found.', ['tag' => $value->getIssuer()]);
            $this->context->buildViolation('Looks like you are trying to use own Identity Provider, but your service provider is not registered in our system.')
                ->addViolation();
            return;
        } catch (Throwable $exception) {
            $this->logger->error('An exception had thrown while a service provider requested.', [
                'error' => $exception->getMessage(),
            ]);

            $this->context->buildViolation('Something went wrong on our side. Please try again.')
                ->addViolation();

            return;
        }

        // Getting the SSO User Data
        try {
            $ssoUserData = ($this->ssoUserDataQuery)($serviceProvider->getTeamId(), $value->getEmail());
        } catch (EntityNotFoundException) {
            $this->logger->error('Teammate profile is not found.', [
                'email' => $value->getEmail(),
                'Team ID' => $serviceProvider->getTeamId()->toString(),
            ]);
            $this->context->buildViolation('This user is not allowed to use this service provider.')
                ->addViolation();
            return;
        } catch (Throwable $exception) {
            $this->logger->error('An exception had thrown while a service provider requested.', [
                'error' => $exception->getMessage(),
            ]);

            $this->context->buildViolation('Something went wrong on our side. Please try again.')
                ->addViolation();

            return;
        }

        // Getting the Service Provider Instance collection
        try {
            $instances = array_filter(($this->instanceQuery)([$ssoUserData->getProfileId()]), static function (ServiceProviderInstanceInterface $instance) use ($ssoUserData) {
                return $instance->getProfileId()->toString() === $ssoUserData->getProfileId()->toString();
            });

            if (false === $instance = reset($instances)) {
                $this->logger->error('Service provider instance is not found.', ['Profile ID' => $ssoUserData->getProfileId()->toString()]);
                $this->context->buildViolation('This user is not allowed to use this service provider.')
                    ->addViolation();
                return;
            }

            if (ServiceProviderInstanceStatus::deployed !== $instance->getStatus()) {
                $this->logger->error('Service provider instance is not deployed for this user.', ['status' => $instance->getStatus()->name]);
                $this->context->buildViolation('This user is not allowed to use this service provider.')
                    ->addViolation();
                return;
            }
        } catch (Throwable $exception) {
            $this->logger->error('An exception had thrown while a service provider requested.', [
                'error' => $exception->getMessage(),
            ]);

            $this->context->buildViolation('Something went wrong on our side. Please try again.')
                ->addViolation();

            return;
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid(
            new SsoUser(
                Uuid::uuid6()->toString(),
                $value->getEmail(),
                $ssoUserData->getPasswordHash()
            ),
            $value->getPassword()
        )) {
            $this->logger->error('Wrong user password');
            $this->context->buildViolation('Wrong username or password.')
                ->addViolation();
        }
    }

    private function hasEmptyValues(LoginDtoInterface $dto): bool
    {
        return empty($dto->getEmail())
            || empty($dto->getPassword())
            || empty($dto->getIssuer())
            || empty($dto->getRelayState())
            || empty($dto->getRequestId());
    }
}
