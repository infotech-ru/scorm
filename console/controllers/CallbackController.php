<?php

namespace console\controllers;

use common\jobs\SendCallbackJob;
use common\models\ScormAttempt;
use common\models\ScormLaunchToken;
use common\services\CallbackNotifier;
use yii\console\Controller;

class CallbackController extends Controller
{
    public $package;
    public $registration;
    public $client;

    public function options($actionID): array
    {
        return $actionID === 'resend' ? ['package', 'registration', 'client'] : parent::options($actionID);
    }

    public function actionCleanup(): void
    {
        $deleted = ScormLaunchToken::deleteAll(['<', 'expires_at', date('Y-m-d H:i:s')]);
        $this->stdout('Deleted expired tokens: ' . $deleted . PHP_EOL);
    }

    public function actionResend(): void
    {
        $query = ScormAttempt::find()
            ->distinct()
            ->where(['is_preview' => 0])
            ->andWhere(['not', ['registration_key' => null]])
            ->andFilterWhere(['package_id' => $this->package, 'registration_key' => $this->registration, 'client_id' => $this->client]);

        $notifier = new CallbackNotifier();

        /** @var ScormAttempt $attempt */
        foreach ($query->each() as $attempt) {
            $scormAttempt = ScormAttempt::findLatestForRegistration($attempt->client_id, $attempt->package_id, $attempt->registration_key);
            $notifier->notify($scormAttempt, SendCallbackJob::EVENT_COMMIT);
        }
    }
}
