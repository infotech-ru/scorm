<?php

use yii\db\Migration;

class m261102_084541_alter_table_scorm_launch_tokens extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn('scorm_launch_tokens', 'user_id', $this->integer()->unsigned()->null());
        $this->alterColumn('scorm_launch_tokens', 'mode', "ENUM('normal', 'review', 'preview') NOT NULL DEFAULT 'normal'");
        $this->addColumn('scorm_launch_tokens', 'client_id', $this->integer()->unsigned()->null());
        $this->createIndex('idx_scorm_launch_tokens_client', 'scorm_launch_tokens', 'client_id');
        $this->addForeignKey(
            'fk_scorm_launch_tokens_client',
            'scorm_launch_tokens',
            'client_id',
            'scorm_clients',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_scorm_launch_tokens_client', 'scorm_launch_tokens');
        $this->dropIndex('idx_scorm_launch_tokens_client', 'scorm_launch_tokens');
        $this->dropColumn('scorm_launch_tokens', 'client_id');
        $this->update('scorm_launch_tokens', ['mode' => 'normal', 'is_active' => 0], ['mode' => 'preview']);
        $this->alterColumn('scorm_launch_tokens', 'mode', "ENUM('normal', 'review') NOT NULL DEFAULT 'normal'");
        $this->update('scorm_launch_tokens', ['user_id' => 0], ['user_id' => null]);
        $this->alterColumn('scorm_launch_tokens', 'user_id', $this->integer()->unsigned()->notNull());
    }
}
