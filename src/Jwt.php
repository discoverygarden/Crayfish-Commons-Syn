<?php

namespace Islandora\Crayfish\Commons\Syn;

class Jwt implements JwtInterface
{

    public function __construct(
        protected string $rawToken,
        protected object $payload,
        protected bool $valid,
        protected bool $expired,
    ) {
    }

    public function getRawToken() : string
    {
        return $this->rawToken;
    }

    public function getPayload() : object
    {
        return $this->payload;
    }

    public function isValid() : bool
    {
        return $this->valid;
    }

    public function isExpired() : bool
    {
        return $this->expired;
    }

}
