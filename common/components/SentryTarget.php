<?php

namespace common\components;

use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;
use Yii;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\init;
use function Sentry\withScope;

class SentryTarget extends Target
{
    public string $dsn = '';

    public function init(): void
    {
        parent::init();

        if ($this->enabled) {
            init(['dsn' => $this->dsn]);
        }
    }

    public function export(): void
    {
        foreach ($this->messages as [$text, $level, $category]) {
            withScope(function (Scope $scope) use ($text, $level, $category): void {
                $scope->setTag('app', Yii::$app->id);
                $scope->setTag('category', $category);

                if ($text instanceof Throwable) {
                    captureException($text);
                } else {
                    captureMessage(
                        is_string($text) ? $text : VarDumper::export($text),
                        $level === Logger::LEVEL_ERROR ? Severity::error() : Severity::warning()
                    );
                }
            });
        }
    }
}
