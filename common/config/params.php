<?php

return (static function (): array {
    $env = static fn(string $name, string $default = ''): string => getenv($name) ?: $default;
    $root = dirname(__DIR__, 2);

    return [
        'mainAppUrl' => rtrim($env('MAIN_APP_URL', 'http://app.local:8090'), '/'),
        'scormPlayerUrl' => rtrim($env('SCORM_PLAYER_URL', 'http://scorm.local:8091'), '/'),
        'scormContentUrl' => rtrim($env('SCORM_CONTENT_URL', 'http://scorm.local:8092'), '/'),
        'contentSigningKey' => $env('CONTENT_SIGNING_KEY'),
        'scormStoragePath' => $root . '/storage/scorm-packages',
        'scormContentPath' => $root . '/storage/scorm-content',
        'launchTokenTtl' => (int)$env('LAUNCH_TOKEN_TTL', '300'),
        'runtimeTokenTtl' => (int)$env('RUNTIME_TOKEN_TTL', '7200'),
        'maxPackageSize' => 100 * 1024 * 1024,
        'maxExtractedSize' => 500 * 1024 * 1024,
        'maxPackageFiles' => 10000,
        'learnerId' => 1,
    ];
})();
