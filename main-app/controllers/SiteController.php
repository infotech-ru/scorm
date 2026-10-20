<?php

namespace mainapp\controllers;

use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public function actions(): array
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
