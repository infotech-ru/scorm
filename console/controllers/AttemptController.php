<?php

namespace console\controllers;

use common\models\ScormAttempt;
use common\models\ScormLaunchToken;
use yii\console\Controller;

class AttemptController extends Controller
{
    public function actionReset(string $registration, int $package): void
    {
        $attempt = ScormAttempt::findOne([
            'registration_key' => $registration,
            'package_id' => $package,
            'is_preview' => 0,
            'finished_at' => null,
        ]);

        if (!$attempt) {
            $this->stderr('Незавершённая попытка не найдена');

            return;
        }

        $attempt->updateAttributes(['finished_at' => date('Y-m-d H:i:s')]);
        ScormLaunchToken::revokeForAttempt($attempt->id);
        $this->stdout('Попытка #' . $attempt->id . ' закрыта');
    }
}
