<?php

namespace tests\Unit;

use common\services\ZipExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ZipExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/zip-extractor-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    public function testExtractsRegularFiles(): void
    {
        $zip = $this->makeZip(['test.txt' => 'root', 'subdir/test2.txt' => 'nested']);

        (new ZipExtractor())->extractSafely($zip, $this->dir . '/out');

        $this->assertStringEqualsFile($this->dir . '/out/test.txt', 'root');
        $this->assertStringEqualsFile($this->dir . '/out/subdir/test2.txt', 'nested');
    }

    #[DataProvider('traversalPaths')]
    public function testRejectsPathTraversal(string $path): void
    {
        $zip = $this->makeZip([$path => 'payload']);

        try {
            (new ZipExtractor())->extractSafely($zip, $this->dir . '/out/inner');
            $this->fail("Path was not rejected: {$path}");
        } catch (RuntimeException) {
        }

        $this->assertFileDoesNotExist($this->dir . '/out/escaped.txt');
        $this->assertFileDoesNotExist($this->dir . '/escaped.txt');
    }

    public static function traversalPaths(): array
    {
        return [
            'parent' => ['../escaped.txt'],
            'nested parent' => ['subdir/../../escaped.txt'],
            'deep parent' => ['a/../../../escaped.txt'],
            'windows separators' => ['..\\escaped.txt'],
            'absolute' => ['/etc/escaped.txt'],
        ];
    }

    #[DataProvider('serverScripts')]
    public function testRejectsServerSideScripts(string $name): void
    {
        $zip = $this->makeZip(['imsmanifest.xml' => '<manifest/>', $name => '<?php echo 1;']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forbidden file type');

        (new ZipExtractor())->extractSafely($zip, $this->dir . '/out');
    }

    public static function serverScripts(): array
    {
        return [['shell.php'], ['nested/x.PHTML'], ['lib.phar'], ['run.cgi']];
    }

    public function testRejectsTooManyFiles(): void
    {
        $zip = $this->makeZip(['a.txt' => 'a', 'b.txt' => 'b', 'c.txt' => 'c']);

        $this->expectException(RuntimeException::class);

        (new ZipExtractor(maxFiles: 2))->extractSafely($zip, $this->dir . '/out');
    }

    public function testRejectsOversizedContent(): void
    {
        $zip = $this->makeZip(['big.txt' => str_repeat('x', 2048)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('too large');

        (new ZipExtractor(maxBytes: 1024))->extractSafely($zip, $this->dir . '/out');
    }

    public function testSkipsDotFiles(): void
    {
        $zip = $this->makeZip(['index.html' => 'ok', '.htaccess' => 'deny', 'sub/.user.ini' => 'x']);

        (new ZipExtractor())->extractSafely($zip, $this->dir . '/out');

        $this->assertFileExists($this->dir . '/out/index.html');
        $this->assertFileDoesNotExist($this->dir . '/out/.htaccess');
        $this->assertFileDoesNotExist($this->dir . '/out/sub/.user.ini');
    }

    private function makeZip(array $files): string
    {
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
