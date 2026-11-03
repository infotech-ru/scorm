<?php

namespace console\controllers;

use common\models\ScormLaunchToken;
use yii\console\Controller;

class CallbackController extends Controller
{
    public function actionCleanup(): void
    {
        $deleted = ScormLaunchToken::deleteAll(['<', 'expires_at', date('Y-m-d H:i:s')]);
        $this->stdout('Deleted expired tokens: ' . $deleted . PHP_EOL);
    }
}
