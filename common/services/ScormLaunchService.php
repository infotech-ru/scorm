<?php

namespace common\services;

use common\models\ScormAttempt;
use common\models\ScormLaunchToken;
use common\models\ScormSco;
use RuntimeException;
use Yii;

final class ScormLaunchService
{
    public function launch(int $userId, ScormSco $sco, bool $newAttempt = false): ScormLaunchToken
    {
        $latest = ScormAttempt::findLatest($userId, $sco->id);
        $mode = ScormLaunchToken::MODE_NORMAL;

        if ($latest === null) {
            $attempt = $this->createAttempt($userId, $sco, 1);
        } elseif ($newAttempt) {
            if (!$latest->isFinished()) {
                $latest->updateAttributes(['finished_at' => date('Y-m-d H:i:s')]);
            }
            $attempt = $this->createAttempt($userId, $sco, $latest->attempt_number + 1);
        } else {
            $attempt = $latest;
            if ($attempt->isFinished()) {
                $mode = ScormLaunchToken::MODE_REVIEW;
            }
        }

        ScormLaunchToken::revokeForAttempt($attempt->id);

        return ScormLaunchToken::issue(
            $attempt,
            ScormLaunchToken::PURPOSE_LAUNCH,
            $mode,
            Yii::$app->params['launchTokenTtl']
        );
    }

    public function startRuntime(string $launchToken): ?ScormLaunchToken
    {
        $token = ScormLaunchToken::consumeLaunchToken($launchToken);
        if ($token === null) {
            return null;
        }

        return ScormLaunchToken::issue(
            $token->attempt,
            ScormLaunchToken::PURPOSE_RUNTIME,
            $token->mode,
            Yii::$app->params['runtimeTokenTtl']
        );
    }

    private function createAttempt(int $userId, ScormSco $sco, int $number): ScormAttempt
    {
        $attempt = new ScormAttempt([
            'user_id' => $userId,
            'package_id' => $sco->package_id,
            'sco_id' => $sco->id,
            'attempt_number' => $number,
            'entry' => ScormAttempt::ENTRY_AB_INITIO,
        ]);

        if (!$attempt->save()) {
            throw new RuntimeException('Failed to create attempt: ' . json_encode($attempt->getFirstErrors()));
        }

        return $attempt;
    }
}
