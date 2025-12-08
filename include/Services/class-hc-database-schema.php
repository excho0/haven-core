<?php

namespace HavenCore\Services;

defined('ABSPATH') || exit;

/**
 * Central place for installing and upgrading HavenCore's custom database tables.
 */
class HC_Database_Schema
{
    private const OPTION_KEY = 'havencore_db_version';

    /**
     * Ensures the schema is installed/updated when the plugin activates.
     */
    public static function install(): void
    {
        self::runMigrations();
    }

    /**
     * Triggers schema updates when the stored version is out of date.
     */
    public static function maybeUpdate(): void
    {
        $current = get_option(self::OPTION_KEY);
        if (self::schemaVersion() === $current) {
            return;
        }

        self::runMigrations();
    }

    /**
     * Returns a fully-qualified table name using the plugin prefix.
     */
    public static function table(string $suffix): string
    {
        global $wpdb;
        $base = $wpdb instanceof \wpdb ? $wpdb->prefix : 'wp_';

        return $base . HAVEN_CORE_DB_PREFIX . $suffix;
    }

    /**
     * Drops plugin tables and removes the stored version flag.
     */
    public static function uninstall(): void
    {
        global $wpdb;

        if (!($wpdb instanceof \wpdb)) {
            return;
        }

        foreach (['message_attachments', 'messages', 'conversations'] as $suffix) {
            $table = self::table($suffix);
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::OPTION_KEY);
    }

    private static function runMigrations(): void
    {
        global $wpdb;

        if (!($wpdb instanceof \wpdb)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $conversationTable = self::table('conversations');
        $messagesTable = self::table('messages');
        $attachmentsTable = self::table('message_attachments');

        $queries = [];

        $queries[] = "CREATE TABLE {$conversationTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            supplier_id bigint(20) unsigned NOT NULL,
            admin_user_id bigint(20) unsigned NULL,
            order_id bigint(20) unsigned NULL,
            subject varchar(255) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'open',
            last_message_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unread_admin_count smallint(5) unsigned NOT NULL DEFAULT 0,
            unread_supplier_count smallint(5) unsigned NOT NULL DEFAULT 0,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY supplier_status (supplier_id, status),
            KEY order_lookup (order_id)
        ) {$charsetCollate};";

        $queries[] = "CREATE TABLE {$messagesTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_type varchar(20) NOT NULL,
            sender_id bigint(20) unsigned NULL,
            message longtext NOT NULL,
            attachments longtext NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY conversation_lookup (conversation_id),
            KEY sender_lookup (sender_type)
        ) {$charsetCollate};";

        $queries[] = "CREATE TABLE {$attachmentsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            file_url text NOT NULL,
            file_name varchar(255) NOT NULL DEFAULT '',
            mime_type varchar(100) NOT NULL DEFAULT '',
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY attachment_message_lookup (message_id)
        ) {$charsetCollate};";

        foreach ($queries as $sql) {
            dbDelta($sql);
        }

        update_option(self::OPTION_KEY, self::schemaVersion());
    }

    private static function schemaVersion(): string
    {
        if (defined('HAVEN_CORE_VERSION') && HAVEN_CORE_VERSION) {
            return HAVEN_CORE_VERSION;
        }

        return '0.0.0';
    }
}
