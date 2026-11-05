<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $package_id
 * @property int|null $parent_id
 * @property string $identifier
 * @property string|null $title
 * @property string $launch_url
 * @property string|null $parameters
 * @property string|null $prerequisites
 * @property string|null $max_time_allowed
 * @property string|null $time_limit_action
 * @property float|null $mastery_score
 * @property float|null $scaled_passing_score
 * @property string[]|null $objective_ids
 * @property string $scorm_type
 * @property int $sort_order
 * @property string|null $created_at
 *
 * @property ScormPackage $package
 * @property ScormSco|null $parent
 * @property ScormSco[] $children
 */
class ScormSco extends ActiveRecord
{
    public const string TYPE_SCO = 'sco';
    public const string TYPE_ASSET = 'asset';

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
