<?php

namespace common\services;

use common\dto\LaunchRequestDTO;
use common\models\ScormAttempt;
use common\models\ScormClient;
use common\models\ScormLaunchToken;
use common\models\ScormPackage;
use common\models\ScormSco;
use InvalidArgumentException;
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

    public function launchForClient(ScormClient $client, ScormPackage $package, LaunchRequestDTO $request): ScormLaunchToken
    {
        if ($package->client_id != $client->id || !$package->isReady()) {
            throw new InvalidArgumentException('Пакет недоступен для запуска');
        }
        if (!in_array($request->mode, ScormLaunchToken::MODES)) {
            throw new InvalidArgumentException('Неизвестный режим запуска');
        }
        if ($request->mode !== ScormLaunchToken::MODE_PREVIEW && !$request->registrationKey) {
            throw new InvalidArgumentException('Не указан ключ прохождения');
        }

        $sco = $package->getDefaultSco() ?? throw new InvalidArgumentException('В пакете нет запускаемого SCO');

        return Yii::$app->db->transaction(function () use ($client, $package, $sco, $request): ScormLaunchToken {
            $select = ScormPackage::find()->select('id')->where(['id' => $package->id])->createCommand();
            Yii::$app->db->createCommand($select->sql . ' FOR UPDATE', $select->params)->queryScalar();
            /** @var ScormAttempt $attempt */
            [$attempt, $mode] = $this->resolveClientAttempt($client, $sco, $request);
            ScormLaunchToken::revokeForAttempt($attempt->id);

            return ScormLaunchToken::issue(
                $attempt,
                ScormLaunchToken::PURPOSE_LAUNCH,
                $mode,
                Yii::$app->params['launchTokenTtl']
            );
        });
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

    private function resolveClientAttempt(ScormClient $client, ScormSco $sco, LaunchRequestDTO $request): array
    {
        if ($request->mode === ScormLaunchToken::MODE_PREVIEW) {
            return [$this->createClientAttempt($client, $sco, $request, 1), ScormLaunchToken::MODE_PREVIEW];
        }

        $latest = ScormAttempt::findLatestForRegistration($client->id, $sco->package_id, $request->registrationKey);
        $reviewRequested = $request->mode === ScormLaunchToken::MODE_REVIEW;

        if (!$latest) {
            $mode = $reviewRequested ? ScormLaunchToken::MODE_REVIEW : ScormLaunchToken::MODE_NORMAL;
            return [$this->createClientAttempt($client, $sco, $request, 1), $mode];
        }
        if ($reviewRequested || ($latest->isFinished() && $latest->isCredited())) {
            return [$latest, ScormLaunchToken::MODE_REVIEW];
        }
        if ($latest->isFinished()) {
            return [
                $this->createClientAttempt($client, $sco, $request, $latest->attempt_number + 1, $latest->callback_version),
                ScormLaunchToken::MODE_NORMAL,
            ];
        }

        return [$latest, ScormLaunchToken::MODE_NORMAL];
    }

    private function createClientAttempt(ScormClient $client, ScormSco $sco, LaunchRequestDTO $request, int $number, int $callbackVersion = 0): ScormAttempt
    {
        $preview = $request->mode === ScormLaunchToken::MODE_PREVIEW;
        $attempt = new ScormAttempt([
            'client_id' => $client->id,
            'package_id' => $sco->package_id,
            'sco_id' => $sco->id,
            'attempt_number' => $number,
            'entry' => ScormAttempt::ENTRY_AB_INITIO,
            'is_preview' => $preview,
            'registration_key' => $preview ? null : $request->registrationKey,
            'learner_id' => $preview ? null : $request->learnerId,
            'learner_name' => $preview ? null : $request->learnerName,
            'course_ref' => $request->courseRef,
            'callback_version' => $callbackVersion,
        ]);

        if (!$attempt->save()) {
            throw new RuntimeException('Не удалось создать попытку: ' . json_encode($attempt->getFirstErrors()));
        }

        return $attempt;
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
            throw new RuntimeException('Не удалось создать попытку: ' . json_encode($attempt->getFirstErrors()));
        }

        return $attempt;
    }
}
