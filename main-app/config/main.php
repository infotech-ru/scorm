<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/params.php'
);

$cookieValidationKey = getenv('COOKIE_VALIDATION_KEY') ?: (YII_DEBUG ? 'dev-only-cookie-validation-key' : null);
if ($cookieValidationKey === null) {
    throw new \yii\base\InvalidConfigException('COOKIE_VALIDATION_KEY must be set');
}

$config = [
    'id' => 'main-app',
    'name' => 'SCORM LMS',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'mainapp\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey,
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'class' => \yii\web\Response::class,
            'on beforeSend' => function ($event) {
                $headers = $event->sender->headers;
                $headers->set('X-Frame-Options', 'DENY');
                $headers->set('Content-Security-Policy', "frame-ancestors 'none'");
                $headers->set('X-Content-Type-Options', 'nosniff');
                $headers->set('Referrer-Policy', 'same-origin');
            },
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/v1/scorm-packages/upload' => 'api/scorm-package/upload',
                'GET api/v1/scorm-packages' => 'api/scorm-package/index',
                'GET api/v1/scorm-packages/<id:\d+>' => 'api/scorm-package/view',
                'DELETE api/v1/scorm-packages/<id:\d+>' => 'api/scorm-package/delete',
                'POST api/v1/scorm-launch/create-token' => 'api/scorm-launch/create-token',
                'GET api/v1/scorm-results/attempt/<id:\d+>' => 'api/scorm-results/attempt',

                'scorm' => 'scorm/index',
                'scorm/upload' => 'scorm/upload',
                'scorm/view/<id:\d+>' => 'scorm/view',
                'scorm/player/<id:\d+>' => 'scorm/player',
                'POST scorm/delete/<id:\d+>' => 'scorm/delete',
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'except' => ['yii\web\HttpException:404'],
                ],
            ],
        ],
    ],
    'params' => $params,
];

$common = require __DIR__ . '/../../common/config/main.php';
return \yii\helpers\ArrayHelper::merge($common, $config);
