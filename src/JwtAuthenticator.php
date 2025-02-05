<?php

namespace Islandora\Crayfish\Commons\Syn;

use Firebase\JWT\Key;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator implements LoggerAwareInterface
{
    use BearerTokenTrait;
    use LoggerAwareTrait;

    const REQUIRED_CLAIMS = [
      'webid',
      'iss',
      'sub',
      'roles',
      'iat',
      'exp',
    ];

    /**
     * Associative array mapping site URLs to related properties.
     *
     * @var array[]
     */
    protected array $sites;

    /**
     * Constructor.
     */
    public function __construct(
        protected JwtFactoryInterface $jwtFactory,
    ) {
    }

    protected function getCredentials(Request $request) : array
    {
        $token = $this->jwtFactory->loadFromRequest($request);
        $payload = $token->getPayload();

        $missing_claims = [];
        foreach (static::REQUIRED_CLAIMS as $required_claim) {
            if (!isset($payload->{$required_claim})) {
                $missing_claims[] = $required_claim;
            }
        }

        if ($missing_claims) {
            // If any claim is missing
            throw new UnauthorizedHttpException('', strtr('Token missing claim(s): @claims', [
              '@claims' => implode(', ', $missing_claims),
            ]));
        }

        if (!$token->isValid()) {
            throw new UnauthorizedHttpException('', 'JWT is not valid.');
        }

        if ($token->isExpired()) {
            throw new UnauthorizedHttpException('', 'JWT is expired.');
        }

        return [
            'token' => $token,
            'name' => $payload->sub ?? null,
            'roles' => $payload->roles ?? null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName) : ?Response
    {
        // on success, let the request continue
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : ?Response
    {
        $data = array(
            'message' => $exception->getMessageKey(),
        );
        return new JsonResponse($data, 403);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request) : ?bool
    {
        // Check headers
        $token = $request->headers->get(static::HEADER);
        if (!$token) {
            $this->logger->info('Token missing');
            return false;
        }
        if (!str_starts_with(strtolower($token), static::PREFIX)) {
            $this->logger->info('Token malformed');
            return false;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request) : Passport
    {
        try {
            $credentials = $this->getCredentials($request);
            $passport = new SelfValidatingPassport(
                new UserBadge($credentials['name'], function ($name) use ($credentials) {
                    return new InMemoryUser($name, null, $credentials['roles']);
                }),
            );
            $passport->setAttribute('roles', $credentials['roles']);
            return $passport;
        } catch (\InvalidArgumentException|JwtException $e) {
            throw new UnauthorizedHttpException('', previous: $e);
        }
    }
}
