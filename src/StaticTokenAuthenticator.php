<?php

namespace Islandora\Crayfish\Commons\Syn;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class StaticTokenAuthenticator extends AbstractAuthenticator
{

    const HEADER = 'Authorization';
    const PREFIX = 'bearer ';

    public function __construct(
        protected SettingsParserInterface $settingsParser,
    ) {
    }

  /**
   * @inheritDoc
   */
    public function supports(Request $request) : ?bool
    {
        if (!$request->headers->has(static::HEADER)) {
            return false;
        }

        $token = $request->headers->get(static::HEADER);
        if (!strpos($token, static::PREFIX) === 0) {
            return false;
        }


        $this->hasTokenMatch(static::getToken($request));
    }

  /**
   * @inheritDoc
   */
    public function authenticate(Request $request) : Passport
    {
        $info = $this->getTokenMatch($request->headers->get(static::HEADER));

        $passport = new SelfValidatingPassport(
            new UserBadge($info['user']),
        );

        $passport->setAttribute('roles', $info['roles']);

        return $passport;
    }

    protected static function getToken(Request $request) : string
    {
      // Chop off the prefix.
        return substr($request->headers->get(static::HEADER), strlen(static::PREFIX));
    }

    protected function hasTokenMatch(string $token) : bool
    {
        return (bool) $this->getTokenMatch($token);
    }

    protected function getTokenMatch(string $token) : array|false
    {
        return $this->settingsParser->getStaticTokens()[$token] ?? false;
    }

  /**
   * {@inheritDoc}
   */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName) : ?Response
    {
        return null;
    }

  /**
   * {@inheritDoc}
   */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : ?Response
    {
        $data = [
        'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
