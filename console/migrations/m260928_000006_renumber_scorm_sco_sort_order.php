<?php

use yii\db\Migration;
use yii\db\Query;

class m260928_000006_renumber_scorm_sco_sort_order extends Migration
{
    public function safeUp()
    {
        $packageIds = (new Query())->select('package_id')->distinct()->from('scorm_scos')->column();

        foreach ($packageIds as $packageId) {
            $scoIds = (new Query())
                ->select('id')
                ->from('scorm_scos')
                ->where(['package_id' => $packageId])
                ->orderBy(['id' => SORT_ASC])
                ->column();

            foreach ($scoIds as $index => $scoId) {
                $this->update('scorm_scos', ['sort_order' => $index], ['id' => $scoId]);
            }
        }
    }

    public function safeDown()
    {
        return true;
    }
}
