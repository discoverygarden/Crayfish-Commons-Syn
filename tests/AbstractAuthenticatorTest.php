<?php

namespace Islandora\Crayfish\Commons\Syn\Tests;

use Islandora\Crayfish\Commons\Syn\SettingsParserInterface;
use Islandora\Crayfish\Commons\Tests\AbstractCrayfishCommonsTestCase;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

abstract class AbstractAuthenticatorTest extends AbstractCrayfishCommonsTestCase
{

    protected AuthenticatorInterface $simpleAuth;

    public function setUp() : void
    {
        parent::setUp();
        $this->simpleAuth = $this->getSimpleAuth();
    }

    abstract protected function getSimpleAuth() : AuthenticatorInterface;

    protected function getParser(?array $site = null) : SettingsParserInterface
    {
        $prophet = $this->prophesize(SettingsParserInterface::class);
        $prophet->getStaticTokens()->willReturn([
          'testtoken' => ['user' => 'test', 'roles' => ['1', '2'], 'token' => 'testToken'],
        ]);
        $prophet->getSites()->willReturn($site ?? [
          'https://foo.com' => ['algorithm' => '', 'key' => '', 'url' => 'https://foo.com'],
        ]);
        return $prophet->reveal();
    }
}
