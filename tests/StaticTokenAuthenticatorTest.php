<?php

namespace Islandora\Crayfish\Commons\Syn\Tests;

use Islandora\Crayfish\Commons\Syn\StaticTokenAuthenticator;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

class StaticTokenAuthenticatorTest extends AbstractAuthenticatorTest
{

    protected function getSimpleAuth($bad_token = false) : AuthenticatorInterface
    {
        $parser = $this->getParser();
        $auth = new StaticTokenAuthenticator($parser);
        $auth->setLogger(new NullLogger());
        return $auth;
    }

    public function testBadStaticToken()
    {
        $auth = $this->getSimpleAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer testbadtoken');

        $this->assertFalse($auth->supports($request));
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
