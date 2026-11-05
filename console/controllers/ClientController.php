<?php

namespace console\controllers;

use common\models\ScormClient;
use InvalidArgumentException;
use yii\console\Controller;
use yii\helpers\Console;

class ClientController extends Controller
{
    public function actionCreate(string $name, string $origin, string $callbackUrl): void
    {
        try {
            $result = ScormClient::create($name, rtrim($origin, '/'), $callbackUrl);
        } catch (InvalidArgumentException $e) {
            $this->stderr($e->getMessage());

            return;
        }

        $this->stdout('Client ID: ' . $result['client']->id . PHP_EOL);
        $this->stdout('API key: ' . $result['apiKey'] . PHP_EOL);
        $this->stdout('Callback secret: ' . $result['callbackSecret'] . PHP_EOL);
    }
}
