<?php

use common\components\SelectelStorageS3;
use common\components\SentryTarget;
use yii\caching\FileCache;
use yii\mutex\MysqlMutex;
use yii\queue\db\Queue;
use yii\queue\LogBehavior;

return (static function (): array {
    $env = static fn(string $name, string $default): string => getenv($name) ?: $default;

    $config = [
        'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
        'timeZone' => 'UTC',
        'components' => [
            'storage' => [
                'class' => SelectelStorageS3::class,
                'accessKey' => $env('S3_ACCESS_KEY', ''),
                'secretKey' => $env('S3_SECRET_KEY', ''),
                'container' => $env('S3_CONTAINER', 'dsf-private'),
                'region' => $env('S3_REGION', 'ru-1'),
            ],
            'cache' => [
                'class' => FileCache::class,
            ],
            'log' => [
                'targets' => [
                    'sentry' => [
                        'class' => SentryTarget::class,
                        'dsn' => $env('SENTRY_DSN', ''),
                        'enabled' => $env('SENTRY_DSN', '') !== '',
                        'levels' => ['error', 'warning'],
                        'logVars' => [],
                        'except' => ['yii\web\HttpException:4*'],
                    ],
                ],
            ],
            'queue' => [
                'class' => Queue::class,
                'as log' => LogBehavior::class,
                'tableName' => 'scorm_queue',
                'mutex' => MysqlMutex::class,
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
