<?php

namespace common\services;

use common\models\ScormPackage;
use common\models\ScormSco;
use RuntimeException;
use Yii;
use yii\web\UploadedFile;

class ScormPackageService
{
    private ZipExtractor $zipExtractor;
    private ScormManifestParser $manifestParser;

    public function __construct()
    {
        $params = Yii::$app->params;
        $this->zipExtractor = new ZipExtractor($params['maxPackageFiles'], $params['maxExtractedSize']);
        $this->manifestParser = new ScormManifestParser();
    }

    public function processUpload(UploadedFile $file, ?int $userId = null): ScormPackage
    {
        $this->validateFile($file);

        $package = new ScormPackage([
            'title' => mb_substr(pathinfo($file->name, PATHINFO_FILENAME), 0, 255),
            'original_filename' => mb_substr($file->name, 0, 255),
            'storage_path' => '',
            'status' => ScormPackage::STATUS_PROCESSING,
            'created_by' => $userId,
        ]);
        if (!$package->save()) {
            throw new RuntimeException('Failed to save package: ' . json_encode($package->getFirstErrors()));
        }

        $zipPath = $this->ensureDirectory(Yii::$app->params['scormStoragePath']) . '/' . $package->id . '.zip';
        $extractPath = $this->ensureDirectory(Yii::$app->params['scormContentPath']) . '/' . $package->id;

        try {
            if (!$file->saveAs($zipPath)) {
                throw new RuntimeException('Failed to store uploaded file');
            }
            if (!$this->zipExtractor->hasManifest($zipPath)) {
                throw new RuntimeException('Invalid SCORM package: imsmanifest.xml not found in the archive root');
            }

            $this->zipExtractor->extractSafely($zipPath, $extractPath);
            $manifest = $this->manifestParser->parse($extractPath . '/imsmanifest.xml');
            $scos = $this->manifestParser->extractScos();
            if (!$scos) {
                throw new RuntimeException('Package has no launchable SCO');
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $package->setAttributes([
                    'storage_path' => $zipPath,
                    'extracted_path' => $extractPath,
                    'identifier' => $manifest['identifier'],
                    'version' => $manifest['version'],
                    'title' => mb_substr($manifest['title'] ?? $package->title, 0, 255),
                    'launch_url' => $manifest['launchData']['launchUrl'] ?? null,
                    'metadata' => array_merge($manifest['metadata'] ?? [], ['toc' => $this->manifestParser->buildToc()]),
                    'status' => ScormPackage::STATUS_READY,
                ], false);
                if (!$package->save()) {
                    throw new RuntimeException('Failed to save package: ' . json_encode($package->getFirstErrors()));
                }
                $this->createScoRecords($package, $scos);
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

            return $package;
        } catch (\Throwable $e) {
            $this->deleteDirectory($extractPath);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }

            $package->updateAttributes([
                'status' => ScormPackage::STATUS_ERROR,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'extracted_path' => null,
            ]);

            throw $e;
        }
    }

    public function deletePackage(ScormPackage $package): void
    {
        if ($package->extracted_path) {
            $this->deleteDirectory($package->extracted_path);
        }

        if ($package->storage_path && is_file($package->storage_path)) {
            unlink($package->storage_path);
        }

        if ($package->delete() === false) {
            throw new RuntimeException('Failed to delete package');
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        $maxSize = Yii::$app->params['maxPackageSize'];

        if ($file->hasError) {
            throw new RuntimeException('Upload failed');
        }
        if ($file->size > $maxSize) {
            throw new RuntimeException('File too large. Maximum size: ' . round($maxSize / 1024 / 1024) . 'MB');
        }
        if (strtolower($file->extension) !== 'zip') {
            throw new RuntimeException('Only ZIP files are allowed');
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
                throw new RuntimeException('Failed to save SCO: ' . json_encode($sco->getFirstErrors()));
            }

            $models[$index] = $sco;
        }
    }

    private function ensureDirectory(string $path): string
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new RuntimeException('Cannot create storage directory');
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
