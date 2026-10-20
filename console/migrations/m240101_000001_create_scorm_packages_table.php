<?php

use yii\db\Migration;

class m240101_000001_create_scorm_packages_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('scorm_packages', [
            'id' => $this->primaryKey()->unsigned(),
            'title' => $this->string(255)->notNull(),
            'identifier' => $this->string(255)->null(),
            'version' => "ENUM('1.2', '2004') NOT NULL DEFAULT '1.2'",
            'scorm_version_detail' => $this->string(50)->null(),
            'original_filename' => $this->string(255)->notNull(),
            'storage_path' => $this->string(500)->notNull(),
            'extracted_path' => $this->string(500)->null(),
            'launch_url' => $this->string(500)->null(),
            'metadata' => $this->json()->null(),
            'status' => "ENUM('uploading', 'processing', 'ready', 'error') NOT NULL DEFAULT 'uploading'",
            'error_message' => $this->text()->null(),
            'created_by' => $this->integer()->unsigned()->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_status', 'scorm_packages', 'status');
        $this->createIndex('idx_created_by', 'scorm_packages', 'created_by');
        $this->createIndex('idx_version', 'scorm_packages', 'version');
    }

    public function safeDown()
    {
        $this->dropTable('scorm_packages');
    }
}
