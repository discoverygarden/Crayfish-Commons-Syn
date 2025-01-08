<?php

namespace Islandora\Crayfish\Commons\Syn;

use Firebase\JWT\Key;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtFactory implements JwtFactoryInterface, LoggerAwareInterface
{

    use BearerTokenTrait;
    use LoggerAwareTrait;

    protected array $sites;

    public function __construct(
        protected SettingsParserInterface $settingsParser,
    ) {
        $this->sites = $this->settingsParser->getSites();
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $jwt) : JwtInterface
    {
        $site = $this->getSiteInfo($jwt);
        try {
            $payload = \Firebase\JWT\JWT::decode(
                $jwt,
                new Key(
                    $site['key'],
                    $site['algorithm'],
                ),
            );
            return new Jwt(
                $jwt,
                $payload,
                true,
                false,
            );
        } catch (\Exception $e) {
            throw new JwtException("JWT decoding failed.", previous: $e);
        }
    }

    public function loadFromRequest(Request $request) : JwtInterface
    {
        return $this->load($this->getBearerToken($request));
    }

    protected function getUnvalidatedTokenPayload(string $token) : object
    {
        $parts = explode('.', $token);
        if (($count = count($parts)) !== 3) {
            throw new \InvalidArgumentException("Expected 3 components in JWT but got $count");
        }

        $payload_json = \Firebase\JWT\JWT::urlsafeB64Decode($parts[1]);
        return \Firebase\JWT\JWT::jsonDecode($payload_json);
    }

    protected function getSiteInfo(string $token) : array
    {
        $issuer = '';
        try {
            $unvalidated_payload = $this->getUnvalidatedTokenPayload($token);
            if (($issuer = $unvalidated_payload?->iss) && ($site = ($this->sites[$issuer] ?? null))) {
                $this->logger->debug('Using site/issuer {issuer}', [
                  'issuer' => $issuer,
                ]);
                return $site;
            }
        } catch (\InvalidArgumentException) {
            $this->logger->debug('Malformed token received.');
        }

        if ($site = ($this->sites['default'] ?? null)) {
            $this->logger->debug('Using default site.');
            return $site;
        }

        throw new UnauthorizedHttpException(strtr('Unknown issuer ":issuer" and no default site defined.', [
            ':issuer' => $issuer,
        ]));
    }
}
