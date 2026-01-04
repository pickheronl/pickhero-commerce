<?php

namespace pickhero\commerce\migrations;

use craft\db\Migration;

/**
 * Adds submissionCount column to track order resubmissions
 */
class m250104_000000_add_submission_count extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%pickhero_order_sync}}')) {
            return true;
        }
        
        $schema = $this->db->getTableSchema('{{%pickhero_order_sync}}');
        
        if ($schema !== null && $schema->getColumn('submissionCount') === null) {
            $this->addColumn(
                '{{%pickhero_order_sync}}',
                'submissionCount',
                $this->integer()->notNull()->defaultValue(0)->after('processed')
            );
        }
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if (!$this->db->tableExists('{{%pickhero_order_sync}}')) {
            return true;
        }
        
        $schema = $this->db->getTableSchema('{{%pickhero_order_sync}}');
        
        if ($schema !== null && $schema->getColumn('submissionCount') !== null) {
            $this->dropColumn('{{%pickhero_order_sync}}', 'submissionCount');
        }
        
        return true;
    }
}

