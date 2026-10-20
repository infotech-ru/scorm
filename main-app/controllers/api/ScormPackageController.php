<?php

namespace mainapp\controllers\api;

use common\models\ScormPackage;
use common\models\ScormSco;
use common\services\ScormPackageService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class ScormPackageController extends ApiController
{
    protected function verbs(): array
    {
        return ['upload' => ['POST'], 'delete' => ['DELETE']];
    }

    public function actionIndex(): array
    {
        return [
            'success' => true,
            'data' => ScormPackage::find()
                ->select(['id', 'title', 'version', 'status', 'created_at'])
                ->orderBy(['created_at' => SORT_DESC])
                ->asArray()
                ->all(),
        ];
    }

    public function actionView(int $id): array
    {
        $package = $this->findPackage($id);

        return [
            'success' => true,
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'identifier' => $package->identifier,
                'version' => $package->version,
                'status' => $package->status,
                'metadata' => $package->metadata,
                'scos' => array_map(fn(ScormSco $sco) => [
                    'id' => $sco->id,
                    'identifier' => $sco->identifier,
                    'title' => $sco->title,
                    'scormType' => $sco->scorm_type,
                    'parentId' => $sco->parent_id,
                ], $package->getOrderedScos()),
                'created_at' => $package->created_at,
            ],
        ];
    }

    public function actionUpload(): array
    {
        $file = UploadedFile::getInstanceByName('file') ?? throw new BadRequestHttpException('No file uploaded');

        try {
            $package = (new ScormPackageService())->processUpload($file, Yii::$app->params['learnerId']);
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        }

        return [
            'success' => true,
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'version' => $package->version,
                'status' => $package->status,
            ],
        ];
    }

    public function actionDelete(int $id): array
    {
        (new ScormPackageService())->deletePackage($this->findPackage($id));

        return ['success' => true];
    }

    private function findPackage(int $id): ScormPackage
    {
        return ScormPackage::findOne($id) ?? throw new NotFoundHttpException('Package not found');
    }
}
