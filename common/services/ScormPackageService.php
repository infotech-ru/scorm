<?php

namespace common\services;

use common\enums\ApiErrorCode;
use common\exceptions\ScormPackageException;
use common\helpers\Helper;
use common\models\ScormClient;
use common\models\ScormPackage;
use common\models\ScormSco;
use FilesystemIterator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use Yii;
use yii\db\Exception;
use yii\web\UploadedFile;

class ScormPackageService
{
    private const int TIMEOUT = 600;
    private const int CONNECT_TIMEOUT = 30;
    private const int MAX_REDIRECTS = 5;

    private ZipExtractor $zipExtractor;
    private ScormManifestParser $manifestParser;

    public function __construct()
    {
        $params = Yii::$app->params;
        $this->zipExtractor = new ZipExtractor($params['maxPackageFiles'], $params['maxExtractedSize']);
        $this->manifestParser = new ScormManifestParser();
    }

    /**
     * @throws Throwable
     */
    public function processUpload(UploadedFile $file, ?int $userId = null): ScormPackage
    {
        $this->validateFile($file);

        return $this->processFile($file->tempName, $file->name, null, $userId);
    }

    /**
     * @throws Throwable
     */
    public function processRemote(ScormClient $client, string $sourceUrl, string $fileName): ScormPackage
    {
        $package = $this->createRecord($fileName, $client, null);
        $zipPath = $this->zipPath($package);

        try {
            $this->download($sourceUrl, $zipPath, $this->maxPackageSize());

            return $this->build($package, $zipPath);
        } catch (Throwable $e) {
            $package->delete();

            throw $e;
        } finally {
            $this->cleanupTemp($package);
        }
    }

