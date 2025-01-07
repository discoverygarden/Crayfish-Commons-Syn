<?php

namespace Islandora\Crayfish\Commons\Syn;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class StaticTokenAuthenticator extends AbstractAuthenticator implements LoggerAwareInterface
{

    use BearerTokenTrait;
    use LoggerAwareTrait;

    protected array $staticTokens;

    /**
     * Constructor.
     */
    public function __construct(
        SettingsParserInterface $settingsParser,
    ) {
        $this->staticTokens = $settingsParser->getStaticTokens();
    }

    public function supports(Request $request) : ?bool
    {
        $token = $this->getBearerToken($request);
        return isset($this->staticTokens[$token]);
    }

    public function authenticate(Request $request) : Passport
    {
        $token = $this->getBearerToken($request);

        // Check if this is a static token
        $staticToken = $this->staticTokens[$token];
        $credentials = [
          'token' => $staticToken['token'],
          'name' => $staticToken['user'],
          'roles' => $staticToken['roles']
        ];

        $passport = new SelfValidatingPassport(new UserBadge($credentials['name'], function ($name) use ($credentials) {
            return new InMemoryUser($name, null, $credentials['roles']);
        }));
        $passport->setAttribute('roles', $credentials['roles']);
        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName) : ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : ?Response
    {
        return new JsonResponse(
            [
              'message' => $exception->getMessage(),
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }

}