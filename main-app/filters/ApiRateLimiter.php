<?php

namespace mainapp\filters;

use yii\filters\RateLimiter;
use yii\web\TooManyRequestsHttpException;

class ApiRateLimiter extends RateLimiter
{
    public function checkRateLimit($user, $request, $response, $action): void
    {
        try {
            parent::checkRateLimit($user, $request, $response, $action);
        } catch (TooManyRequestsHttpException $e) {
            [$limit, $window] = $user->getRateLimit($request, $action);
            $response->headers->set('Retry-After', max(1, ceil($window / $limit)));

            throw $e;
        }
    }
}
