<?php

use yii\db\Migration;

class m240101_000005_create_scorm_launch_tokens_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('scorm_launch_tokens', [
            'id' => $this->primaryKey()->unsigned(),
            'token' => $this->string(64)->notNull(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'attempt_id' => $this->integer()->unsigned()->notNull(),
            'expires_at' => $this->timestamp()->notNull(),
            'used_at' => $this->timestamp()->null(),
            'ip_address' => $this->string(45)->null(),
            'is_active' => $this->tinyInteger(1)->defaultValue(1),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('uk_token', 'scorm_launch_tokens', 'token', true);
        $this->createIndex('idx_expires', 'scorm_launch_tokens', 'expires_at');
        $this->createIndex('idx_user_attempt', 'scorm_launch_tokens', ['user_id', 'attempt_id']);

        $this->addForeignKey(
            'fk_tokens_attempt',
            'scorm_launch_tokens', 'attempt_id',
            'scorm_attempts', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('scorm_launch_tokens');
    }
}
