<?php

namespace Islandora\Crayfish\Commons\Syn;

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
    use LoggerAwareTrait;

    const HEADER = 'Authorization';
    const PREFIX = 'bearer ';

    const REQUIRED_CLAIMS = [
      'webid',
      'iss',
      'sub',
      'roles',
      'iat',
      'exp',
    ];

    protected array $sites;
    protected array $staticTokens;

    public function __construct(
        protected SettingsParserInterface $settingsParser,
        protected JwtFactoryInterface $jwtFactory,
    ) {
        $this->sites = $this->settingsParser->getSites();
        $this->staticTokens = $this->settingsParser->getStaticTokens();
    }

    /**
     * Extract credentials from the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request from which to extract credentials.
     *
     * @return array
     *   An array of credential info, including:
     *    - token: The raw token.
     *    - jwt: The parsed JWT.
     *    - name: The name of the user being auth'd.
     *    - roles: The roles as which the user is being auth'd.
     */
    protected function getCredentials(Request $request) : array
    {
        // Check headers
        $token = $request->headers->get(static::HEADER);

        // Chop off the leading "bearer " from the token
        $token = substr($token, strlen(static::PREFIX));
        $this->logger->debug("Token: $token");

        // Check if this is a static token
        if (isset($this->staticTokens[$token])) {
            $staticToken = $this->staticTokens[$token];
            return [
                'token' => $staticToken['token'],
                'jwt' => null,
                'name' => $staticToken['user'],
                'roles' => $staticToken['roles']
            ];
        }

        // Decode token
        try {
            $jwt = $this->jwtFactory->load($token);
        } catch (\InvalidArgumentException $exception) {
            throw new UnauthorizedHttpException('', 'Invalid token.', $exception);
        }

        // Check correct properties
        $payload = $jwt->getPayload();

        return [
            'token' => $token,
            'jwt' => $jwt,
            'name' => $payload['sub'] ?? null,
            'roles' => $payload['roles'] ?? null,
        ];
    }

    /**
     * Check the extracted credentials.
     *
     * @param array $credentials
     *   Array of credentials including:
     *   - token: The raw token.
     *   - jwt: The parsed JWT.
     *   - name: The name of the user being auth'd.
     *   - roles: The roles as which the user is being auth'd.
     */
    protected function checkCredentials(array $credentials) : void
    {
        // If this is a static token then no more verification needed
        if ($credentials['jwt'] === null) {
            $this->logger->info('Logged in with static token: {0}', [$credentials['name']]);
            return;
        }

        $jwt = $credentials['jwt'];
        $payload = $jwt->getPayload();

        // Check and warn of all missing claims before rejecting.
        $missing_claims = [];
        foreach (static::REQUIRED_CLAIMS as $claim) {
            if (!isset($payload[$claim])) {
                $missing_claims[] = $claim;
            }
        }

        if ($missing_claims) {
            // If any claim is missing
            throw new UnauthorizedHttpException('', strtr('Token missing claim(s): @claims', [
              '@claims' => implode(', ', $missing_claims),
            ]));
        }
        if ($jwt->isExpired()) {
            throw new UnauthorizedHttpException('', 'Token has expired.');
        }

        $url = $payload['iss'];
        if (isset($this->sites[$url])) {
            $site = $this->sites[$url];
        } elseif (isset($this->sites['default'])) {
            $site = $this->sites['default'];
        } else {
            throw new UnauthorizedHttpException('', 'Failed to identify token key.');
        }

        if (!$jwt->isValid($site['key'], $site['algorithm'])) {
            throw new UnauthorizedHttpException('', 'The token\'s signature does not appear to be valid.');
        }
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
        $credentials = $this->getCredentials($request);
        $this->checkCredentials($credentials);

        $passport = new SelfValidatingPassport(new UserBadge($credentials['name'], function ($name) use ($credentials) {
            return new InMemoryUser($name, null, $credentials['roles']);
        }));
        $passport->setAttribute('roles', $credentials['roles']);
        return $passport;
    }

}
