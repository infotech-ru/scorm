<?php

use yii\db\Migration;

class m261102_083846_create_scorm_clients_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('scorm_clients', [
            'id' => $this->primaryKey()->unsigned(),
            'name' => $this->string(255)->notNull(),
            'api_key_hash' => $this->string(64)->notNull(),
            'callback_url' => $this->string(1024)->notNull(),
            'callback_secret' => $this->string(128)->notNull(),
            'origin' => $this->string(255)->notNull(),
            'created_at' => $this->timestamp(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('uk_api_key_hash', 'scorm_clients', 'api_key_hash', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('scorm_clients');
    }
}
