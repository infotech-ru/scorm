<?php

namespace common\services;

use common\models\ScormAttempt;
use common\models\ScormPackage;
use common\models\ScormTrackingData;
use RuntimeException;
use Yii;

final class ScormRuntimeService
{
    public const MAX_VALUES_PER_REQUEST = 2000;
    private const MAX_SESSION_SECONDS = 86400;
    private const COLUMN_NUMBER_LIMIT = 99999999;

    private const COLUMNS = [
        '1.2' => [
            'cmi.core.lesson_status' => 'lesson_status',
            'cmi.core.lesson_location' => 'lesson_location',
            'cmi.core.score.raw' => 'score_raw',
            'cmi.core.score.min' => 'score_min',
            'cmi.core.score.max' => 'score_max',
            'cmi.core.exit' => 'exit_type',
            'cmi.suspend_data' => 'suspend_data',
        ],
        '2004' => [
            'cmi.completion_status' => 'completion_status',
            'cmi.success_status' => 'success_status',
            'cmi.location' => 'lesson_location',
            'cmi.score.scaled' => 'score_scaled',
            'cmi.score.raw' => 'score_raw',
            'cmi.score.min' => 'score_min',
            'cmi.score.max' => 'score_max',
            'cmi.progress_measure' => 'progress_measure',
            'cmi.exit' => 'exit_type',
            'cmi.suspend_data' => 'suspend_data',
        ],
    ];

    public function initialData(ScormAttempt $attempt, bool $review): array
    {
        $package = $attempt->package;
        $sco = $attempt->sco;
        $version = $package->version;
        $definitions = ScormDataModel::definitions($version);

        $data = [];
        foreach (ScormTrackingData::getAllForAttempt($attempt->id) as $element => $value) {
            $definition = $definitions[ScormDataModel::normalize($element)] ?? null;
            if ($definition !== null && $definition[0] === ScormDataModel::ACCESS_READ_WRITE) {
                $data[$element] = $value;
            }
        }

        $entry = $review ? '' : $attempt->entry;
        $credit = $review ? 'no-credit' : 'credit';
        $mode = $review ? 'review' : 'normal';
        $learnerId = (string)$attempt->user_id;
        $learnerName = 'Learner ' . $attempt->user_id;

        if ($version === ScormPackage::VERSION_12) {
            return array_merge($data, [
                'cmi.core.student_id' => $learnerId,
                'cmi.core.student_name' => $learnerName,
                'cmi.core.credit' => $credit,
                'cmi.core.lesson_status' => $attempt->lesson_status,
                'cmi.core.entry' => $entry,
                'cmi.core.lesson_mode' => $mode,
                'cmi.core.total_time' => ScormDataModel::formatSeconds($version, $attempt->total_time_seconds),
                'cmi.launch_data' => '',
                'cmi.comments_from_lms' => '',
                'cmi.student_data.mastery_score' => $sco->mastery_score === null ? '' : (string)(float)$sco->mastery_score,
                'cmi.student_data.max_time_allowed' => (string)$sco->max_time_allowed,
                'cmi.student_data.time_limit_action' => (string)$sco->time_limit_action,
            ]);
        }

        $data = $this->withObjectives($data, $sco->objective_ids ?? []);

        return array_merge($data, [
            'cmi.scaled_passing_score' => $sco->scaled_passing_score === null ? '' : (string)(float)$sco->scaled_passing_score,
            'cmi.learner_id' => $learnerId,
            'cmi.learner_name' => $learnerName,
            'cmi.credit' => $credit,
            'cmi.completion_status' => $attempt->completion_status ?? 'unknown',
            'cmi.success_status' => $attempt->success_status ?? 'unknown',
            'cmi.entry' => $entry,
            'cmi.mode' => $mode,
            'cmi.total_time' => ScormDataModel::formatSeconds($version, $attempt->total_time_seconds),
            'cmi.launch_data' => '',
            'cmi.time_limit_action' => $sco->time_limit_action ?: 'continue,no message',
        ]);
    }

    private function withObjectives(array $data, array $objectiveIds): array
    {
        $known = [];
        $next = 0;
        foreach ($data as $element => $value) {
            if (preg_match('/^cmi\.objectives\.(\d+)\.id$/', $element, $m)) {
                $known[] = $value;
                $next = max($next, (int)$m[1] + 1);
            }
        }

        foreach ($objectiveIds as $id) {
            if (!in_array($id, $known, true)) {
                $data['cmi.objectives.' . $next++ . '.id'] = $id;
            }
        }

        return $data;
    }

