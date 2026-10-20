<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

class ScormTrackingData extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'scorm_tracking_data';
    }

    public static function upsert(int $attemptId, string $element, string $value): void
    {
        static::getDb()->createCommand()->upsert(
            static::tableName(),
            [
                'attempt_id' => $attemptId,
                'element' => $element,
                'value' => $value,
                'created_at' => new Expression('NOW()'),
                'updated_at' => new Expression('NOW()'),
            ],
            [
                'value' => $value,
                'updated_at' => new Expression('NOW()'),
            ]
        )->execute();
    }

    public static function getAllForAttempt(int $attemptId): array
    {
        return self::find()
            ->select(['value', 'element'])
            ->where(['attempt_id' => $attemptId])
            ->indexBy('element')
            ->column();
    }
}
