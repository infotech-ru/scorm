<?php

namespace tests\Unit;

use common\services\ContentUrlSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentUrlSignerTest extends TestCase
{
    public function testVerifiesOwnSignature(): void
    {
        $signer = new ContentUrlSigner('secret');
        $expires = time() + 60;

        [, , $package, $expiresAt, $signature] = explode('/', $signer->basePath(7, $expires));

        $this->assertSame('7', $package);
        $this->assertTrue($signer->verify(7, (int)$expiresAt, $signature));
    }

    public function testRejectsOtherPackageExpiredOrForeignKey(): void
    {
        $expires = time() + 60;
        $path = (new ContentUrlSigner('secret'))->basePath(7, $expires);
        $signature = explode('/', $path)[4];

        $this->assertFalse((new ContentUrlSigner('secret'))->verify(8, $expires, $signature));
        $this->assertFalse((new ContentUrlSigner('secret'))->verify(7, $expires, $signature, $expires + 1));
        $this->assertFalse((new ContentUrlSigner('other'))->verify(7, $expires, $signature));
    }

    public function testRequiresKeyOutsideDebug(): void
    {
        $this->expectException(RuntimeException::class);

        ContentUrlSigner::fromParams(['contentSigningKey' => ''], false);
    }
}
