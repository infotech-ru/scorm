<?php

use yii\db\Migration;

class m261102_083958_create_table_scorm_queue extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('scorm_queue', [
            'id' => $this->primaryKey(),
            'channel' => $this->string()->notNull(),
            'job' => $this->binary()->notNull(),
            'pushed_at' => $this->integer()->notNull(),
            'ttr' => $this->integer()->notNull(),
            'delay' => $this->integer()->notNull()->defaultValue(0),
            'priority' => $this->integer()->unsigned()->notNull()->defaultValue(1024),
            'reserved_at' => $this->integer(),
            'attempt' => $this->integer(),
            'done_at' => $this->integer(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_channel', 'scorm_queue', 'channel');
        $this->createIndex('idx_reserved_at', 'scorm_queue', 'reserved_at');
        $this->createIndex('idx_priority', 'scorm_queue', 'priority');

    }

    public function safeDown(): void
    {
        $this->dropTable('scorm_queue');
    }
}
