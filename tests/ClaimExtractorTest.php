<?php
namespace DalPraS\OpenId\Server\Test;

use DalPraS\OpenId\Server\ClaimExtractor;
use DalPraS\OpenId\Server\Entities\ClaimSetEntity;
use PHPUnit\Framework\TestCase;
use DalPraS\OpenId\Server\Exception\InvalidArgumentException;

class ClaimExtractorTest extends TestCase
{
    public function testDefaultClaimSetsExist()
    {
        $extractor = new ClaimExtractor();
        $this->assertTrue($extractor->hasClaimSet('profile'));
        $this->assertTrue($extractor->hasClaimSet('email'));
        $this->assertTrue($extractor->hasClaimSet('address'));
        $this->assertTrue($extractor->hasClaimSet('phone'));
    }

    public function testCanAddCustomClaimSet()
    {
        $claims = new ClaimSetEntity('custom', ['custom_claim']);
        $extractor = new ClaimExtractor([$claims]);
        $this->assertTrue($extractor->hasClaimSet('custom'));

        $result = $extractor->extract(['custom'], ['custom_claim' => 'test']);
        $this->assertEquals($result['custom_claim'], 'test');
    }

    public function testCanNotOverrideDefaultScope()
    {
        $this->expectException(InvalidArgumentException::class);
        $claims = new ClaimSetEntity('profile', ['custom_claim']);
        $extractor = new ClaimExtractor([$claims]);
    }

    public function testCanGetClaimSet()
    {
        $extractor = new ClaimExtractor();
        $claimset = $extractor->getClaimSet('profile');
        $this->assertEquals($claimset->getScope(), 'profile');
        $claimset = $extractor->getClaimSet('unknown');
        $this->assertNull($claimset);
    }

    public function testExtract()
    {
        $extractor = new ClaimExtractor();
        // no result
        $result = $extractor->extract(['custom'], ['custom_claim' => 'test']);
        $this->assertEmpty($result);

        // result
        $result = $extractor->extract(['profile'], ['name' => 'Pluto']);
        $this->assertEquals($result['name'], 'Pluto');

        // no result
        $result = $extractor->extract(['profile'], ['invalid' => 'Pluto']);
        $this->assertEmpty($result);
    }
}
