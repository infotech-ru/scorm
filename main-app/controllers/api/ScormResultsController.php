<?php

namespace mainapp\controllers\api;

use common\models\ScormAttempt;
use common\models\ScormTrackingData;
use Yii;
use yii\web\NotFoundHttpException;

class ScormResultsController extends ApiController
{
    public function actionAttempt(int $id): array
    {
        $attempt = ScormAttempt::findOne(['id' => $id, 'user_id' => Yii::$app->params['learnerId']])
            ?? throw new NotFoundHttpException('Attempt not found');

        return [
            'success' => true,
            'data' => [
                'id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'package_id' => $attempt->package_id,
                'sco_id' => $attempt->sco_id,
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->getDisplayStatus(),
                'lesson_status' => $attempt->lesson_status,
                'completion_status' => $attempt->completion_status,
                'success_status' => $attempt->success_status,
                'location' => $attempt->lesson_location,
                'score' => [
                    'raw' => $attempt->score_raw,
                    'min' => $attempt->score_min,
                    'max' => $attempt->score_max,
                    'scaled' => $attempt->score_scaled,
                ],
                'progress_measure' => $attempt->progress_measure,
                'total_time_seconds' => $attempt->total_time_seconds,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,
                'finished_at' => $attempt->finished_at,
                'last_accessed_at' => $attempt->last_accessed_at,
                'tracking_data' => ScormTrackingData::getAllForAttempt($attempt->id),
            ],
        ];
    }
}
