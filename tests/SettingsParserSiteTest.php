<?php

namespace Islandora\Crayfish\Commons\Syn\Tests;

use Islandora\Crayfish\Commons\Syn\SettingsParser;
use Islandora\Crayfish\Commons\Tests\AbstractCrayfishCommonsTestCase;
use org\bovigo\vfs\vfsStream;

class SettingsParserSiteTest extends AbstractCrayfishCommonsTestCase
{

    public function testInvalidVersion()
    {
        $testXml =  <<<STRING
<config version='2'>
  <site url='http://test.com' algorithm='HS384' encoding='plain'>
    Its always sunny in Charlottetown
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        new SettingsParser($testXml);
    }

    public function hmacHelper($algorithm)
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='$algorithm' encoding='plain'>
    Its always sunny in Charlottetown
  </site>
</config>
STRING;
        $parser = new SettingsParser($testXml, $this->logger);
        $sites = $parser->getSites();
        $this->assertEquals(1, count($sites));
        $this->assertTrue(isset($sites['http://test.com']));
        $site = $sites['http://test.com'];
        $this->assertEquals($algorithm, $site['algorithm']);
        $this->assertEquals('Its always sunny in Charlottetown', $site['key']);
    }

    public function testOneSiteAllHmacInlineKey()
    {
        $this->hmacHelper('HS256');
        $this->hmacHelper('HS384');
        $this->hmacHelper('HS512');
    }

    public function testOneSiteHmacBase64()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='base64'>
    RG8geW91IHNlZSB0aGF0IGRvb3IgbWFya2VkIHBpcmF0ZT8=
  </site>
</config>
STRING;
        $parser = new SettingsParser($testXml, $this->logger);
        $sites = $parser->getSites();
        $this->assertEquals(1, count($sites));
        $this->assertTrue(isset($sites['http://test.com']));
        $site = $sites['http://test.com'];
        $this->assertEquals('HS256', $site['algorithm']);
        $this->assertEquals('Do you see that door marked pirate?', $site['key']);
    }

    public function testOneSiteHmacInvalidBase64()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='base64'>
    I am invalid!
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        (new SettingsParser($testXml))->getSites();
    }

    public function testOneSiteHmacInvalidEncoding()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='greenman'>
    RG8geW91IHNlZSB0aGF0IGRvb3IgbWFya2VkIHBpcmF0ZT8=
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        (new SettingsParser($testXml))->getSites();
    }

    public function testOneSiteHmacFileKey()
    {
        $dir = vfsStream::setup()->url();
        $file = $dir . DIRECTORY_SEPARATOR . "test";
        file_put_contents($file, 'lulz');

        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='plain' path="$file"/>
</config>
STRING;
        $parser = new SettingsParser($testXml, $this->logger);
        $sites = $parser->getSites();
        $this->assertEquals(1, count($sites));
        $this->assertTrue(isset($sites['http://test.com']));
        $site = $sites['http://test.com'];
        $this->assertEquals('lulz', $site['key']);
    }

    public function testOneSiteHmacInvalidFileKey()
    {
        $file = '/does/not/exist';

        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='plain' path="$file"/>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml);
        $sites = $parser->getSites();
    }

    public function testNoKeyOrPath()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='HS256' encoding='plain'/>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml, $this->logger);
        $parser->getSites();
    }

    public function testNoUrl()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site algorithm='HS256' encoding='plain'>
    foo
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml);
        $parser->getSites();
    }

    public function testNoUrlDefault()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site algorithm='HS256' encoding='plain' default="true">
    foo
  </site>
</config>
STRING;
        $parser = new SettingsParser($testXml);
        $sites = $parser->getSites();
        $this->assertEquals(1, count($sites));
    }

    public function testNoUrlNotDefault()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site algorithm='HS256' encoding='plain' default="false">
    foo
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml, $this->logger);
        $sites = $parser->getSites();
    }

    public function rsaHelper($algorithm)
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='$algorithm' encoding='PEM'>
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDEVO4MNlZG+iGYhoJd/cBpfMd9
YnKsntF+zhQs8lCbBabgY8kNoXVIEeOm4WPJ+W53gLDAIg6BNrZqxk9z1TLD6Dmz
t176OLYkNoTI9LNf6z4wuBenrlQ/H5UnYl6h5QoOdVpNAgEjkDcdTSOE1lqFLIle
KOT4nEF7MBGyOSP3KQIDAQAB
-----END PUBLIC KEY-----
  </site>
</config>
STRING;
        $parser = new SettingsParser($testXml, $this->logger);
        $sites = $parser->getSites();
        $this->assertEquals(1, count($sites));
        $this->assertTrue(isset($sites['http://test.com']));
        $site = $sites['http://test.com'];
        $this->assertEquals($algorithm, $site['algorithm']);
    }

    public function testOneSiteAllRsaInlineKey()
    {
        $this->rsaHelper('RS256');
        $this->rsaHelper('RS384');
        $this->rsaHelper('RS512');
    }

    public function testRsaNotRealKey()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='RS256' encoding='PEM'>
    fake key!
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml, $this->logger);
        $parser->getSites();
    }

    public function testRsaBadEncoding()
    {
        $testXml =  <<<STRING
<config version='1'>
  <site url='http://test.com' algorithm='RS256' encoding='DER'>
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDEVO4MNlZG+iGYhoJd/cBpfMd9
YnKsntF+zhQs8lCbBabgY8kNoXVIEeOm4WPJ+W53gLDAIg6BNrZqxk9z1TLD6Dmz
t176OLYkNoTI9LNf6z4wuBenrlQ/H5UnYl6h5QoOdVpNAgEjkDcdTSOE1lqFLIle
KOT4nEF7MBGyOSP3KQIDAQAB
-----END PUBLIC KEY-----
  </site>
</config>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml);
        $parser->getSites();
    }

    public function testEmptyString()
    {
        $testXml =  <<<STRING
STRING;
        $this->expectException(\InvalidArgumentException::class);
        $parser = new SettingsParser($testXml);
        $parser->getSites();
    }

    public function testIncorrectTags()
    {
        $testXml =  <<<STRING
<foo></foo>
STRING;
        $this->expectException(\InvalidArgumentException::class);
        new SettingsParser($testXml);
    }
}
