<?php

defined('YII_DEBUG') or define('YII_DEBUG', filter_var(getenv('YII_DEBUG'), FILTER_VALIDATE_BOOL));
defined('YII_ENV') or define('YII_ENV', YII_DEBUG ? 'dev' : 'prod');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';

$config = require __DIR__ . '/../config/main.php';

(new yii\web\Application($config))->run();
