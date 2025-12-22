<?php

namespace pickhero\commerce\migrations;

use craft\db\Migration;

/**
 * Migration to v2.0 - Renames tables and columns to match new PickHero API
 */
class m241222_000000_migrate_to_v2 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Migrate order sync table
        if ($this->db->tableExists('{{%commercepickhero_ordersyncstatus}}')) {
            // Rename old table to new name
            $this->renameTable('{{%commercepickhero_ordersyncstatus}}', '{{%pickhero_order_sync}}');
        }
        
        // Migrate webhooks table
        if ($this->db->tableExists('{{%commercepickhero_webhooks}}')) {
            // Check if old column exists and rename
            $schema = $this->db->getTableSchema('{{%commercepickhero_webhooks}}');
            
            if ($schema !== null && $schema->getColumn('pickheroHookId') !== null) {
                $this->renameColumn('{{%commercepickhero_webhooks}}', 'pickheroHookId', 'pickheroWebhookId');
            }
            
            // Rename table
            $this->renameTable('{{%commercepickhero_webhooks}}', '{{%pickhero_webhooks}}');
        }
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Reverse the table renames
        if ($this->db->tableExists('{{%pickhero_order_sync}}')) {
            $this->renameTable('{{%pickhero_order_sync}}', '{{%commercepickhero_ordersyncstatus}}');
        }
        
        if ($this->db->tableExists('{{%pickhero_webhooks}}')) {
            $schema = $this->db->getTableSchema('{{%pickhero_webhooks}}');
            
            if ($schema !== null && $schema->getColumn('pickheroWebhookId') !== null) {
                $this->renameColumn('{{%pickhero_webhooks}}', 'pickheroWebhookId', 'pickheroHookId');
            }
            
            $this->renameTable('{{%pickhero_webhooks}}', '{{%commercepickhero_webhooks}}');
        }
        
        return true;
    }
}

