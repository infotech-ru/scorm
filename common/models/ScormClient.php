<?php

namespace common\models;

use common\helpers\Helper;
use InvalidArgumentException;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\filters\RateLimitInterface;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $name
 * @property string $origin
 * @property string $callback_url
 * @property string $api_key_hash
 * @property string $callback_secret
 * @property string|null $created_at
 */
class ScormClient extends ActiveRecord implements RateLimitInterface, IdentityInterface
{
    public const int RATE_LIMIT_REQUESTS = 600;
    public const int RATE_LIMIT_WINDOW = 60;
    public const int PROCESSING_STALE_AFTER = 3600;

    public static function tableName(): string
    {
        return 'scorm_clients';
    }

    public function rules(): array
    {
        return [
            [['name', 'origin', 'callback_url'], 'required'],
            ['name', 'string', 'max' => 255],
            ['origin', 'string', 'max' => 255],
            ['origin', function (string $attribute): void {
                if (!Helper::isValidOrigin($this->$attribute)) {
                    $this->addError($attribute, 'Origin должен быть в формате scheme://host[:port]');
                }
            }],
            ['callback_url', 'string', 'max' => 1024],
            ['callback_url', 'url', 'validSchemes' => ['http', 'https'], 'enableIDN' => false],
        ];
    }

    public static function create(string $name, string $origin, string $callbackUrl): array
    {
        $apiKey = bin2hex(random_bytes(32));
        $callbackSecret = bin2hex(random_bytes(32));

        $client = new self();
        $client->name = $name;
        $client->origin = $origin;
        $client->callback_url = $callbackUrl;
        $client->api_key_hash = hash('sha256', $apiKey);
        $client->callback_secret = $callbackSecret;

        if (!$client->save()) {
            throw new InvalidArgumentException(implode('; ', $client->getFirstErrors()));
        }

        return ['client' => $client, 'apiKey' => $apiKey, 'callbackSecret' => $callbackSecret];
    }

    public static function findIdentity($id): ?static
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        return static::findOne(['api_key_hash' => hash('sha256', $token)]);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }

    public function getRateLimit($request, $action): array
    {
        return [self::RATE_LIMIT_REQUESTS, self::RATE_LIMIT_WINDOW];
    }

    public function loadAllowance($request, $action): array
    {
        $data = Yii::$app->cache->get($this->allowanceCacheKey());

        return is_array($data) ? $data : [self::RATE_LIMIT_REQUESTS, time()];
    }

    public function saveAllowance($request, $action, $allowance, $timestamp): void
    {
        Yii::$app->cache->set($this->allowanceCacheKey(), [$allowance, $timestamp], self::RATE_LIMIT_WINDOW * 2);
    }

    public function consumeUploadSlot(): int
    {
        [$limit, $window] = Yii::$app->params['uploadRateLimit'];
        $key = 'scorm-client-upload:' . $this->id;
        $now = time();

        $state = Yii::$app->cache->get($key);
        if (!is_array($state) || $state['resetAt'] <= $now) {
            $state = ['count' => 0, 'resetAt' => $now + $window];
        }
        if ($state['count'] >= $limit) {
            return max(1, $state['resetAt'] - $now);
        }

        $state['count']++;
        Yii::$app->cache->set($key, $state, $state['resetAt'] - $now);

        return 0;
    }

    public function hasTooManyProcessing(): bool
    {
        return ScormPackage::find()
            ->where(['client_id' => $this->id, 'status' => ScormPackage::STATUS_PROCESSING])
            ->andWhere(['>=', 'created_at', new Expression('NOW() - INTERVAL ' . self::PROCESSING_STALE_AFTER . ' SECOND')])
            ->count() >= Yii::$app->params['maxProcessingPerClient'];
    }

    private function allowanceCacheKey(): string
    {
        return 'scorm-client-allowance:' . $this->id;
    }
}
