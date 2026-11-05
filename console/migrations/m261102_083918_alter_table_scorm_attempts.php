<?php

use yii\db\Migration;

class m261102_083918_alter_table_scorm_attempts extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('scorm_attempts', 'client_id', $this->integer()->unsigned()->null());
        $this->createIndex('idx_scorm_attempts_client', 'scorm_attempts', 'client_id');
        $this->addForeignKey(
            'fk_scorm_attempts_client',
            'scorm_attempts', 'client_id',
            'scorm_clients', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addColumn('scorm_attempts', 'registration_key', $this->string(64)->null());
        $this->addColumn('scorm_attempts', 'learner_id', $this->string(64)->null());
        $this->addColumn('scorm_attempts', 'learner_name', $this->string(255)->null());
        $this->addColumn('scorm_attempts', 'course_ref', $this->string(64)->null());
        $this->addColumn('scorm_attempts', 'is_preview', $this->tinyInteger(1)->notNull()->defaultValue(0));
        $this->createIndex('idx_client_package_registration', 'scorm_attempts', ['client_id', 'package_id', 'registration_key']);
        $this->alterColumn('scorm_attempts', 'user_id', $this->integer()->unsigned()->null());
        $this->addColumn('scorm_attempts', 'callback_version', $this->integer()->unsigned()->notNull()->defaultValue(0));
    }

    public function safeDown(): void
    {
        $this->update('scorm_attempts', ['user_id' => 0], ['user_id' => null]);
        $this->alterColumn('scorm_attempts', 'user_id', $this->integer()->unsigned()->notNull());
        $this->dropIndex('idx_client_package_registration', 'scorm_attempts');
        $this->dropColumn('scorm_attempts', 'is_preview');
        $this->dropColumn('scorm_attempts', 'course_ref');
        $this->dropColumn('scorm_attempts', 'learner_name');
        $this->dropColumn('scorm_attempts', 'learner_id');
        $this->dropColumn('scorm_attempts', 'registration_key');
        $this->dropColumn('scorm_attempts', 'callback_version');
        $this->dropForeignKey('fk_scorm_attempts_client', 'scorm_attempts');
        $this->dropIndex('idx_scorm_attempts_client', 'scorm_attempts');
        $this->dropColumn('scorm_attempts', 'client_id');
    }
}
