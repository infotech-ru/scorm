<?php

use common\services\ScormManifestParser;
use yii\db\Migration;
use yii\db\Query;

class m260928_000007_redetect_scorm_package_version extends Migration
{
    public function safeUp()
    {
        $packages = (new Query())
            ->select(['id', 'version', 'extracted_path'])
            ->from('scorm_packages')
            ->where(['not', ['extracted_path' => null]])
            ->all();

        foreach ($packages as $package) {
            $manifestPath = $package['extracted_path'] . DIRECTORY_SEPARATOR . 'imsmanifest.xml';
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $version = (new ScormManifestParser())->parse($manifestPath)['version'];
            } catch (\Exception $e) {
                echo "    > package {$package['id']}: {$e->getMessage()}\n";
                continue;
            }

            if ($version !== $package['version']) {
                $this->update('scorm_packages', ['version' => $version], ['id' => $package['id']]);
            }
        }
    }

    public function safeDown()
    {
        return true;
    }
}
