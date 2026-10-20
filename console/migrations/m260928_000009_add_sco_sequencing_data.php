<?php

use common\services\ScormManifestParser;
use yii\db\Migration;
use yii\db\Query;

class m260928_000009_add_sco_sequencing_data extends Migration
{
    public function safeUp()
    {
        $this->addColumn('scorm_scos', 'objective_ids', $this->json()->null()->after('mastery_score'));
        $this->addColumn('scorm_scos', 'scaled_passing_score', $this->decimal(5, 4)->null()->after('objective_ids'));

        $packages = (new Query())->select(['id', 'extracted_path'])->from('scorm_packages')->all();

        foreach ($packages as $package) {
            $manifest = $package['extracted_path'] . '/imsmanifest.xml';
            if (!$package['extracted_path'] || !is_file($manifest)) {
                continue;
            }

            $parser = new ScormManifestParser();
            $parser->parse($manifest);

            foreach ($parser->extractScos() as $sco) {
                $this->update('scorm_scos', [
                    'objective_ids' => $sco['objectives'],
                    'scaled_passing_score' => $sco['scaledPassingScore'],
                ], ['package_id' => $package['id'], 'identifier' => $sco['identifier']]);
            }
        }
    }

    public function safeDown()
    {
        $this->dropColumn('scorm_scos', 'scaled_passing_score');
        $this->dropColumn('scorm_scos', 'objective_ids');
    }
}
