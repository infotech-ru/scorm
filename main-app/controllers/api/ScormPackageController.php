<?php

namespace mainapp\controllers\api;

use common\enums\ApiErrorCode;
use common\exceptions\ScormPackageException;
use common\models\ScormPackage;
use common\models\ScormSco;
use common\services\ScormPackageService;
use mainapp\models\PackageCreateForm;
use Throwable;
use Yii;
use yii\web\NotFoundHttpException;

class ScormPackageController extends ApiController
{
    private const int PROCESSING_RETRY_AFTER = 10;

    protected function verbs(): array
    {
        return ['create' => ['POST'], 'delete' => ['DELETE']];
    }

    public function actionIndex(): array
    {
        return [
            'success' => true,
            'data' => ScormPackage::findAllForClient(Yii::$app->user->identity),
        ];
    }

    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     */
    public function actionCreate(): array
    {
        $form = new PackageCreateForm();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            return $this->error(422, ApiErrorCode::InvalidRequest, implode(' ', $form->getFirstErrors()));
        }

        $client = Yii::$app->user->identity;
        $retryAfter = $client->consumeUploadSlot();

        if ($retryAfter > 0) {
            return $this->error(429, ApiErrorCode::RateLimited, 'Слишком много загрузок пакетов', $retryAfter);
        }

        if ($client->hasTooManyProcessing()) {
            return $this->error(429, ApiErrorCode::TooManyProcessing, 'Слишком много пакетов в обработке', self::PROCESSING_RETRY_AFTER);
        }

        try {
            $package = new ScormPackageService()->processRemote($client, $form->sourceUrl, $form->fileName);
        } catch (ScormPackageException $e) {
            return $this->error(422, $e->getErrorCode(), $e->getMessage());
        }

        return [
            'success' => true,
            'data' => [
                'id' => $package->id,
                'version' => $package->version,
                'launch_file' => $package->launch_url,
                'size' => $package->size,
                'file_name' => $package->original_filename,
            ],
        ];
    }

    /**
     * @throws Throwable
     */
    public function actionDelete(int $id): array
    {
        new ScormPackageService()->deletePackage($this->findPackage($id));

        return ['success' => true];
    }

    /**
     * @throws Throwable
     */
    private function findPackage(int $id): ScormPackage
    {
        return ScormPackage::findForClient(Yii::$app->user->identity, $id) ?? throw new NotFoundHttpException('Пакет не найден');
    }
}
