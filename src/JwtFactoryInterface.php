<?php

namespace Islandora\Crayfish\Commons\Syn;

use Namshi\JOSE\JWS;

interface JwtFactoryInterface
{
    /**
     * Load/parse a JWT/JWS.
     *
     * @param string $jwt
     *   The token to load/parse.
     *
     * @return \Namshi\JOSE\JWS
     *   The loaded/parsed token.
     */
    public function load(string $jwt) : JWS;

}
