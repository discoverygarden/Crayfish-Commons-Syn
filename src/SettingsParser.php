<?php

namespace Islandora\Crayfish\Commons\Syn;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Reads Syn XML Settings file
 *
 * @package Islandora\Crayfish\Commons\Syn
 */
class SettingsParser implements LoggerAwareInterface, SettingsParserInterface {

    use LoggerAwareTrait;

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
    public function __construct(string $xml) {
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

    protected function getKey(\SimpleXMLElement $site) : false|string {
        if (!empty($site['path'])) {
            if (!file_exists($site['path'])) {
                $this->logger->error('Key file does not exist.');
                return false;
            } else {
                $key = file_get_contents((string)$site['path']);
            }
        } else {
            $key = trim($site->__toString());
        }

        return $key;
    }

    protected function parseRsaSite(\SimpleXMLElement $site) : false|array {
        $key = $this->getKey($site);
        if ($key === false) {
            return false;
        }

        if (!isset($site['encoding']) || $site['encoding'] != 'PEM') {
            $this->logger->error("Incorrect encoding");
            return false;
        }

        $resource = openssl_pkey_get_public($key);
        if ($resource === false) {
            $this->logger->error("Key invalid");
            return false;
        }

        return [
            'algorithm' => (string)$site['algorithm'],
            'key' => $resource
        ];
    }

    protected function parseHmacSite(\SimpleXMLElement $site) : false|array {
        $key = $this->getKey($site);
        if ($key === false) {
            return false;
        }

        if (!isset($site['encoding']) || !in_array($site['encoding'], ['base64', 'plain'])) {
            $this->logger->error("Incorrect encoding");
            return false;
        }

        if ($site['encoding'] == 'base64') {
            $key = base64_decode($key, true);
            if ($key === false) {
                $this->logger->error('Base64 Decode Failed');
                return false;
            }
        }

        return [
            'algorithm' => (string)$site['algorithm'],
            'key' => $key
        ];
    }

    protected function parseSite(\SimpleXMLElement $site) : false|array {
        // Needs either key or path
        if (!empty($site['path']) == !empty(trim($site->__toString()))) {
            $this->logger->error("Only one of path or key must be defined.");
            return false;
        }

        // Check algorithm is correct and supported
        if (empty($site['algorithm'])) {
            $this->logger->error("Must define an algorithm.");
            return false;
        }

        $algorithm = $site['algorithm'];
        $rsa = in_array($algorithm, ['RS256', "RS384", "RS512"]);
        $hmac = in_array($algorithm, ['HS256', "HS384", "HS512"]);

        $default = isset($site['default']) && strtolower($site['default']) == 'true';
        if (empty($site['url']) && !$default) {
            $this->logger->error("Must define a URL or set to default.");
            return false;
        }

        if ($rsa) {
            $siteReturn = $this->parseRsaSite($site);
        } elseif ($hmac) {
            $siteReturn = $this->parseHmacSite($site);
        } else {
            $this->logger->error('Incorrect algorithm selection');
            return false;
        }

        if ($siteReturn === false) {
            return false;
        } else {
            $siteReturn['url'] = $default ? 'default' : (string)$site['url'];
            $siteReturn['default'] = $default;
            return $siteReturn;
        }
    }

    protected function parseToken(\SimpleXMLElement $token) : false|array {
        if (empty($token->__toString())) {
            $this->logger->error("Token cannot be empty.");
            return false;
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
    public function getSites() : array {
        $sites = [];
        $defaultSet = false;
        if (!$this->getValid()) {
            return $sites;
        }
        foreach ($this->xml->children() as $child) {
            if ($child->getName() == "site") {
                $site = $this->parseSite($child);
                if ($site !== false) {
                    if ($defaultSet && $site['default']) {
                        $this->logger->error('There can be only one default site.');
                    } else {
                        $sites[$site['url']] = $site;
                        $defaultSet |= $site['default'];
                    }
                }
            }
        }
        return $sites;
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticTokens() : array {
        $tokens = [];
        $sites = [];
        if (!$this->getValid()) {
            return $sites;
        }
        foreach ($this->xml->children() as $child) {
            if ($child->getName() == "token") {
                $token = $this->parseToken($child);
                if ($token !== false) {
                    $tokens[$token['token']] = $token;
                }
            }
        }
        return $tokens;
    }

    /**
     * Returns if the XML structure is valid.
     *
     * @return bool
     */
    public function getValid() : bool {
        return $this->valid;
    }
}
