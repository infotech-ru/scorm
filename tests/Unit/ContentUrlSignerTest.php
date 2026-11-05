<?php

namespace tests\Unit;

use common\services\ContentUrlSigner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentUrlSignerTest extends TestCase
{
    private const ORIGIN = 'https://dnm.example.com';
    private const PREFIX = '2026/10/28/scorm-a/';

    public function testVerifiesOwnSignature(): void
    {
        $signer = new ContentUrlSigner('secret');
        $expires = time() + 60;

        [, $marker, $package, $encodedPrefix, $expiresAt, $origin, $signature, $tail] = explode('/', $signer->basePath(7, self::PREFIX, $expires, self::ORIGIN));

        $this->assertSame('c', $marker);
        $this->assertSame('7', $package);
        $this->assertSame(self::PREFIX, ContentUrlSigner::decodePrefix($encodedPrefix));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encodedPrefix);
        $this->assertSame('', $tail);
        $this->assertSame(self::ORIGIN, ContentUrlSigner::decodeOrigin($origin));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $origin);
        $this->assertTrue($signer->verify(7, self::PREFIX, (int)$expiresAt, self::ORIGIN, $signature));
    }

    public function testRejectsOtherPackageExpiredOrForeignKey(): void
    {
        $expires = time() + 60;
        $signature = $this->signature(new ContentUrlSigner('secret'), 7, self::PREFIX, $expires, self::ORIGIN);

        $this->assertFalse((new ContentUrlSigner('secret'))->verify(8, self::PREFIX, $expires, self::ORIGIN, $signature));
        $this->assertFalse((new ContentUrlSigner('secret'))->verify(7, self::PREFIX, $expires, self::ORIGIN, $signature, $expires + 1));
        $this->assertFalse((new ContentUrlSigner('other'))->verify(7, self::PREFIX, $expires, self::ORIGIN, $signature));
    }

    public function testRequiresKeyOutsideDebug(): void
    {
        $this->expectException(RuntimeException::class);

        ContentUrlSigner::fromParams(['contentSigningKey' => ''], false);
    }

    private function signature(ContentUrlSigner $signer, int $packageId, string $prefix, int $expiresAt, string $origin): string
    {
        return explode('/', $signer->basePath($packageId, $prefix, $expiresAt, $origin))[6];
    }
}
