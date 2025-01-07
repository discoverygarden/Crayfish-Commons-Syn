<?php

namespace Islandora\Crayfish\Commons\Syn;

use Symfony\Component\HttpFoundation\Request;

trait BearerTokenTrait {

    private const HEADER = 'Authorization';

    private const PREFIX = 'bearer ';

    private const REQUEST_ATTRIBUTE = 'crayfish-commons-syn-token';

    protected function getBearerToken(Request $request) : ?string {
        if (!$request->attributes->has(static::REQUEST_ATTRIBUTE)) {
            $request->attributes->set(static::REQUEST_ATTRIBUTE, $this->doGetBearerToken($request));
        }

        return $request->attributes->get(static::REQUEST_ATTRIBUTE);
    }

    private function doGetBearerToken(Request $request) : ?string {
        if (!($token = $request->headers->get(static::HEADER))) {
            return null;
        }

        // Chop off the leading "bearer " from the token
        return substr($token, strlen(static::PREFIX));
    }

}