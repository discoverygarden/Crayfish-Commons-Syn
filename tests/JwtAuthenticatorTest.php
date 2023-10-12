<?php

namespace Islandora\Crayfish\Commons\Syn\Tests;

use Islandora\Crayfish\Commons\Syn\JwtAuthenticator;
use Islandora\Crayfish\Commons\Syn\JwtFactoryInterface;
use Islandora\Crayfish\Commons\Syn\SettingsParserInterface;
use Islandora\Crayfish\Commons\Tests\AbstractCrayfishCommonsTestCase;
use Namshi\JOSE\SimpleJWS;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class JwtAuthenticatorTest extends AbstractCrayfishCommonsTestCase
{

    private AuthenticatorInterface $simpleAuth;

    public function setUp(): void
    {
        parent::setUp();
        $this->simpleAuth = $this->getSimpleAuth();
    }

    private function getParser(array $site = null) : SettingsParserInterface
    {
        $prophet = $this->prophesize(SettingsParserInterface::class);
        $prophet->getStaticTokens()->willReturn([
          'testtoken' => ['user' => 'test', 'roles' => ['1', '2'], 'token' => 'testToken']
        ]);
        $prophet->getSites()->willReturn($site ?? [
          'https://foo.com' => ['algorithm' => '', 'key' => '' , 'url' => 'https://foo.com']
        ]);
        return $prophet->reveal();
    }

    private function getJwtFactory($jwt, $fail = false) : JwtFactoryInterface
    {
        $prophet = $this->prophesize(JwtFactoryInterface::class);
        if ($fail) {
            $prophet->load(Argument::any())->willThrow(\InvalidArgumentException::class);
        } else {
            $prophet->load(Argument::any())->willReturn($jwt);
        }
        return $prophet->reveal();
    }

    private function getSimpleAuth($bad_token = false) : AuthenticatorInterface
    {
        $jwt = $this->prophesize(SimpleJWS::class)->reveal();
        $parser = $this->getParser();
        $jwtFactory = $this->getJwtFactory($jwt, $bad_token);
        $auth = new JwtAuthenticator($parser, $jwtFactory);
        $auth->setLogger(new NullLogger());
        return $auth;
    }

    /**
     * Utility function to ensure the index does not exist in array or is null.
     *
     * @param array $array
     *   The credential array.
     * @param string $index
     *   The associative array index.
     *
     * @return boolean
     *   Whether the index does not exist or is null.
     */
    private function unsetOrNull(array $array, $index)
    {
        return (!array_key_exists($index, $array) || is_null($array[$index]));
    }

    public function testAuthenticationFailure()
    {
        $request = $this->prophesize(Request::class)->reveal();
        $exception = $this->prophesize(AuthenticationException::class)->reveal();

        $response = $this->simpleAuth->onAuthenticationFailure($request, $exception);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAuthenticationSuccess()
    {
        $request = $this->prophesize(Request::class)->reveal();
        $token = $this->prophesize(TokenInterface::class)->reveal();

        $response = $this->simpleAuth->onAuthenticationSuccess($request, $token, null);
        $this->assertNull($response);
    }

    public function testNoHeader()
    {
        $request = new Request();
        $this->assertFalse($this->simpleAuth->supports($request));
    }

    public function testHeaderNoBearer()
    {
        $request = new Request();
        $request->headers->set("Authorization", "foo");
        $this->assertFalse($this->simpleAuth->supports($request));
    }

    public function testHeaderBadToken()
    {
        $request = new Request();
        $request->headers->set("Authorization", "Bearer foo");
        $simple_auth = $this->getSimpleAuth(true);
        $this->assertTrue($simple_auth->supports($request));
        $this->expectException(UnauthorizedHttpException::class);
        $simple_auth->authenticate($request);
    }

    /**
     * Takes an array of JWT parts and tries to authenticate against it.
     *
     * @param $data
     *   The array of JWT parameters.
     * @param bool $expired
     *   Whether the JWT has expired or not.
     */
    public function headerTokenHelper($data, bool $expired = false) : bool
    {
        $parser = $this->getParser();
        $request = new Request();
        $request->headers->set("Authorization", "Bearer foo");
        $prophet = $this->prophesize(SimpleJWS::class);
        $prophet->getPayload()->willReturn($data);
        $prophet->isExpired()->willReturn($expired);
        $prophet->isValid(Argument::any(), Argument::any())->willReturn(true);
        $jwt = $prophet->reveal();
        $jwtFactory = $this->getJwtFactory($jwt);
        $auth = new JwtAuthenticator($parser, $jwtFactory);
        $auth->setLogger(new NullLogger());
        $this->assertTrue($auth->supports($request));
        return $auth->authenticate($request) instanceof Passport;
    }

    const DUMMY_DATA = [
      'webid' => 1,
      'iss' => 'https://foo.com',
      'sub' => 'charlie',
      'roles' => ['bartender', 'exterminator'],
      'iat' => 1,
      'exp' => 1,
    ];

    public function testHeaderTokenFieldsBase()
    {
        $data = static::DUMMY_DATA;
        $this->assertTrue($this->headerTokenHelper($data));
    }

    public function missingClaimProvider() : array
    {
        return [
        ['webid'],
        ['iss'],
        ['sub'],
        ['roles'],
        ['iat'],
        ['exp'],
        ];
    }

    /**
     * @dataProvider missingClaimProvider
     */
    public function testHeaderTokenFieldsMissingClaims($claim)
    {
        $data = static::DUMMY_DATA;
        unset($data[$claim]);
        $this->expectException(UnauthorizedHttpException::class);
        $this->headerTokenHelper($data);
    }

    public function testHeaderTokenExpired()
    {
        $data = static::DUMMY_DATA;
        $this->expectException(UnauthorizedHttpException::class);
        $this->headerTokenHelper($data, true);
    }

    public function jwtAuthHelper(array $data, SettingsParserInterface $parser, bool $valid = true) : void
    {
        $request = new Request();
        $request->headers->set("Authorization", "Bearer foo");

        $prophet = $this->prophesize(SimpleJWS::class);
        $prophet->getPayload()->willReturn($data);
        $prophet->isExpired()->willReturn(false);
        $prophet->isValid(Argument::any(), Argument::any())->willReturn($valid);
        $jwt = $prophet->reveal();
        $jwtFactory = $this->getJwtFactory($jwt);
        $auth = new JwtAuthenticator($parser, $jwtFactory);
        $auth->setLogger(new NullLogger());
        $this->assertTrue($auth->supports($request));
        $passport = $auth->authenticate($request);

        $this->assertEquals('charlie', $passport->getUser()->getUserIdentifier());
        $this->assertTrue(in_array('bartender', $passport->getAttribute('roles')));
        $this->assertTrue(in_array('exterminator', $passport->getAttribute('roles')));
    }

    public function testJwtAuthentication()
    {
        $parser = $this->getParser();
        $this->jwtAuthHelper(static::DUMMY_DATA, $parser);
    }

    public function testJwtAuthenticationInvalidJwt()
    {
        $parser = $this->getParser();
        $this->expectException(UnauthorizedHttpException::class);
        $this->jwtAuthHelper(static::DUMMY_DATA, $parser, false);
    }

    public function testJwtAuthenticationNoSite()
    {
        $parser = $this->getParser();
        $this->expectException(UnauthorizedHttpException::class);
        $this->jwtAuthHelper([
          'iss' => 'https://www.pattyspub.ca/',
        ] + static::DUMMY_DATA, $parser);
    }

    public function testJwtAuthenticationDefaultSite()
    {
        $site = [
            'default' => ['algorithm' => '', 'key' => '' , 'url' => 'default']
        ];
        $parser = $this->getParser($site);
        $this->jwtAuthHelper([
          'iss' => 'https://www.pattyspub.ca/',
        ] + static::DUMMY_DATA, $parser);
    }

    public function testStaticToken()
    {
        $auth = $this->getSimpleAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer testtoken');

        $auth->supports($request);
        $passport = $auth->authenticate($request);

        $this->assertEquals('test', $passport->getUser()->getUserIdentifier());
        $this->assertEquals(['1', '2'], $passport->getAttribute('roles'));
    }
}
