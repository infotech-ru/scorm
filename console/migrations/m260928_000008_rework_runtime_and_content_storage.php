<?php

use common\services\ScormManifestParser;
use yii\db\Migration;
use yii\db\Query;

class m260928_000008_rework_runtime_and_content_storage extends Migration
{
    public function safeUp()
    {
        $attempts = $this->db->getTableSchema('scorm_attempts', true);

        if ($attempts->getColumn('total_time_seconds') === null) {
            $this->addColumn('scorm_attempts', 'total_time_seconds', $this->integer()->unsigned()->notNull()->defaultValue(0)->after('score_scaled'));
            foreach ((new Query())->select(['id', 'total_time'])->from('scorm_attempts')->each() as $row) {
                $this->update('scorm_attempts', ['total_time_seconds' => self::parseTime((string)$row['total_time'])], ['id' => $row['id']]);
            }
        }

        if ($attempts->getColumn('finished_at') === null) {
            $this->addColumn('scorm_attempts', 'finished_at', $this->timestamp()->null()->after('completed_at'));
            $this->execute(
                "UPDATE scorm_attempts SET finished_at = COALESCE(last_accessed_at, updated_at)
                 WHERE (exit_type IS NULL OR exit_type NOT IN ('suspend'))
                   AND (lesson_status IN ('passed', 'completed', 'failed')
                        OR completion_status = 'completed'
                        OR success_status IN ('passed', 'failed'))"
            );
        }

        $this->alterColumn('scorm_attempts', 'exit_type', "ENUM('timeout', 'time-out', 'suspend', 'logout', 'normal', '') NULL");
        $this->update('scorm_attempts', ['exit_type' => 'time-out'], ['exit_type' => 'timeout']);
        $this->alterColumn('scorm_attempts', 'exit_type', "ENUM('time-out', 'suspend', 'logout', 'normal', '') NULL");

        foreach (['total_time', 'session_time'] as $column) {
            if ($attempts->getColumn($column) !== null) {
                $this->dropColumn('scorm_attempts', $column);
            }
        }

        $tokens = $this->db->getTableSchema('scorm_launch_tokens', true);
        if ($tokens->getColumn('purpose') === null) {
            $this->addColumn('scorm_launch_tokens', 'purpose', "ENUM('launch', 'runtime') NOT NULL DEFAULT 'launch' AFTER `attempt_id`");
        }
        if ($tokens->getColumn('mode') === null) {
            $this->addColumn('scorm_launch_tokens', 'mode', "ENUM('normal', 'review') NOT NULL DEFAULT 'normal' AFTER `purpose`");
        }
        $this->update('scorm_launch_tokens', ['is_active' => 0]);

        $this->alterColumn('scorm_scos', 'prerequisites', $this->string(1000)->null());

        $this->moveExtractedPackages();
    }

    public function safeDown()
    {
        echo "m260928_000008 cannot be reverted.\n";
        return false;
    }

    private function moveExtractedPackages(): void
    {
        $root = dirname(__DIR__, 2);
        $target = $root . '/storage/scorm-content';
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $packages = (new Query())->select(['id', 'extracted_path', 'metadata'])->from('scorm_packages')->all();

        foreach ($packages as $package) {
            $newPath = $target . '/' . $package['id'];
            $oldPath = $package['extracted_path'];

            if ($oldPath && is_dir($oldPath) && realpath($oldPath) !== realpath($newPath)) {
                if (!rename($oldPath, $newPath)) {
                    throw new RuntimeException("Cannot move {$oldPath} to {$newPath}");
                }
            }

            $metadata = $package['metadata'];
            while (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }
            $metadata = is_array($metadata) ? $metadata : [];
            if (!isset($metadata['toc']) && is_file($newPath . '/imsmanifest.xml')) {
                $parser = new ScormManifestParser();
                $parser->parse($newPath . '/imsmanifest.xml');
                $metadata['toc'] = $parser->buildToc();
            }

            $this->update('scorm_packages', [
                'extracted_path' => is_dir($newPath) ? $newPath : null,
                'metadata' => $metadata,
            ], ['id' => $package['id']]);
        }
    }

    private static function parseTime(string $time): int
    {
        if (preg_match('/^(\d+):(\d{2}):(\d{2})(?:\.\d+)?$/', $time, $m)) {
            return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
        }

        return 0;
    }
}
