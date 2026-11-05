<?php

namespace common\helpers;

class Helper
{
    public static function isValidOrigin(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        $pattern = '~^https?://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::(\d{1,5}))?\z~i';

        if (strlen($origin) > 255 || !preg_match($pattern, $origin, $matches)) {
            return false;
        }

        $port = $matches[1] ?? '';

        return $port === '' || ($port >= 1 && $port <= 65535);
    }

    public static function isValidPrefix(?string $prefix): bool
    {
        if (!$prefix) {
            return false;
        }

        $pattern = '#^\d{4}/\d{2}/\d{2}/scorm-[A-Za-z0-9_-]+/\z#';

        return preg_match($pattern, $prefix) === 1;
    }

    public static function generatePrefix(): string
    {
        return date('Y/m/d') . '/scorm-' . uniqid() . '/';
    }

    public static function objectKeyPrefix(string $prefix, string $relativePath): ?string
    {
        if (!self::isValidPrefix($prefix)
            || $relativePath === ''
            || str_contains($relativePath, '\\')
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, '//')
            || str_ends_with($relativePath, '/')
            || preg_match('/[\x00-\x1f\x7f]/', $relativePath) === 1
        ) {
            return null;
        }

        if (array_any(explode('/', $relativePath), fn($segment) => $segment === '.' || $segment === '..')) {
            return null;
        }

        return $prefix . $relativePath;
    }
}
