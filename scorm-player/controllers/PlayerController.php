<?php

namespace scormplayer\controllers;

use common\services\ContentUrlSigner;
use common\services\ScormDataModel;
use common\services\ScormLaunchService;
use common\services\ScormRuntimeService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class PlayerController extends Controller
{
    public $layout = false;

    public function actionLaunch(string $token = ''): string
    {
        $runtimeToken = $token === '' ? null : (new ScormLaunchService())->startRuntime($token);
        if ($runtimeToken === null) {
            throw new NotFoundHttpException('Invalid or expired launch link');
        }

        $attempt = $runtimeToken->attempt;
        $package = $attempt->package;
        $params = Yii::$app->params;

        $contentBase = ContentUrlSigner::fromParams($params, YII_DEBUG)
            ->basePath($package->id, time() + $params['runtimeTokenTtl']);

        return $this->render('launch', [
            'package' => $package,
            'config' => [
                'token' => $runtimeToken->token,
                'apiEndpoint' => '/api/v1/runtime',
                'contentOrigin' => $params['scormContentUrl'],
                'hostUrl' => $params['scormContentUrl'] . $contentBase . '.scorm-host.html?launch='
                    . rawurlencode($attempt->sco->getRelativeLaunchUrl()),
                'mainAppOrigin' => $params['mainAppUrl'],
                'version' => $package->version,
                'definitions' => ScormDataModel::definitions($package->version),
                'cmi' => (new ScormRuntimeService())->initialData($attempt, $runtimeToken->isReview()),
            ],
        ]);
    }
}
