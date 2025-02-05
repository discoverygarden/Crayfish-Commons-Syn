<?php

namespace Islandora\Crayfish\Commons\Syn;

interface JwtInterface
{
    public function getRawToken() : string;

    public function getPayload() : object;

    public function isValid() : bool;

    public function isExpired() : bool;
}
