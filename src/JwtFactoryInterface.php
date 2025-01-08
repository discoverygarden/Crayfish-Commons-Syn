<?php

namespace Islandora\Crayfish\Commons\Syn;

use Symfony\Component\HttpFoundation\Request;

interface JwtFactoryInterface
{
    /**
     * Load/parse a JWT/JWS.
     *
     * @param string $jwt
     *   The token to load/parse.
     *
     * @return \Islandora\Crayfish\Commons\Syn\JwtInterface
     *   The loaded/parsed token.
     */
    public function load(string $jwt) : JwtInterface;

    public function loadFromRequest(Request $request) : JwtInterface;
}
