<?php

use yii\db\Migration;

class m261102_083939_alter_table_scorm_packages extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('scorm_packages', 'size', $this->bigInteger()->unsigned()->null());
        $this->dropColumn('scorm_packages', 'storage_path');
        $this->addColumn('scorm_packages', 'client_id', $this->integer()->unsigned()->null());
        $this->createIndex('idx_scorm_packages_client', 'scorm_packages', 'client_id');
        $this->addForeignKey(
            'fk_scorm_packages_client',
            'scorm_packages', 'client_id',
            'scorm_clients', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->addColumn('scorm_packages', 'storage_path', $this->string(500)->notNull()->defaultValue(''));
        $this->dropColumn('scorm_packages', 'size');
        $this->dropForeignKey('fk_scorm_packages_client', 'scorm_packages');
        $this->dropIndex('idx_scorm_packages_client', 'scorm_packages');
        $this->dropColumn('scorm_packages', 'client_id');
    }
}