    public function commit(ScormAttempt $attempt, array $values): array
    {
        $version = $attempt->package->version;
        [$valid, $errors] = $this->validate($version, $values);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->apply($attempt, $version, $valid);
            $this->save($attempt);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $errors;
    }

    public function terminate(ScormAttempt $attempt, array $values, int $elapsedSeconds): array
    {
        $version = $attempt->package->version;
        [$valid, $errors] = $this->validate($version, $values);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->apply($attempt, $version, $valid);
            $this->finalize($attempt, $version, $valid, $elapsedSeconds);
            $this->save($attempt);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $errors;
    }

    private function validate(string $version, array $values): array
    {
        if (count($values) > self::MAX_VALUES_PER_REQUEST) {
            throw new RuntimeException('Too many values in one request');
        }

        $valid = [];
        $errors = [];
        foreach ($values as $element => $value) {
            $element = (string)$element;
            $error = ScormDataModel::validate($version, $element, $value);
            if ($error === null) {
                $valid[$element] = (string)$value;
            } else {
                $errors[$element] = $error;
            }
        }

        return [$valid, $errors];
    }

    private function apply(ScormAttempt $attempt, string $version, array $values): void
    {
        $columns = self::COLUMNS[$version];

        foreach ($values as $element => $value) {
            ScormTrackingData::upsert($attempt->id, $element, $value);

            $column = $columns[$element] ?? null;
            if ($column === null) {
                continue;
            }

            if (str_starts_with($column, 'score_') || $column === 'progress_measure') {
                $attempt->$column = $value === '' || abs((float)$value) > self::COLUMN_NUMBER_LIMIT ? null : $value;
            } elseif ($column === 'lesson_location') {
                $attempt->$column = mb_substr($value, 0, 255);
            } else {
                $attempt->$column = $value;
            }
        }

        $now = date('Y-m-d H:i:s');
        $attempt->started_at ??= $now;
        $attempt->last_accessed_at = $now;

        if ($attempt->completed_at === null && $attempt->hasTerminalStatus()) {
            $attempt->completed_at = $now;
        }
    }

    private function finalize(ScormAttempt $attempt, string $version, array $values, int $elapsedSeconds): void
    {
        $sessionElement = $version === ScormPackage::VERSION_12 ? 'cmi.core.session_time' : 'cmi.session_time';
        $sessionSeconds = isset($values[$sessionElement])
            ? ScormDataModel::durationToSeconds($values[$sessionElement])
            : $elapsedSeconds;
        $attempt->total_time_seconds += max(0, min($sessionSeconds, self::MAX_SESSION_SECONDS));

        $exit = (string)$attempt->exit_type;

        if ($version === ScormPackage::VERSION_12 && $exit !== ScormAttempt::EXIT_SUSPEND) {
            $this->evaluateScorm12Status($attempt);
        }
        if ($version === ScormPackage::VERSION_2004) {
            $this->evaluateScorm2004Success($attempt);
        }

        $attempt->entry = $exit === ScormAttempt::EXIT_SUSPEND ? ScormAttempt::ENTRY_RESUME : ScormAttempt::ENTRY_EMPTY;

        $now = date('Y-m-d H:i:s');
        if ($attempt->completed_at === null && $attempt->hasTerminalStatus()) {
            $attempt->completed_at = $now;
        }
        if ($exit !== ScormAttempt::EXIT_SUSPEND && $attempt->hasTerminalStatus()) {
            $attempt->finished_at = $now;
        }
    }

    private function evaluateScorm12Status(ScormAttempt $attempt): void
    {
        $mastery = $attempt->sco->mastery_score;
        if ($mastery !== null && $attempt->score_raw !== null) {
            $attempt->lesson_status = (float)$attempt->score_raw >= (float)$mastery
                ? ScormAttempt::STATUS_PASSED
                : ScormAttempt::STATUS_FAILED;
            return;
        }

        if ($attempt->lesson_status === ScormAttempt::STATUS_NOT_ATTEMPTED) {
            $attempt->lesson_status = ScormAttempt::STATUS_COMPLETED;
        }
    }

    private function evaluateScorm2004Success(ScormAttempt $attempt): void
    {
        $passingScore = $attempt->sco->scaled_passing_score;
        if ($passingScore !== null && $attempt->score_scaled !== null) {
            $attempt->success_status = (float)$attempt->score_scaled >= (float)$passingScore
                ? ScormAttempt::SUCCESS_PASSED
                : ScormAttempt::SUCCESS_FAILED;
        }
    }

    private function save(ScormAttempt $attempt): void
    {
        if (!$attempt->save()) {
            throw new RuntimeException('Failed to save attempt: ' . json_encode($attempt->getFirstErrors()));
        }
    }
}
