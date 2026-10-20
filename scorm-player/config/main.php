<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/params.php'
);

$config = [
    'id' => 'scorm-player',
    'name' => 'SCORM Player',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'scormplayer\controllers',
    'components' => [
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'class' => \yii\web\Response::class,
            'on beforeSend' => function ($event) {
                $params = Yii::$app->params;
                $headers = $event->sender->headers;

                $headers->set('Content-Security-Policy',
                    "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
                    . "img-src 'self' data:; connect-src 'self'; object-src 'none'; "
                    . "frame-src {$params['scormContentUrl']}; "
                    . "frame-ancestors {$params['mainAppUrl']}; base-uri 'none'; form-action 'none'"
                );
                $headers->set('X-Content-Type-Options', 'nosniff');
                $headers->set('Referrer-Policy', 'no-referrer');
            },
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                '' => 'site/index',
                'GET player/launch' => 'player/launch',
                'POST api/v1/runtime/commit' => 'api/runtime/commit',
                'POST api/v1/runtime/terminate' => 'api/runtime/terminate',
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logVars' => [],
                ],
            ],
        ],
    ],
    'params' => $params,
];

$common = require __DIR__ . '/../../common/config/main.php';
return \yii\helpers\ArrayHelper::merge($common, $config);
