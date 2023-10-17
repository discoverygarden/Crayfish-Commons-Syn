<?php

namespace Islandora\Crayfish\Commons\Syn;

interface SettingsParserInterface
{

    /**
     * Get an array of sites from the configuration file.
     *
     * @return array[]
     *   Each site is keyed with its url. Each sites array contains:
     *   - algorithm
     *   - key
     *   - url
     *   - default
     */
    public function getSites() : array;

    /**
     * Get an array of static tokens from the configuration file.
     *
     * @return array[]
     *   Each tokens entry is keyed with its token value. Each token array contains:
     *   - token
     *   - user
     *   - roles
     */
    public function getStaticTokens() : array;
}
