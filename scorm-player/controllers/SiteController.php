<?php

namespace scormplayer\controllers;

use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public $layout = false;

    public function actions(): array
    {
        return [
            'error' => ErrorAction::class,
        ];
    }

    public function actionIndex(): Response
    {
        return $this->asJson(['status' => 'ok']);
    }
}
