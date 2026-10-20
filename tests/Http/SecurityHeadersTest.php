<?php

namespace tests\Http;

use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    private string $mainAppUrl;
    private string $playerUrl;
    private string $contentUrl;

    protected function setUp(): void
    {
        $this->mainAppUrl = rtrim(getenv('MAIN_APP_URL') ?: 'http://app.local:8090', '/');
        $this->playerUrl = rtrim(getenv('SCORM_PLAYER_URL') ?: 'http://scorm.local:8091', '/');
        $this->contentUrl = rtrim(getenv('SCORM_CONTENT_URL') ?: 'http://scorm.local:8092', '/');

        foreach ([$this->mainAppUrl, $this->playerUrl, $this->contentUrl] as $url) {
            if ($this->request('GET', $url . '/') === null) {
                $this->markTestSkipped("{$url} is not reachable");
            }
        }
    }

    public function testMainAppCannotBeFramed(): void
    {
        $headers = $this->request('GET', $this->mainAppUrl . '/')['headers'];

        $this->assertSame('DENY', $headers['x-frame-options'] ?? null);
        $this->assertStringContainsString("frame-ancestors 'none'", $headers['content-security-policy'] ?? '');
    }

    public function testPlayerCanOnlyBeFramedByMainAppAndFramesOnlyContent(): void
    {
        $csp = $this->request('GET', $this->playerUrl . '/')['headers']['content-security-policy'] ?? '';

        $this->assertStringContainsString("frame-ancestors {$this->mainAppUrl}", $csp);
        $this->assertStringContainsString("frame-src {$this->contentUrl}", $csp);
        $this->assertStringContainsString("script-src 'self';", $csp);
    }

    public function testContentServerSendsHeadersEvenForErrors(): void
    {
        $response = $this->request('GET', $this->contentUrl . '/c/1/1/00000000000000000000000000000000/index.html');

        $this->assertSame(403, $response['status']);
        $this->assertSame('nosniff', $response['headers']['x-content-type-options'] ?? null);
        $this->assertStringContainsString("frame-ancestors 'self' {$this->playerUrl} {$this->mainAppUrl}", $response['headers']['content-security-policy'] ?? '');
    }

    public function testContentServerRejectsUnsignedPaths(): void
    {
        $this->assertSame(404, $this->request('GET', $this->contentUrl . '/storage/scorm-content/1/imsmanifest.xml')['status']);
        $this->assertSame(404, $this->request('GET', $this->contentUrl . '/router.php')['status']);
    }

    public function testRuntimeApiRequiresBearerToken(): void
    {
        $response = $this->request('POST', $this->playerUrl . '/api/v1/runtime/commit', ['Content-Type: application/json'], '{"values":{}}');

        $this->assertSame(401, $response['status']);
    }

    public function testApisDoNotAllowCrossOriginAccess(): void
    {
        $headers = ['Origin: ' . $this->playerUrl, 'Access-Control-Request-Method: POST'];

        $launch = $this->request('OPTIONS', $this->mainAppUrl . '/api/v1/scorm-launch/create-token', $headers);
        $runtime = $this->request('OPTIONS', $this->playerUrl . '/api/v1/runtime/commit', ['Origin: ' . $this->contentUrl, 'Access-Control-Request-Method: POST']);

        $this->assertArrayNotHasKey('access-control-allow-origin', $launch['headers']);
        $this->assertArrayNotHasKey('access-control-allow-origin', $runtime['headers']);
    }

    private function request(string $method, string $url, array $headers = [], ?string $body = null): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $parsed = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsed[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsed];
    }
}
