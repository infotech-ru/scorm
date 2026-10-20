<?php

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ScormLaunchToken extends ActiveRecord
{
    public const PURPOSE_LAUNCH = 'launch';
    public const PURPOSE_RUNTIME = 'runtime';

    public const MODE_NORMAL = 'normal';
    public const MODE_REVIEW = 'review';

    public static function tableName(): string
    {
        return 'scorm_launch_tokens';
    }

    public function getAttempt(): ActiveQuery
    {
        return $this->hasOne(ScormAttempt::class, ['id' => 'attempt_id']);
    }

    public static function issue(ScormAttempt $attempt, string $purpose, string $mode, int $ttl): self
    {
        $token = new self();
        $token->token = bin2hex(random_bytes(32));
        $token->user_id = $attempt->user_id;
        $token->attempt_id = $attempt->id;
        $token->purpose = $purpose;
        $token->mode = $mode;
        $token->is_active = 1;
        $token->ip_address = Yii::$app->request->userIP ?? null;
        $token->expires_at = date('Y-m-d H:i:s', time() + $ttl);
        $token->created_at = date('Y-m-d H:i:s');
        $token->save(false);

        return $token;
    }

    public static function revokeForAttempt(int $attemptId): void
    {
        self::updateAll(['is_active' => 0], ['attempt_id' => $attemptId, 'is_active' => 1]);
    }

    public static function consumeLaunchToken(string $token): ?self
    {
        $launchToken = self::findActive($token, self::PURPOSE_LAUNCH);
        if ($launchToken === null) {
            return null;
        }

        $consumed = self::updateAll(
            ['is_active' => 0, 'used_at' => date('Y-m-d H:i:s')],
            ['id' => $launchToken->id, 'is_active' => 1, 'used_at' => null]
        );

        return $consumed === 1 ? $launchToken : null;
    }

    public static function findActive(string $token, string $purpose): ?self
    {
        return self::find()
            ->where(['token' => $token, 'purpose' => $purpose, 'is_active' => 1])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->one();
    }

    public function isReview(): bool
    {
        return $this->mode === self::MODE_REVIEW;
    }

    public function extend(int $ttl): void
    {
        $this->updateAttributes(['expires_at' => date('Y-m-d H:i:s', time() + $ttl)]);
    }

    public function revoke(): void
    {
        $this->updateAttributes(['is_active' => 0]);
    }

    public function getLaunchUrl(): string
    {
        return Yii::$app->params['scormPlayerUrl'] . '/player/launch?token=' . $this->token;
    }
}
