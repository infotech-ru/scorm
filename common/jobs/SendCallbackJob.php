<?php

namespace common\jobs;

use common\models\ScormClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SendCallbackJob extends BaseObject implements JobInterface
{
    public const string EVENT_INITIALIZE = 'initialize';
    public const string EVENT_COMMIT = 'commit';
    public const string EVENT_TERMINATE = 'terminate';

    public const int TIMEOUT = 10;
    public const array BACKOFF = [2, 4, 8, 16, 32];
    public const int BACKOFF_MAX = 300;
    public const int GIVE_UP_AFTER = 86400;

    public $clientId;
    public $payload;
    public $attempt;
    public $firstFailedAt;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function execute($queue): void
    {
        $client = ScormClient::findOne($this->clientId);

        if (!$client) {
            return;
        }

        $body = json_encode($this->payload);
        $timestamp = time();

        try {
            $response = new Client()->post($client->callback_url, [
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'X-Scorm-Timestamp' => (string)$timestamp,
                    'X-Scorm-Signature' => self::sign($client->callback_secret, $timestamp, $body),
                ],
                RequestOptions::BODY => $body,
                RequestOptions::TIMEOUT => self::TIMEOUT,
                RequestOptions::CONNECT_TIMEOUT => self::TIMEOUT,
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS => false,
            ]);
            $status = $response->getStatusCode();
            $error = 'HTTP ' . $status;
        } catch (GuzzleException $e) {
            $response = null;
            $status = 0;
            $error = $e->getMessage();
        }

        if ($status >= 200 && $status < 300) {
            return;
        }

        $now = time();
        $firstFailedAt = $this->firstFailedAt ?? $now;

        if (($status !== 0 && $status !== 429 && $status < 500) || $now - $firstFailedAt >= self::GIVE_UP_AFTER) {
            $this->logGiveUp($error);
            return;
        }

        $delay = max(self::BACKOFF[$this->attempt] ?? self::BACKOFF_MAX, $this->retryAfter($response, $now));

        $queue->delay($delay)->push(new self([
            'clientId' => $this->clientId,
            'payload' => $this->payload,
            'attempt' => $this->attempt + 1,
            'firstFailedAt' => $firstFailedAt,
        ]));
    }

    private function retryAfter(?ResponseInterface $response, int $now): int
    {
        $value = $response?->getHeaderLine('Retry-After');

        if (!$value) {
            return 0;
        }

        if (ctype_digit($value)) {
            return min($value, self::GIVE_UP_AFTER);
        }

        $date = strtotime($value);

        return $date === false ? 0 : max(0, min($date - $now, self::GIVE_UP_AFTER));
    }

    private function logGiveUp(string $error): void
    {
        Yii::error(sprintf(
            'SCORM callback завершился ошибкой: client %d, registration %s, version %d, attempt %d: %s',
            $this->clientId,
            $this->payload['registration_key'] ?? '',
            $this->payload['version'] ?? 0,
            $this->attempt,
            mb_substr($error, 0, 1000)
        ), __METHOD__);
    }
}
