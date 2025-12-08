<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateHcSupplierMessagingTables extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('hc_conversations')) {
            $this->table('hc_conversations', [
                'id' => 'id',
                'signed' => false,
            ])
                ->addColumn('supplier_id', 'biginteger', ['signed' => false])
                ->addColumn('admin_user_id', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('order_id', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('subject', 'string', ['limit' => 255, 'default' => ''])
                ->addColumn('status', 'string', ['limit' => 32, 'default' => 'open'])
                ->addColumn('last_message_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('unread_admin_count', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('unread_supplier_count', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('meta', 'text', ['null' => true])
                ->addTimestamps()
                ->addIndex(['supplier_id', 'status'], ['name' => 'supplier_status'])
                ->addIndex(['order_id'], ['name' => 'order_lookup'])
                ->create();
        }

        if (!$this->hasTable('hc_messages')) {
            $this->table('hc_messages', [
                'id' => 'id',
                'signed' => false,
            ])
                ->addColumn('conversation_id', 'biginteger', ['signed' => false])
                ->addColumn('sender_type', 'string', ['limit' => 20])
                ->addColumn('sender_id', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('message', 'text')
                ->addColumn('attachments', 'text', ['null' => true])
                ->addColumn('is_read', 'boolean', ['default' => false])
                ->addTimestamps()
                ->addIndex(['conversation_id'], ['name' => 'conversation_lookup'])
                ->addIndex(['sender_type'], ['name' => 'sender_lookup'])
                ->create();
        }

        if (!$this->hasTable('hc_message_attachments')) {
            $this->table('hc_message_attachments', [
                'id' => 'id',
                'signed' => false,
            ])
                ->addColumn('message_id', 'biginteger', ['signed' => false])
                ->addColumn('file_url', 'text')
                ->addColumn('file_name', 'string', ['limit' => 255, 'default' => ''])
                ->addColumn('mime_type', 'string', ['limit' => 100, 'default' => ''])
                ->addColumn('meta', 'text', ['null' => true])
                ->addTimestamps()
                ->addIndex(['message_id'], ['name' => 'attachment_message_lookup'])
                ->create();
        }
    }

    public function down(): void
    {
        foreach (['hc_message_attachments', 'hc_messages', 'hc_conversations'] as $table) {
            if ($this->hasTable($table)) {
                $this->table($table)->drop()->save();
            }
        }
    }
}
