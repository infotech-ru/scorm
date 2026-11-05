<?php

namespace mainapp\controllers;

use common\models\ScormAttempt;
use common\models\ScormPackage;
use common\models\ScormSco;
use common\services\ScormLaunchService;
use common\services\ScormPackageService;
use LogicException;
use RuntimeException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ScormController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!Yii::$app->params['demoUiEnabled']) {
            throw new NotFoundHttpException();
        }

        return parent::beforeAction($action);
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'packages' => ScormPackage::find()->where(['client_id' => null])->orderBy(['created_at' => SORT_DESC])->all(),
        ]);
    }

    public function actionUpload(): Response|string
    {
        if (Yii::$app->request->isPost) {
            $file = UploadedFile::getInstanceByName('scorm_file');

            if ($file === null) {
                Yii::$app->session->setFlash('error', 'Выберите файл для загрузки.');
            } else {
                try {
                    $package = new ScormPackageService()->processUpload($file, $this->learnerId());
                    Yii::$app->session->setFlash('success', 'SCORM package uploaded.');
                    return $this->redirect(['view', 'id' => $package->id]);
                } catch (RuntimeException $e) {
                    Yii::$app->session->setFlash('error', 'Не удалось загрузить пакет: ' . $e->getMessage());
                } catch (LogicException $e) {
                    Yii::error($e, __METHOD__);
                    Yii::$app->session->setFlash('error', 'Не удалось загрузить пакет: ошибка хранилища.');
                }
            }
        }

        return $this->render('upload');
    }

    public function actionView(int $id): string
    {
        $package = $this->findPackage($id);

        return $this->render('view', [
            'package' => $package,
            'tocRows' => $package->getTocRows($package->getOrderedScos()),
            'scoAttempts' => ScormAttempt::findLatestByScoForUser($this->learnerId(), $package->id),
            'attempts' => ScormAttempt::find()
                ->with('sco')
                ->where(['package_id' => $id])
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(10)
                ->all(),
        ]);
    }

    public function actionPlayer(int $id, ?int $sco = null, bool $new = false): string
    {
        $package = $this->findPackage($id);
        if (!$package->isReady()) {
            throw new NotFoundHttpException('Пакет не готов к запуску.');
        }

        $tocRows = $package->getTocRows($package->getOrderedScos());
        $scos = array_values(array_filter(array_column($tocRows, 'sco')));
        if (!$scos) {
            throw new NotFoundHttpException('В пакете нет SCO.');
        }

        $index = 0;
        if ($sco !== null) {
            $index = array_search($sco, array_map(fn(ScormSco $s) => $s->id, $scos), true);
            if ($index === false) {
                throw new NotFoundHttpException('SCO не найден в пакете.');
            }
        }
        $currentSco = $scos[$index];

        $token = (new ScormLaunchService())->launch($this->learnerId(), $currentSco, $new);

        return $this->render('player', [
            'package' => $package,
            'tocRows' => $tocRows,
            'scoCount' => count($scos),
            'sco' => $currentSco,
            'prevSco' => $scos[$index - 1] ?? null,
            'nextSco' => $scos[$index + 1] ?? null,
            'attempt' => $token->attempt,
            'review' => $token->isReview(),
            'scoAttempts' => ScormAttempt::findLatestByScoForUser($this->learnerId(), $package->id),
            'launchUrl' => $token->getLaunchUrl(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        try {
            (new ScormPackageService())->deletePackage($this->findPackage($id));
            Yii::$app->session->setFlash('success', 'Пакет удалён.');
        } catch (RuntimeException $e) {
            Yii::$app->session->setFlash('error', 'Не удалось удалить пакет: ' . $e->getMessage());
        } catch (LogicException $e) {
            Yii::error($e, __METHOD__);
            Yii::$app->session->setFlash('error', 'Не удалось удалить пакет: ошибка хранилища.');
        }

        return $this->redirect(['index']);
    }

    private function findPackage(int $id): ScormPackage
    {
        return ScormPackage::findOne(['id' => $id, 'client_id' => null]) ?? throw new NotFoundHttpException('Пакет не найден.');
    }

    private function learnerId(): int
    {
        return Yii::$app->params['learnerId'];
    }
}
