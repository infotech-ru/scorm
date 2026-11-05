<?php

namespace common\services;

use common\jobs\SendCallbackJob;
use common\models\ScormAttempt;
use common\models\ScormPackage;
use Yii;

final class CallbackNotifier
{
    public function notify(ScormAttempt $attempt, string $event): void
    {
        if ($attempt->is_preview == 1 || !$attempt->client_id || !$attempt->registration_key) {
            return;
        }

        ScormAttempt::updateAllCounters(['callback_version' => 1], ['id' => $attempt->id]);
        $version = ScormAttempt::find()->select('callback_version')->where(['id' => $attempt->id])->scalar();

        Yii::$app->queue->push(new SendCallbackJob([
            'clientId' => $attempt->client_id,
            'payload' => $this->payload($attempt, $event, $version),
        ]));
    }

    private function payload(ScormAttempt $attempt, string $event, int $version): array
    {
        return [
            'registration_key' => $attempt->registration_key,
            'learner_id' => $attempt->learner_id,
            'course_ref' => $attempt->course_ref,
            'version' => $version,
            'event' => $event,
            'lesson_status' => $this->lessonStatus($attempt),
            'score_raw' => $attempt->score_raw,
            'score_min' => $attempt->score_min,
            'score_max' => $attempt->score_max,
            'first_launch_at' => $this->firstLaunchAt($attempt),
        ];
    }

    private function lessonStatus(ScormAttempt $attempt): ?string
    {
        if ($attempt->package->version !== ScormPackage::VERSION_2004) {
            return $attempt->lesson_status ?: null;
        }

        if (in_array($attempt->success_status, [ScormAttempt::SUCCESS_PASSED, ScormAttempt::SUCCESS_FAILED])) {
            return $attempt->success_status;
        }

        $completion = $attempt->completion_status;

        return !$completion || $completion === ScormAttempt::COMPLETION_UNKNOWN ? null : $completion;
    }

    private function firstLaunchAt(ScormAttempt $attempt): ?string
    {
        return ScormAttempt::find()
            ->where([
                'client_id' => $attempt->client_id,
                'package_id' => $attempt->package_id,
                'registration_key' => $attempt->registration_key,
                'is_preview' => 0,
            ])
            ->min('started_at');
    }
}
