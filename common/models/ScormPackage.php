<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property int|null $client_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $identifier
 * @property string $version
 * @property string|null $scorm_version_detail
 * @property string $original_filename
 * @property int|null $size
 * @property string $status
 * @property string|null $extracted_path
 * @property string|null $launch_url
 * @property string|null $error_message
 * @property array|null $metadata
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property ScormSco[] $scos
 * @property ScormAttempt[] $attempts
 */
class ScormPackage extends ActiveRecord
{
    public const string STATUS_UPLOADING = 'uploading';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_READY = 'ready';
    public const string STATUS_ERROR = 'error';

    public const string VERSION_12 = '1.2';
    public const string VERSION_2004 = '2004';

    public static function tableName(): string
    {
        return 'scorm_packages';
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
            [['title', 'original_filename'], 'required'],
            [['title', 'identifier', 'original_filename'], 'string', 'max' => 255],
            [['extracted_path', 'launch_url'], 'string', 'max' => 500],
            [['scorm_version_detail'], 'string', 'max' => 50],
            ['version', 'in', 'range' => [self::VERSION_12, self::VERSION_2004]],
            ['status', 'in', 'range' => [self::STATUS_UPLOADING, self::STATUS_PROCESSING, self::STATUS_READY, self::STATUS_ERROR]],
            ['status', 'default', 'value' => self::STATUS_UPLOADING],
            ['version', 'default', 'value' => self::VERSION_12],
            [['error_message'], 'string'],
            [['metadata'], 'safe'],
            [['created_by', 'client_id', 'size'], 'integer'],
        ];
    }

    public static function findForClient(ScormClient $client, int $id): ?self
    {
        return static::findOne(['id' => $id, 'client_id' => $client->id]);
    }

    public static function findAllForClient(ScormClient $client): array
    {
        return static::find()
            ->where(['client_id' => $client->id])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'identifier' => 'Identifier',
            'version' => 'SCORM Version',
            'original_filename' => 'Original Filename',
            'status' => 'Status',
            'created_at' => 'Created At',
        ];
    }

    public function getScos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ScormSco::class, ['package_id' => 'id']);
    }

    public function getAttempts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ScormAttempt::class, ['package_id' => 'id']);
    }

    public function getOrderedScos(): array
    {
        return $this->getScos()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    public function getDefaultSco(): ?ScormSco
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getScos()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->one();
    }

    public function findSco(int $scoId): ?ScormSco
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getScos()->andWhere(['id' => $scoId])->one();
    }

    public function getToc(): array
    {
        return $this->metadata['toc'] ?? [];
    }

    public function getTocRows(array $scos): array
    {
        $byIdentifier = [];
        foreach ($scos as $sco) {
            $byIdentifier[$sco->identifier] = $sco;
        }

        $rows = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$rows, &$byIdentifier): void {
            foreach ($nodes as $node) {
                $sco = $node['sco'] ? ($byIdentifier[$node['identifier']] ?? null) : null;
                if ($sco) {
                    unset($byIdentifier[$node['identifier']]);
                }
                $rows[] = ['depth' => $depth, 'title' => $node['title'], 'sco' => $sco];
                $walk($node['children'], $depth + 1);
            }
        };
        $walk($this->getToc(), 0);

        foreach ($byIdentifier as $sco) {
            $rows[] = ['depth' => 0, 'title' => $sco->title ?? $sco->identifier, 'sco' => $sco];
        }

        return $rows;
    }

    public function getStatusColor(): string
    {
        return [
            self::STATUS_READY => 'success',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_UPLOADING => 'info',
            self::STATUS_ERROR => 'danger',
        ][$this->status] ?? 'secondary';
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
