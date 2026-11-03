<?php

namespace scormplayer\controllers;

use common\helpers\Helper;
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
        $runtimeToken = $token ? new ScormLaunchService()->startRuntime($token) : null;

        if (!$runtimeToken) {
            throw new NotFoundHttpException('Ссылка запуска недействительна или устарела');
        }

        $attempt = $runtimeToken->attempt;
        $package = $attempt->package;
        $origin = $attempt->client?->origin;

        if (!Helper::isValidOrigin($origin)) {
            $runtimeToken->revoke();
            throw new NotFoundHttpException('Ссылка запуска недействительна или устарела');
        }

        $params = Yii::$app->params;
        $contentBase = ContentUrlSigner::fromParams($params, YII_DEBUG)->basePath($package->id, $package->extracted_path, time() + $params['runtimeTokenTtl'], $origin);

        Yii::$app->response->headers->set('Content-Security-Policy', 'frame-ancestors ' . $origin);

        return $this->render('launch', [
            'package' => $package,
            'config' => [
                'token' => $runtimeToken->token,
                'apiEndpoint' => '/api/v1/runtime',
                'contentOrigin' => $params['scormContentUrl'],
                'hostUrl' => $params['scormContentUrl'] . $contentBase . '.scorm-host.html?launch=' . rawurlencode($attempt->sco->getRelativeLaunchUrl()),
                'mainAppOrigin' => $origin,
                'version' => $package->version,
                'definitions' => ScormDataModel::definitions($package->version),
                'cmi' => new ScormRuntimeService()->initialData($attempt, $runtimeToken->isReview()),
            ],
        ]);
    }
}
