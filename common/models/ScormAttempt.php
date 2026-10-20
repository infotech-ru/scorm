<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

class ScormAttempt extends ActiveRecord
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_BROWSED = 'browsed';
    public const STATUS_NOT_ATTEMPTED = 'not attempted';

    public const COMPLETION_COMPLETED = 'completed';
    public const COMPLETION_UNKNOWN = 'unknown';

    public const SUCCESS_PASSED = 'passed';
    public const SUCCESS_FAILED = 'failed';
    public const SUCCESS_UNKNOWN = 'unknown';

    public const ENTRY_AB_INITIO = 'ab-initio';
    public const ENTRY_RESUME = 'resume';
    public const ENTRY_EMPTY = '';

    public const EXIT_SUSPEND = 'suspend';

    public static function tableName(): string
    {
        return 'scorm_attempts';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'package_id', 'sco_id'], 'required'],
            [['user_id', 'package_id', 'sco_id', 'attempt_number', 'total_time_seconds'], 'integer', 'min' => 0],
            ['attempt_number', 'default', 'value' => 1],
            [['lesson_location'], 'string', 'max' => 255],
            [['score_raw', 'score_min', 'score_max'], 'number'],
            [['score_scaled', 'progress_measure'], 'number', 'min' => -1, 'max' => 1],
            [['suspend_data'], 'string'],
            ['lesson_status', 'in', 'range' => [
                self::STATUS_PASSED, self::STATUS_COMPLETED, self::STATUS_FAILED,
                self::STATUS_INCOMPLETE, self::STATUS_BROWSED, self::STATUS_NOT_ATTEMPTED,
            ]],
            ['lesson_status', 'default', 'value' => self::STATUS_NOT_ATTEMPTED],
            ['completion_status', 'in', 'range' => ['completed', 'incomplete', 'not attempted', 'unknown']],
            ['success_status', 'in', 'range' => [self::SUCCESS_PASSED, self::SUCCESS_FAILED, self::SUCCESS_UNKNOWN]],
            ['entry', 'in', 'range' => [self::ENTRY_AB_INITIO, self::ENTRY_RESUME, self::ENTRY_EMPTY]],
            ['entry', 'default', 'value' => self::ENTRY_AB_INITIO],
            ['exit_type', 'in', 'range' => ['time-out', 'suspend', 'logout', 'normal', '']],
            [['started_at', 'completed_at', 'finished_at', 'last_accessed_at'], 'safe'],
        ];
    }

    public function getPackage(): ActiveQuery
    {
        return $this->hasOne(ScormPackage::class, ['id' => 'package_id']);
    }

    public function getSco(): ActiveQuery
    {
        return $this->hasOne(ScormSco::class, ['id' => 'sco_id']);
    }

    public function getTrackingData(): ActiveQuery
    {
        return $this->hasMany(ScormTrackingData::class, ['attempt_id' => 'id']);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function hasTerminalStatus(): bool
    {
        return in_array($this->lesson_status, [self::STATUS_PASSED, self::STATUS_COMPLETED, self::STATUS_FAILED], true)
            || $this->completion_status === self::COMPLETION_COMPLETED
            || in_array($this->success_status, [self::SUCCESS_PASSED, self::SUCCESS_FAILED], true);
    }

    public function getDisplayStatus(): string
    {
        if ($this->success_status && $this->success_status !== self::SUCCESS_UNKNOWN) {
            return $this->success_status;
        }

        if ($this->completion_status && $this->completion_status !== self::COMPLETION_UNKNOWN) {
            return $this->completion_status;
        }

        return $this->lesson_status ?? self::STATUS_NOT_ATTEMPTED;
    }

    public static function statusColor(string $status): string
    {
        return [
            self::STATUS_PASSED => 'success',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_INCOMPLETE => 'warning',
            self::STATUS_BROWSED => 'info',
        ][$status] ?? 'secondary';
    }

    public static function findLatest(int $userId, int $scoId): ?self
    {
        return self::find()
            ->where(['user_id' => $userId, 'sco_id' => $scoId])
            ->orderBy(['attempt_number' => SORT_DESC])
            ->one();
    }

    public static function findLatestByScoForUser(int $userId, int $packageId): array
    {
        $latest = [];
        $attempts = self::find()
            ->where(['user_id' => $userId, 'package_id' => $packageId])
            ->orderBy(['attempt_number' => SORT_DESC])
            ->all();

        foreach ($attempts as $attempt) {
            $latest[$attempt->sco_id] ??= $attempt;
        }

        return $latest;
    }
}
