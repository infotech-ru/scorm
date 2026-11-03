<?php

namespace common\components;

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use common\helpers\Helper;
use DomainException;
use GuzzleHttp\Psr7\Stream;
use InvalidArgumentException;
use Throwable;
use yii\base\Component;

class SelectelStorageS3 extends Component
{
    private const int DELETE_BATCH_SIZE = 1000;

    public string $accessKey = '';
    public string $secretKey = '';
    public string $container = '';
    public string $region = 'ru-1';

    private ?S3Client $client = null;

    protected function client(): S3Client
    {
        $this->client ??= new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
            'endpoint' => sprintf('https://s3.%s.storage.selcloud.ru', $this->region),
        ]);

        return $this->client;
    }

    public function upload(string $filePath, string $key, string $mime): void
    {
        $this->assertConfigured();

        if (!file_exists($filePath)) {
            throw new DomainException("SelectelStorageS3 - файл $filePath не существует");
        }

        $resource = fopen($filePath, 'rb');

        if ($resource === false) {
            throw new DomainException("SelectelStorageS3 - ошибка чтения файла $filePath");
        }

        $stream = new Stream($resource);

        try {
            $this->client()->putObject([
                'Bucket' => $this->container,
                'Key' => $key,
                'Body' => $stream,
                'ContentType' => $mime,
            ]);
        } catch (Throwable $e) {
            throw new DomainException("SelectelStorageS3 - ошибка загрузки файла $filePath " . $this->errorCode($e));
        } finally {
            $stream->close();
        }
    }

    public function getObject(string $key, ?string $range = null): ?Result
    {
        $this->assertConfigured();

        $params = [
            'Bucket' => $this->container,
            'Key' => $key,
            '@http' => ['stream' => true],
        ];

        if ($range !== null) {
            $params['Range'] = $range;
        }

        try {
            return $this->client()->getObject($params);
        } catch (AwsException $e) {
            $code = $e->getAwsErrorCode();
            $status = (int)$e->getStatusCode();

            if ($status === 404 || $code === 'NoSuchKey') {
                return null;
            }

            if ($status === 416 || $code === 'InvalidRange') {
                throw new DomainException('SelectelStorageS3 - диапазон не выполним', 416);
            }

            throw new DomainException("SelectelStorageS3 - ошибка чтения объекта $key " . $this->errorCode($e));
        } catch (Throwable $e) {
            throw new DomainException("SelectelStorageS3 - ошибка чтения объекта $key " . $this->errorCode($e));
        }
    }

    public function deletePrefix(string $prefix): void
    {
        if (!$prefix) {
            return;
        }

        if (!Helper::isValidPrefix($prefix)) {
            throw new InvalidArgumentException('SelectelStorageS3 - недопустимый префикс объектов');
        }

        $this->assertConfigured();

        try {
            $token = null;

            do {
                $params = ['Bucket' => $this->container, 'Prefix' => $prefix];

                if ($token !== null) {
                    $params['ContinuationToken'] = $token;
                }

                $list = $this->client()->listObjectsV2($params);
                $objects = array_map(fn(array $item) => ['Key' => $item['Key']], $list['Contents'] ?? []);

                foreach (array_chunk($objects, self::DELETE_BATCH_SIZE) as $batch) {
                    $result = $this->client()->deleteObjects([
                        'Bucket' => $this->container,
                        'Delete' => ['Objects' => $batch, 'Quiet' => true],
                    ]);

                    $errors = $result['Errors'] ?? [];

                    if ($errors) {
                        $codes = implode(',', array_unique(array_map(fn(array $error) => (string)($error['Code'] ?? ''), $errors)));

                        throw new DomainException(sprintf(
                            'SelectelStorageS3 - не удалось удалить объектов: %d (%s) в префиксе %s',
                            count($errors),
                            $codes,
                            $prefix
                        ));
                    }
                }

                $token = !empty($list['IsTruncated']) ? ($list['NextContinuationToken'] ?? null) : null;
            } while ($token !== null);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException("SelectelStorageS3 - ошибка удаления префикса $prefix " . $this->errorCode($e));
        }
    }

    private function assertConfigured(): void
    {
        if (!$this->accessKey || !$this->secretKey || !$this->container) {
            throw new DomainException('SelectelStorageS3 - хранилище не настроено');
        }
    }

    private function errorCode(Throwable $e): string
    {
        return $e instanceof AwsException ? (string)$e->getAwsErrorCode() : $e::class;
    }
}
