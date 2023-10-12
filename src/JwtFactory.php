<?php

namespace Islandora\Crayfish\Commons\Syn;

use Namshi\JOSE\JWS;
use Namshi\JOSE\SimpleJWS;

class JwtFactory implements JwtFactoryInterface
{

    /**
     * {@inheritDoc}
     */
    public function load(string $jwt) : JWS
    {
        return SimpleJWS::load($jwt);
    }
}
