<?php

namespace mainapp\controllers\api;

use common\models\ScormPackage;
use common\models\ScormSco;
use common\services\ScormLaunchService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ScormLaunchController extends ApiController
{
    protected function verbs(): array
    {
        return ['create-token' => ['POST']];
    }

    public function actionCreateToken(): array
    {
        $request = Yii::$app->request;
        $sco = $this->findSco((int)$request->post('package_id'), (int)$request->post('sco_id'));
        $newAttempt = filter_var($request->post('new_attempt', false), FILTER_VALIDATE_BOOL);

        $token = (new ScormLaunchService())->launch(Yii::$app->params['learnerId'], $sco, $newAttempt);

        return [
            'success' => true,
            'data' => [
                'launch_url' => $token->getLaunchUrl(),
                'attempt_id' => $token->attempt_id,
                'attempt_number' => $token->attempt->attempt_number,
                'mode' => $token->mode,
                'expires_at' => $token->expires_at,
            ],
        ];
    }

    private function findSco(int $packageId, int $scoId): ScormSco
    {
        if (!$packageId && !$scoId) {
            throw new BadRequestHttpException('sco_id or package_id is required');
        }

        $sco = $scoId ? ScormSco::findOne($scoId) : ScormPackage::findOne($packageId)?->getDefaultSco();
        if ($sco === null || ($packageId && $sco->package_id !== $packageId) || !$sco->package->isReady()) {
            throw new NotFoundHttpException('SCO not found or package is not ready');
        }

        return $sco;
    }
}
