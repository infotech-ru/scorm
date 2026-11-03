<?php

namespace mainapp\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
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
        if (!Yii::$app->params['demoUiEnabled']) {
            throw new NotFoundHttpException();
        }

        return $this->render('index');
    }
}
