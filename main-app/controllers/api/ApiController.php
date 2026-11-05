<?php

namespace mainapp\controllers\api;

use common\enums\ApiErrorCode;
use mainapp\filters\ApiRateLimiter;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\Response;

abstract class ApiController extends Controller
{
    public function behaviors(): array
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
            'authenticator' => [
                'class' => HttpBearerAuth::class,
            ],
            'rateLimiter' => [
                'class' => ApiRateLimiter::class,
                'enableRateLimitHeaders' => true,
            ],
            'verbFilter' => [
                'class' => VerbFilter::class,
                'actions' => $this->verbs(),
            ],
        ];
    }

    protected function error(int $status, ApiErrorCode $code, string $message, ?int $retryAfter = null): array
    {
        Yii::$app->response->setStatusCode($status);
        if ($retryAfter !== null) {
            Yii::$app->response->headers->set('Retry-After', (string)$retryAfter);
        }

        return ['success' => false, 'error' => ['code' => $code->value, 'message' => $message]];
    }
}
