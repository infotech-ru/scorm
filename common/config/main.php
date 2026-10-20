<?php

return (static function (): array {
    $env = static fn(string $name, string $default): string => getenv($name) ?: $default;

    $config = [
        'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
        'components' => [
            'cache' => [
                'class' => \yii\caching\FileCache::class,
            ],
            'db' => [
                'class' => \yii\db\Connection::class,
                'dsn' => sprintf(
                    'mysql:host=%s;port=%s;dbname=%s',
                    $env('DB_HOST', '127.0.0.1'),
                    $env('DB_PORT', '3306'),
                    $env('DB_NAME', 'scorm_lms')
                ),
                'username' => $env('DB_USER', 'root'),
                'password' => $env('DB_PASSWORD', '12345'),
                'charset' => 'utf8mb4',
                'tablePrefix' => '',
                'enableSchemaCache' => false,
            ],
        ],
    ];

    $local = __DIR__ . '/main-local.php';

    return is_file($local)
        ? \yii\helpers\ArrayHelper::merge($config, require $local)
        : $config;
})();
