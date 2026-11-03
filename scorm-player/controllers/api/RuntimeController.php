<?php

namespace scormplayer\controllers\api;

use common\models\ScormLaunchToken;
use common\services\ScormRuntimeService;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class RuntimeController extends Controller
{
    private const int MAX_BODY_BYTES = 1048576;

    public function behaviors(): array
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['initialize' => ['POST'], 'commit' => ['POST'], 'terminate' => ['POST']],
            ],
        ];
    }

    public function actionInitialize(): array
    {
        $token = $this->authenticate();

        if ($token->isReview()) {
            return ['success' => true, 'errors' => []];
        }

        new ScormRuntimeService()->initialize($token->attempt);
        $token->extend(Yii::$app->params['runtimeTokenTtl']);

        return ['success' => true, 'errors' => []];
    }

    public function actionCommit(): array
    {
        $token = $this->authenticate();

        if ($token->isReview()) {
            return ['success' => true, 'errors' => []];
        }

        $errors = new ScormRuntimeService()->commit($token->attempt, $this->values());
        $token->extend(Yii::$app->params['runtimeTokenTtl']);

        return ['success' => true, 'errors' => $errors];
    }

    public function actionTerminate(): array
    {
        $token = $this->authenticate();
        $errors = [];

        if (!$token->isReview()) {
            $elapsed = (int)Yii::$app->request->getBodyParam('elapsed', 0);
            $errors = new ScormRuntimeService()->terminate($token->attempt, $this->values(), $elapsed);
        }

        $token->revoke();

        return ['success' => true, 'errors' => $errors];
    }

    private function authenticate(): ScormLaunchToken
    {
        $header = (string)Yii::$app->request->headers->get('Authorization');
        $token = preg_match('/^Bearer\s+([a-f0-9]{64})$/', $header, $matches)
            ? ScormLaunchToken::findActive($matches[1], ScormLaunchToken::PURPOSE_RUNTIME)
            : null;

        if ($token === null) {
            throw new UnauthorizedHttpException('Runtime-токен недействителен или устарел');
        }

        return $token;
    }

    private function values(): array
    {
        if (strlen(Yii::$app->request->getRawBody()) > self::MAX_BODY_BYTES) {
            throw new BadRequestHttpException('Слишком большое тело запроса');
        }

        $values = Yii::$app->request->getBodyParam('values', []);
        if (!is_array($values)) {
            throw new BadRequestHttpException('values должен быть объектом');
        }

        return $values;
    }
}
