<?php

namespace mainapp\controllers\api;

use common\enums\ApiErrorCode;
use common\models\ScormPackage;
use common\services\ScormLaunchService;
use mainapp\models\LaunchRequestForm;
use Yii;
use yii\web\NotFoundHttpException;

class ScormLaunchController extends ApiController
{
    protected function verbs(): array
    {
        return ['create-token' => ['POST']];
    }

    public function actionCreateToken(): array
    {
        $form = new LaunchRequestForm();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            return $this->error(422, ApiErrorCode::InvalidRequest, implode(' ', $form->getFirstErrors()));
        }

        $package = ScormPackage::findForClient(Yii::$app->user->identity, $form->package_id);

        if (!$package || !$package->isReady() || !$package->getDefaultSco()) {
            throw new NotFoundHttpException('Пакет не найден или не готов');
        }

        $token = new ScormLaunchService()->launchForClient(Yii::$app->user->identity, $package, $form->toLaunchRequest());

        return [
            'success' => true,
            'data' => [
                'launch_url' => $token->getLaunchUrl(),
                'mode' => $token->mode,
            ],
        ];
    }
}
