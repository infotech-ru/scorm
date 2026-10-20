<?php

namespace common\services;

use RuntimeException;

final class ContentUrlSigner
{
    private const DEV_KEY = 'dev-only-content-signing-key';

    public function __construct(private readonly string $key)
    {
        if ($key === '') {
            throw new RuntimeException('Content signing key is empty');
        }
    }

    public static function fromParams(array $params, bool $debug): self
    {
        $key = (string)($params['contentSigningKey'] ?? '');
        if ($key === '' && !$debug) {
            throw new RuntimeException('CONTENT_SIGNING_KEY must be set');
        }

        return new self($key !== '' ? $key : self::DEV_KEY);
    }

    public function basePath(int $packageId, int $expiresAt): string
    {
        return sprintf('/c/%d/%d/%s/', $packageId, $expiresAt, $this->signature($packageId, $expiresAt));
    }

    public function verify(int $packageId, int $expiresAt, string $signature, ?int $now = null): bool
    {
        return $expiresAt >= ($now ?? time())
            && hash_equals($this->signature($packageId, $expiresAt), $signature);
    }

    private function signature(int $packageId, int $expiresAt): string
    {
        return substr(hash_hmac('sha256', $packageId . '|' . $expiresAt, $this->key), 0, 32);
    }
}
