<?php

namespace pickhero\commerce\migrations;

use craft\db\Migration;

/**
 * Installation migration for PickHero plugin
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createOrderSyncTable();
        $this->createWebhooksTable();
        
        return true;
    }

    /**
     * Create the order synchronization tracking table
     */
    protected function createOrderSyncTable(): void
    {
        if ($this->db->tableExists('{{%pickhero_order_sync}}')) {
            return;
        }
        
        $this->createTable('{{%pickhero_order_sync}}', [
            'id' => $this->bigPrimaryKey(),
            'orderId' => $this->integer()->notNull(),
            'pickheroOrderId' => $this->bigInteger(),
            'pushed' => $this->boolean()->notNull()->defaultValue(false),
            'stockAllocated' => $this->boolean()->notNull()->defaultValue(false),
            'processed' => $this->boolean()->notNull()->defaultValue(false),
            'pickheroOrderNumber' => $this->string(),
            'publicStatusPage' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'dateDeleted' => $this->dateTime()->null(),
            'uid' => $this->uid(),
        ]);
        
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            '{{%pickhero_order_sync}}',
            'orderId',
            '{{%commerce_orders}}',
            'id',
            'CASCADE',
            null
        );
        
        $this->createIndex(null, '{{%pickhero_order_sync}}', ['orderId'], true);
    }

    /**
     * Create the webhook registrations table
     */
    protected function createWebhooksTable(): void
    {
        if ($this->db->tableExists('{{%pickhero_webhooks}}')) {
            return;
        }
        
        $this->createTable('{{%pickhero_webhooks}}', [
            'id' => $this->bigPrimaryKey(),
            'type' => $this->string(32)->notNull(),
            'pickheroWebhookId' => $this->bigInteger()->notNull(),
            'secret' => $this->string()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        
        $this->createIndex(null, '{{%pickhero_webhooks}}', ['type'], true);
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%pickhero_webhooks}}');
        $this->dropTableIfExists('{{%pickhero_order_sync}}');
        
        return true;
    }
}
