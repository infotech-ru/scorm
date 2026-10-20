<?php

namespace common\models;

use yii\db\ActiveRecord;

class ScormSco extends ActiveRecord
{
    public const TYPE_SCO = 'sco';
    public const TYPE_ASSET = 'asset';

    public static function tableName(): string
    {
        return 'scorm_scos';
    }

    public function rules(): array
    {
        return [
            [['package_id', 'identifier', 'launch_url'], 'required'],
            [['package_id', 'sort_order', 'parent_id'], 'integer'],
            [['identifier', 'title'], 'string', 'max' => 255],
            [['launch_url'], 'string', 'max' => 500],
            [['parameters', 'prerequisites'], 'string', 'max' => 1000],
            [['max_time_allowed', 'time_limit_action'], 'string', 'max' => 50],
            [['mastery_score'], 'number', 'min' => 0, 'max' => 100],
            [['scaled_passing_score'], 'number', 'min' => -1, 'max' => 1],
            [['objective_ids'], 'each', 'rule' => ['string', 'max' => 4000]],
            ['scorm_type', 'in', 'range' => [self::TYPE_SCO, self::TYPE_ASSET]],
            ['scorm_type', 'default', 'value' => self::TYPE_SCO],
            ['sort_order', 'default', 'value' => 0],
        ];
    }

    public function getPackage(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ScormPackage::class, ['id' => 'package_id']);
    }

    public function getParent(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ScormSco::class, ['id' => 'parent_id']);
    }

    public function getChildren(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ScormSco::class, ['parent_id' => 'id']);
    }

    public function getRelativeLaunchUrl(): string
    {
        $path = ltrim($this->launch_url, '/');
        $parameters = trim((string)$this->parameters);

        if ($parameters === '') {
            return $path;
        }

        if ($parameters[0] === '#') {
            return $path . $parameters;
        }

        return $path . (str_contains($path, '?') ? '&' : '?') . ltrim($parameters, '?&');
    }
}
