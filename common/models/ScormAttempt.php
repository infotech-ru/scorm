<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property int $package_id
 * @property int $sco_id
 * @property string|null $registration_key
 * @property string|null $learner_id
 * @property string|null $learner_name
 * @property string|null $course_ref
 * @property int $callback_version
 * @property int $attempt_number
 * @property bool $is_preview
 * @property string|null $lesson_location
 * @property string $lesson_status
 * @property string|null $completion_status
 * @property string|null $success_status
 * @property string|null $entry
 * @property string|null $exit_type
 * @property float|null $score_raw
 * @property float|null $score_min
 * @property float|null $score_max
 * @property float|null $score_scaled
 * @property float|null $progress_measure
 * @property string|null $suspend_data
 * @property int $total_time_seconds
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property string|null $finished_at
 * @property string|null $last_accessed_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property ScormPackage $package
 * @property ScormClient|null $client
 * @property ScormSco $sco
 * @property ScormTrackingData[] $trackingData
 */
class ScormAttempt extends ActiveRecord
{
    public const string STATUS_PASSED = 'passed';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_INCOMPLETE = 'incomplete';
    public const string STATUS_BROWSED = 'browsed';
    public const string STATUS_NOT_ATTEMPTED = 'not attempted';

    public const string COMPLETION_COMPLETED = 'completed';
    public const string COMPLETION_UNKNOWN = 'unknown';

    public const string SUCCESS_PASSED = 'passed';
    public const string SUCCESS_FAILED = 'failed';
    public const string SUCCESS_UNKNOWN = 'unknown';

    public const string ENTRY_AB_INITIO = 'ab-initio';
    public const string ENTRY_RESUME = 'resume';
    public const string ENTRY_EMPTY = '';

    public const string EXIT_SUSPEND = 'suspend';

    public const array LESSON_STATUSES = [self::STATUS_PASSED, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_INCOMPLETE, self::STATUS_BROWSED, self::STATUS_NOT_ATTEMPTED,];

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
            [['package_id', 'sco_id'], 'required'],
            [['user_id', 'client_id', 'package_id', 'sco_id', 'attempt_number', 'total_time_seconds'], 'integer', 'min' => 0],
            [['registration_key', 'learner_id', 'course_ref'], 'string', 'max' => 64],
            ['learner_name', 'string', 'max' => 255],
            ['is_preview', 'boolean'],
            ['is_preview', 'default', 'value' => 0],
            ['attempt_number', 'default', 'value' => 1],
            [['lesson_location'], 'string', 'max' => 255],
            [['score_raw', 'score_min', 'score_max'], 'number'],
            [['score_scaled', 'progress_measure'], 'number', 'min' => -1, 'max' => 1],
            [['suspend_data'], 'string'],
            ['lesson_status', 'in', 'range' => self::LESSON_STATUSES],
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

    public function getClient(): ActiveQuery
    {
        return $this->hasOne(ScormClient::class, ['id' => 'client_id']);
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

    public function isCredited(): bool
    {
        if ($this->success_status === self::SUCCESS_FAILED || $this->lesson_status === self::STATUS_FAILED) {
            return false;
        }

        return in_array($this->lesson_status, [self::STATUS_PASSED, self::STATUS_COMPLETED, self::STATUS_BROWSED])
            || $this->success_status === self::SUCCESS_PASSED
            || $this->completion_status === self::COMPLETION_COMPLETED;
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
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return self::find()
            ->where(['user_id' => $userId, 'sco_id' => $scoId])
            ->orderBy(['attempt_number' => SORT_DESC])
            ->one();
    }

    public static function findLatestForRegistration(int $clientId, int $packageId, string $registrationKey): ?self
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return self::find()
            ->where([
                'client_id' => $clientId,
                'package_id' => $packageId,
                'registration_key' => $registrationKey,
                'is_preview' => 0,
            ])
            ->orderBy(['attempt_number' => SORT_DESC, 'id' => SORT_DESC])
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
