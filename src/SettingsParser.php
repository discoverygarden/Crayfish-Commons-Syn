<?php

namespace Islandora\Crayfish\Commons\Syn;

/**
 * Reads Syn XML Settings file
 *
 * @package Islandora\Crayfish\Commons\Syn
 */
class SettingsParser implements SettingsParserInterface
{

    /**
     * Root XML element.
     *
     * @var \SimpleXMLElement
     */
    protected \SimpleXMLElement $xml;

    /**
     * @var bool
     */
    protected bool $valid;

    /**
     * Constructor.
     */
    public function __construct(string $xml)
    {
        $parsed = simplexml_load_string($xml);
        if (!$parsed) {
            throw new \InvalidArgumentException('The XML could not be parsed.');
        }
        $this->xml = $parsed;
        $this->valid = true;

        if ($this->xml->getName() != 'config') {
            throw new \InvalidArgumentException('The root element is not the expect "config" element in the XML.');
        }

        if ($this->xml['version'] != '1') {
            throw new \InvalidArgumentException('Failed to find the "version" attribute in the XML.');
        }
    }

    public static function create(string $path) : SettingsParserInterface
    {
        return new static(
            file_get_contents($path)
        );
    }

    protected function getKey(\SimpleXMLElement $site) : string
    {
        if (!empty($site['path'])) {
            if (!file_exists($site['path'])) {
                throw new \InvalidArgumentException("The key file ({$site['path']}) does not appear to exist.");
            } else {
                $key = file_get_contents((string)$site['path']);
            }
        } else {
            $key = trim($site->__toString());
        }

        return $key;
    }

    protected function parseRsaSite(\SimpleXMLElement $site) : array
    {
        $key = $this->getKey($site);
        if (!isset($site['encoding']) || $site['encoding'] != 'PEM') {
            throw new \InvalidArgumentException('Incorrect encoding.');
        }

        $resource = openssl_pkey_get_public($key);
        if ($resource === false) {
            throw new \InvalidArgumentException('Invalid key.');
        }

        return [
            'algorithm' => (string)$site['algorithm'],
            'key' => $resource
        ];
    }

    protected function parseHmacSite(\SimpleXMLElement $site) : array
    {
        $key = $this->getKey($site);

        if (!isset($site['encoding']) || !in_array($site['encoding'], ['base64', 'plain'])) {
            throw new \InvalidArgumentException('Incorrect encoding.');
        }

        if ($site['encoding'] == 'base64') {
            $key = base64_decode($key, true);
            if ($key === false) {
                throw new \InvalidArgumentException('Base64 decode failed; invalid base64 content?');
            }
        }

        return [
            'algorithm' => (string)$site['algorithm'],
            'key' => $key
        ];
    }

    protected function parseSite(\SimpleXMLElement $site) : array
    {
        // Needs either key or path
        if (!empty($site['path']) == !empty(trim($site->__toString()))) {
            throw new \InvalidArgumentException('The "path" and "key" attributes are mutually-exclusive.');
        }

        // Check algorithm is correct and supported
        if (empty($site['algorithm'])) {
            throw new \InvalidArgumentException('No defined algorithm.');
        }

        $algorithm = $site['algorithm'];
        $rsa = in_array($algorithm, ['RS256', "RS384", "RS512"]);
        $hmac = in_array($algorithm, ['HS256', "HS384", "HS512"]);
        if ($rsa) {
            $siteReturn = $this->parseRsaSite($site);
        } elseif ($hmac) {
            $siteReturn = $this->parseHmacSite($site);
        } else {
            throw new \InvalidArgumentException('Invalid algorithm selection.');
        }

        $default = isset($site['default']) && strtolower($site['default']) == 'true';
        if (empty($site['url']) && !$default) {
            throw new \InvalidArgumentException('No URL defined and not defined as "default".');
        }

        $siteReturn['url'] = $default ? 'default' : (string)$site['url'];
        $siteReturn['default'] = $default;
        return $siteReturn;
    }

    protected function parseToken(\SimpleXMLElement $token) : array
    {
        if (empty($token->__toString())) {
            throw new \InvalidArgumentException('Token cannot be empty.');
        }

        $tokenString = trim($token->__toString());

        if (!isset($token['user'])) {
            $user = 'islandoraAdmin';
        } else {
            $user = (string)$token['user'];
        }

        if (!isset($token['roles'])) {
            $roles = [];
        } else {
            $roles = explode(',', $token['roles']);
        }

        return [
            'roles' => $roles,
            'token' => $tokenString,
            'user' => $user
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getSites() : array
    {
        $sites = [];
        $defaultSet = false;
        if (!$this->getValid()) {
            return $sites;
        }
        foreach ($this->xml->children() as $child) {
            if ($child->getName() == "site") {
                $site = $this->parseSite($child);
                if ($defaultSet && $site['default']) {
                    throw new \InvalidArgumentException(
                        strtr('There can be only one "default" site. Duplicate found at !xpath', [
                          '!xpath' => dom_import_simplexml($child)->getNodePath(),
                        ])
                    );
                } else {
                    $sites[$site['url']] = $site;
                    $defaultSet = $defaultSet || $site['default'];
                }
            }
        }
        return $sites;
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticTokens() : array
    {
        $tokens = [];
        $sites = [];
        if (!$this->getValid()) {
            return $sites;
        }
        foreach ($this->xml->children() as $child) {
            if ($child->getName() == "token") {
                $token = $this->parseToken($child);
                $tokens[$token['token']] = $token;
            }
        }
        return $tokens;
    }

    /**
     * Returns if the XML structure is valid.
     *
     * @return bool
     */
    public function getValid() : bool
    {
        return $this->valid;
    }
}
