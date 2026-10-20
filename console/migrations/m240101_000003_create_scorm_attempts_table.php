<?php

use yii\db\Migration;

class m240101_000003_create_scorm_attempts_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('scorm_attempts', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'package_id' => $this->integer()->unsigned()->notNull(),
            'sco_id' => $this->integer()->unsigned()->notNull(),
            'attempt_number' => $this->integer()->unsigned()->defaultValue(1),

            'lesson_status' => "ENUM('passed', 'completed', 'failed', 'incomplete', 'browsed', 'not attempted') DEFAULT 'not attempted'",
            'lesson_location' => $this->string(255)->null(),
            'score_raw' => $this->decimal(10, 2)->null(),
            'score_min' => $this->decimal(10, 2)->null(),
            'score_max' => $this->decimal(10, 2)->null(),
            'score_scaled' => $this->decimal(5, 4)->null(),
            'total_time' => $this->string(50)->null(),
            'session_time' => $this->string(50)->null(),

            'completion_status' => "ENUM('completed', 'incomplete', 'not attempted', 'unknown') NULL",
            'success_status' => "ENUM('passed', 'failed', 'unknown') NULL",
            'progress_measure' => $this->decimal(5, 4)->null(),

            'suspend_data' => $this->text()->null(),
            'entry' => "ENUM('ab-initio', 'resume', '') DEFAULT 'ab-initio'",
            'exit_type' => "ENUM('timeout', 'suspend', 'logout', 'normal', '') NULL",

            'started_at' => $this->timestamp()->null(),
            'completed_at' => $this->timestamp()->null(),
            'last_accessed_at' => $this->timestamp()->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_user_package', 'scorm_attempts', ['user_id', 'package_id']);
        $this->createIndex('idx_user_sco', 'scorm_attempts', ['user_id', 'sco_id']);
        $this->createIndex('idx_lesson_status', 'scorm_attempts', 'lesson_status');
        $this->createIndex('uk_user_sco_attempt', 'scorm_attempts', ['user_id', 'sco_id', 'attempt_number'], true);

        $this->addForeignKey(
            'fk_attempts_package',
            'scorm_attempts', 'package_id',
            'scorm_packages', 'id',
            'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            'fk_attempts_sco',
            'scorm_attempts', 'sco_id',
            'scorm_scos', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('scorm_attempts');
    }
}
