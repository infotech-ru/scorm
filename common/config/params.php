<?php

return (static function (): array {
    $env = static fn(string $name, string $default = ''): string => getenv($name) ?: $default;
    $positive = static fn(string $name, int $default): int => max(0, (int)getenv($name)) ?: $default;
    $root = dirname(__DIR__, 2);

    return [
        'mainAppUrl' => rtrim($env('MAIN_APP_URL', 'http://app.local:8090'), '/'),
        'scormPlayerUrl' => rtrim($env('SCORM_PLAYER_URL', 'http://scorm.local:8091'), '/'),
        'scormContentUrl' => rtrim($env('SCORM_CONTENT_URL', 'http://scorm.local:8092'), '/'),
        'contentSigningKey' => $env('CONTENT_SIGNING_KEY'),
        'scormTempPath' => $root . '/storage/temp',
        'launchTokenTtl' => (int)$env('LAUNCH_TOKEN_TTL', '300'),
        'runtimeTokenTtl' => (int)$env('RUNTIME_TOKEN_TTL', '7200'),
        'maxPackageSize' => $positive('MAX_PACKAGE_SIZE', 500 * 1024 * 1024),
        'maxExtractedSize' => $positive('MAX_EXTRACTED_SIZE', 2048 * 1024 * 1024),
        'maxPackageFiles' => $positive('MAX_PACKAGE_FILES', 50000),
        'maxProcessingPerClient' => 2,
        'uploadRateLimit' => [10, 60],
        'learnerId' => 1,
        'demoUiEnabled' => filter_var($env('DEMO_UI_ENABLED', 0), FILTER_VALIDATE_BOOL),
    ];
})();
