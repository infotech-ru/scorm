<?php

use yii\db\Migration;

class m240101_000004_create_scorm_tracking_data_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('scorm_tracking_data', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'attempt_id' => $this->integer()->unsigned()->notNull(),
            'element' => $this->string(255)->notNull(),
            'value' => $this->text()->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_attempt_id', 'scorm_tracking_data', 'attempt_id');
        $this->createIndex('idx_element', 'scorm_tracking_data', 'element');
        $this->createIndex('uk_attempt_element', 'scorm_tracking_data', ['attempt_id', 'element(191)'], true);

        $this->addForeignKey(
            'fk_tracking_attempt',
            'scorm_tracking_data', 'attempt_id',
            'scorm_attempts', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('scorm_tracking_data');
    }
}
