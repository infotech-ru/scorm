<?php

use yii\db\Migration;

class m240101_000002_create_scorm_scos_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('scorm_scos', [
            'id' => $this->primaryKey()->unsigned(),
            'package_id' => $this->integer()->unsigned()->notNull(),
            'identifier' => $this->string(255)->notNull(),
            'title' => $this->string(255)->null(),
            'launch_url' => $this->string(500)->notNull(),
            'scorm_type' => "ENUM('sco', 'asset') NOT NULL DEFAULT 'sco'",
            'parameters' => $this->string(1000)->null(),
            'prerequisites' => $this->string(500)->null(),
            'mastery_score' => $this->decimal(5, 2)->null(),
            'max_time_allowed' => $this->string(50)->null(),
            'time_limit_action' => $this->string(50)->null(),
            'sort_order' => $this->integer()->defaultValue(0),
            'parent_id' => $this->integer()->unsigned()->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_package_id', 'scorm_scos', 'package_id');
        $this->createIndex('idx_parent_id', 'scorm_scos', 'parent_id');

        $this->addForeignKey(
            'fk_scos_package',
            'scorm_scos', 'package_id',
            'scorm_packages', 'id',
            'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            'fk_scos_parent',
            'scorm_scos', 'parent_id',
            'scorm_scos', 'id',
            'SET NULL', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('scorm_scos');
    }
}
