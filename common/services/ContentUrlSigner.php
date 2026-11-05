<?php

namespace common\services;

use common\helpers\Helper;
use InvalidArgumentException;
use RuntimeException;

final class ContentUrlSigner
{
    private const string DEV_KEY = 'dev-only-content-signing-key';

    public function __construct(private readonly string $key)
    {
        if (!$key) {
            throw new RuntimeException('Ключ подписи контента пуст');
        }
    }

    public static function fromParams(array $params, bool $debug): self
    {
        $key = $params['contentSigningKey'] ?? '';
        if (!$key && !$debug) {
            throw new RuntimeException('Не задан CONTENT_SIGNING_KEY');
        }

        return new self($key ?: self::DEV_KEY);
    }

    public function basePath(int $packageId, string $prefix, int $expiresAt, string $ancestorOrigin): string
    {
        if (!Helper::isValidOrigin($ancestorOrigin)) {
            throw new InvalidArgumentException('Недопустимый origin родительской страницы');
        }

        if (!Helper::isValidPrefix($prefix)) {
            throw new InvalidArgumentException('Недопустимый префикс пакета');
        }

        return sprintf(
            '/c/%d/%s/%d/%s/%s/',
            $packageId,
            self::encode($prefix),
            $expiresAt,
            self::encode($ancestorOrigin),
            $this->signature($packageId, $prefix, $expiresAt, $ancestorOrigin)
        );
    }

    public function verify(int $packageId, string $prefix, int $expiresAt, string $ancestorOrigin, string $signature, ?int $now = null): bool
    {
        return $expiresAt >= ($now ?? time())
            && Helper::isValidOrigin($ancestorOrigin)
            && Helper::isValidPrefix($prefix)
            && hash_equals($this->signature($packageId, $prefix, $expiresAt, $ancestorOrigin), $signature);
    }

    public static function decodeOrigin(string $encoded): ?string
    {
        $origin = self::decode($encoded);

        return !Helper::isValidOrigin($origin) ? null : $origin;
    }

    public static function decodePrefix(string $encoded): ?string
    {
        $prefix = self::decode($encoded);

        return !Helper::isValidPrefix($prefix) ? null : $prefix;
    }

    private static function decode(string $encoded): ?string
    {
        if (!$encoded || !preg_match('/^[A-Za-z0-9_-]+\z/', $encoded)) {
            return null;
        }

        $value = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $value ?: null;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function signature(int $packageId, string $prefix, int $expiresAt, string $ancestorOrigin): string
    {
        return substr(hash_hmac('sha256', $packageId . '|' . $prefix . '|' . $expiresAt . '|' . $ancestorOrigin, $this->key), 0, 32);
    }
}