    private function download(string $url, string $targetPath, int $maxBytes): void
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https']) || !parse_url($url, PHP_URL_HOST)) {
            throw new ScormPackageException(ApiErrorCode::DownloadFailed, 'Поддерживаются только ссылки http и https');
        }

        $tooLarge = false;

        try {
            new Client()->get($url, [
                RequestOptions::SINK => $targetPath,
                RequestOptions::TIMEOUT => self::TIMEOUT,
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
                RequestOptions::ALLOW_REDIRECTS => ['max' => self::MAX_REDIRECTS, 'protocols' => ['http', 'https']],
                RequestOptions::ON_HEADERS => function (ResponseInterface $response) use ($maxBytes, &$tooLarge): void {
                    if ($response->getHeaderLine('Content-Length') > $maxBytes) {
                        $tooLarge = true;
                        throw new RuntimeException('Пакет превышает допустимый размер');
                    }
                },
                RequestOptions::PROGRESS => function (int $total, int $downloaded) use ($maxBytes, &$tooLarge): bool {
                    $tooLarge = $tooLarge || $downloaded > $maxBytes;

                    return $tooLarge || $downloaded > $maxBytes;
                },
            ]);
        } catch (GuzzleException) {
            @unlink($targetPath);

            if ($tooLarge) {
                throw new ScormPackageException(ApiErrorCode::TooLarge, 'Файл слишком большой. Максимальный размер: ' . round($maxBytes / 1024 / 1024) . ' МБ');
            }
            throw new ScormPackageException(ApiErrorCode::DownloadFailed, 'Не удалось скачать пакет');
        }
    }

    public function processFile(string $zipPath, string $fileName, ?ScormClient $client, ?int $userId = null): ScormPackage
    {
        if (!is_file($zipPath)) {
            throw new ScormPackageException(ApiErrorCode::InvalidZip, 'Файл не является корректным ZIP-архивом');
        }
        if (filesize($zipPath) > $this->maxPackageSize()) {
            throw new ScormPackageException(ApiErrorCode::TooLarge, 'Файл слишком большой. Максимальный размер: ' . round($this->maxPackageSize() / 1024 / 1024) . ' МБ');
        }

        $package = $this->createRecord($fileName, $client, $userId);

        try {
            $storedPath = $this->zipPath($package);
            if (!copy($zipPath, $storedPath)) {
                throw new RuntimeException('Не удалось сохранить загруженный файл');
            }

            return $this->build($package, $storedPath);
        } catch (Throwable $e) {
            $package->delete();

            throw $e;
        } finally {
            $this->cleanupTemp($package);
        }
    }

    public function deletePackage(ScormPackage $package): void
    {
        Yii::$app->storage->deletePrefix($package->extracted_path);

        if (!$package->delete()) {
            throw new RuntimeException('Не удалось удалить пакет');
        }
    }

    private function build(ScormPackage $package, string $zipPath): ScormPackage
    {
        $extractPath = $this->ensureDirectory(Yii::$app->params['scormTempPath']) . '/' . $package->id;
        $prefix = null;

        try {
            if (filesize($zipPath) > $this->maxPackageSize()) {
                throw new ScormPackageException(ApiErrorCode::TooLarge, 'Файл слишком большой. Максимальный размер: ' . round($this->maxPackageSize() / 1024 / 1024) . ' МБ');
            }
            if (!$this->zipExtractor->isArchive($zipPath)) {
                throw new ScormPackageException(ApiErrorCode::InvalidZip, 'Файл не является корректным ZIP-архивом');
            }
            if (!$this->zipExtractor->hasManifest($zipPath)) {
                throw new ScormPackageException(ApiErrorCode::ManifestMissing, 'Некорректный SCORM-пакет: imsmanifest.xml не найден в корне архива');
            }

            $this->zipExtractor->extractSafely($zipPath, $extractPath);

            try {
                $manifest = $this->manifestParser->parse($extractPath . '/imsmanifest.xml');
            } catch (RuntimeException) {
                throw new ScormPackageException(ApiErrorCode::InvalidZip, 'Некорректный SCORM-пакет: не удалось разобрать imsmanifest.xml');
            }

            $schemaVersion = trim((string)($manifest['metadata']['schemaversion'] ?? ''));
            if ($schemaVersion !== '' && !preg_match('/^1\.2$|^1\.3$|2004|CAM 1\.3/i', $schemaVersion)) {
                throw new ScormPackageException(ApiErrorCode::UnsupportedVersion, 'Неподдерживаемая версия SCORM: ' . mb_substr($schemaVersion, 0, 50));
            }

            $scos = $this->manifestParser->extractScos();
            if (!$scos) {
                throw new ScormPackageException(ApiErrorCode::NoLaunchResource, 'В пакете нет запускаемого SCO');
            }
            if (count($scos) > 1) {
                throw new ScormPackageException(ApiErrorCode::MultipleSco, 'Пакеты с несколькими SCO не поддерживаются');
            }

            $prefix = Helper::generatePrefix();
            $this->uploadDirectory($extractPath, $prefix);

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $package->setAttributes([
                    'size' => filesize($zipPath),
                    'extracted_path' => $prefix,
                    'identifier' => $manifest['identifier'],
                    'version' => $manifest['version'],
                    'title' => mb_substr($manifest['title'] ?? $package->title, 0, 255),
                    'launch_url' => $manifest['launchData']['launchUrl'] ?? null,
                    'metadata' => array_merge($manifest['metadata'] ?? [], ['toc' => $this->manifestParser->buildToc()]),
                    'status' => ScormPackage::STATUS_READY,
                ], false);
                if (!$package->save()) {
                    throw new RuntimeException('Не удалось сохранить пакет: ' . json_encode($package->getFirstErrors()));
                }
                $this->createScoRecords($package, $scos);
                $transaction->commit();
            } catch (Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

            return $package;
        } catch (Throwable $e) {
            Yii::$app->storage->deletePrefix($prefix);

            throw $e;
        }
    }

    private function uploadDirectory(string $directory, string $prefix): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $root = strlen($directory) + 1;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), $root));
            $key = Helper::objectKeyPrefix($prefix, $relativePath);
            if (!$key) {
                throw new RuntimeException('Недопустимое имя файла в пакете');
            }

            $extension = strtolower($file->getExtension());
            Yii::$app->storage->upload(
                $file->getPathname(),
                $key,
                ContentFileResponder::MIME_TYPES[$extension] ?? 'application/octet-stream'
            );
        }
    }

    /**
     * @throws Exception
     */
    private function createRecord(string $fileName, ?ScormClient $client, ?int $userId): ScormPackage
    {
        $package = new ScormPackage([
            'title' => mb_substr(pathinfo($fileName, PATHINFO_FILENAME), 0, 255),
            'original_filename' => mb_substr($fileName, 0, 255),
            'status' => ScormPackage::STATUS_PROCESSING,
            'created_by' => $userId,
            'client_id' => $client?->id,
        ]);
        if (!$package->save()) {
            throw new RuntimeException('Не удалось сохранить пакет: ' . json_encode($package->getFirstErrors()));
        }

        return $package;
    }

    private function zipPath(ScormPackage $package): string
    {
        return $this->ensureDirectory(Yii::$app->params['scormTempPath']) . '/' . $package->id . '.zip';
    }

    private function cleanupTemp(ScormPackage $package): void
    {
        $base = rtrim(Yii::$app->params['scormTempPath'], '/') . '/' . $package->id;
        @unlink($base . '.zip');
        $this->deleteDirectory($base);
    }

    private function maxPackageSize(): int
    {
        return (int)Yii::$app->params['maxPackageSize'];
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->hasError) {
            throw new RuntimeException('Не удалось загрузить файл');
        }
        if ($file->size > $this->maxPackageSize()) {
            throw new ScormPackageException(ApiErrorCode::TooLarge, 'Файл слишком большой. Максимальный размер: ' . round($this->maxPackageSize() / 1024 / 1024) . ' МБ');
        }
        if (strtolower($file->extension) !== 'zip') {
            throw new RuntimeException('Допускаются только ZIP-файлы');
        }
    }

    private function createScoRecords(ScormPackage $package, array $scos): void
    {
        $models = [];

        foreach ($scos as $index => $data) {
            $sco = new ScormSco([
                'package_id' => $package->id,
                'identifier' => $data['identifier'],
                'title' => $data['title'],
                'launch_url' => $data['launchUrl'],
                'scorm_type' => $data['scormType'],
                'parameters' => $data['parameters'],
                'prerequisites' => $data['prerequisites'],
                'mastery_score' => $data['masteryScore'],
                'objective_ids' => $data['objectives'],
                'scaled_passing_score' => $data['scaledPassingScore'],
                'max_time_allowed' => $data['maxTimeAllowed'],
                'time_limit_action' => $data['timeLimitAction'],
                'sort_order' => $index,
                'parent_id' => isset($models[$data['parentIndex']]) ? $models[$data['parentIndex']]->id : null,
            ]);

            if (!$sco->save()) {
                throw new RuntimeException('Не удалось сохранить SCO: ' . json_encode($sco->getFirstErrors()));
            }

            $models[$index] = $sco;
        }
    }

    private function ensureDirectory(string $path): string
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new RuntimeException('Не удалось создать каталог хранилища');
        }

        return rtrim($path, '/');
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
