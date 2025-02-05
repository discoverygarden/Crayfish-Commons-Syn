<?php

namespace Islandora\Crayfish\Commons\Syn\Tests;

use Islandora\Crayfish\Commons\Syn\JwtAuthenticator;
use Islandora\Crayfish\Commons\Syn\JwtException;
use Islandora\Crayfish\Commons\Syn\JwtFactory;
use Islandora\Crayfish\Commons\Syn\JwtFactoryInterface;
use Islandora\Crayfish\Commons\Syn\JwtInterface;
use Islandora\Crayfish\Commons\Syn\SettingsParserInterface;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class JwtAuthenticatorTest extends AbstractAuthenticatorTest
{

    public function setUp() : void
    {
        parent::setUp();
    }

    protected function getSimpleAuth($bad_token = false) : AuthenticatorInterface
    {
        $jwt_prophet = $this->prophesize(JwtInterface::class);
        $auth = new JwtAuthenticator($this->getJwtFactory($jwt_prophet->reveal(), $bad_token));
        $auth->setLogger(new NullLogger());
        return $auth;
    }

    protected function getJwtFactory(?JwtInterface $jwt = null, bool $fail = false) : JwtFactoryInterface
    {
        $prophet = $this->prophesize(JwtFactoryInterface::class);

        if ($fail) {
            $prophet->load(Argument::any())->willThrow(JwtException::class);
            $prophet->loadFromRequest(Argument::type(Request::class))->willThrow(JwtException::class);
        } else {
            $prophet->load(Argument::any())->willReturn($jwt);
            $prophet->loadFromRequest(Argument::type(Request::class))->willReturn($jwt);
        }

        return $prophet->reveal();
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
     * @param array $data
     *   The array of JWT parameters.
     * @param bool $expired
     *   Whether the JWT has expired or not.
     */
    public function headerTokenHelper(array $data, bool $expired = false) : bool
    {
        $request = new Request();
        $request->headers->set("Authorization", "Bearer foo");

        $jwt_prophet = $this->prophesize(JwtInterface::class);
        $jwt_prophet->getPayload()->willReturn((object) $data);
        $jwt_prophet->isValid()->willReturn(!$expired);
        $jwt_prophet->isExpired()->willReturn($expired);

        $factory_prophet = $this->prophesize(JwtFactoryInterface::class);
        $factory_prophet->loadFromRequest($request)->willReturn($jwt_prophet->reveal());

        $auth = new JwtAuthenticator($factory_prophet->reveal());
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

    protected function jwtHelper(
        array $data,
        bool $valid = true,
    ) : JwtInterface {
        $prophet = $this->prophesize(JwtInterface::class);
        $prophet->getPayload()->willReturn((object) $data);
        $prophet->isExpired()->willReturn(false);
        $prophet->isValid()->willReturn($valid);
        return $prophet->reveal();
    }

    public function jwtAuthHelper(
        array $data,
        bool $valid = true,
        ?JwtFactoryInterface $jwtFactory = null,
    ) : void {
        $request = new Request();
        $request->headers->set("Authorization", "Bearer foo");

        $jwtFactory ??= $this->getJwtFactory($this->jwtHelper($data, $valid));
        $auth = new JwtAuthenticator($jwtFactory);
        $auth->setLogger(new NullLogger());
        $this->assertTrue($auth->supports($request));
        $passport = $auth->authenticate($request);

        $this->assertEquals('charlie', $passport->getUser()->getUserIdentifier());
        $this->assertTrue(in_array('bartender', $passport->getAttribute('roles')));
        $this->assertTrue(in_array('exterminator', $passport->getAttribute('roles')));
    }

    public function testJwtAuthentication()
    {
        $this->jwtAuthHelper(static::DUMMY_DATA);
    }

    public function testJwtAuthenticationInvalidJwt()
    {
        $this->expectException(UnauthorizedHttpException::class);
        $this->jwtAuthHelper(static::DUMMY_DATA, false);
    }

    public function testJwtAuthenticationNoSite()
    {
        $this->expectException(UnauthorizedHttpException::class);
        $jwt_factory = new JwtFactory($this->getParser());
        $jwt_factory->setLogger(new NullLogger());
        $this->jwtAuthHelper(
            [
                'iss' => 'https://www.pattyspub.ca/',
            ] + static::DUMMY_DATA,
            jwtFactory: $jwt_factory,
        );
    }

    public function testJwtAuthenticationDefaultSite()
    {
        $this->markTestIncomplete('Refactor required; crossing too many boundaries.');
        $site = [
            'default' => ['algorithm' => '', 'key' => '' , 'url' => 'default']
        ];

        $jwt_factory = new JwtFactory($this->getParser($site));
        $jwt_factory->setLogger(new NullLogger());

        $this->jwtAuthHelper(
            [
                'iss' => 'https://www.pattyspub.ca/',
            ] + static::DUMMY_DATA,
            jwtFactory: $jwt_factory,
        );
    }
}
