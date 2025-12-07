<?php

$pluginRoot = __DIR__;
$wordpressRoot = dirname($pluginRoot, 3);

if (!defined('ABSPATH')) {
    require_once $wordpressRoot . '/wp-load.php';
}

global $wpdb;

$tablePrefix = isset($wpdb->prefix) ? $wpdb->prefix : 'wp_';
$defaultCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
$defaultCollation = defined('DB_COLLATE') && DB_COLLATE ? DB_COLLATE : 'utf8mb4_unicode_ci';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$port = null;

if (false !== strpos($host, ':') && false === strpos($host, ']')) {
    $segments = explode(':', $host);
    $maybePort = array_pop($segments);
    if (is_numeric($maybePort)) {
        $port = (int) $maybePort;
        $host = implode(':', $segments);
    } else {
        $segments[] = $maybePort;
        $host = implode(':', $segments);
    }
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => $tablePrefix . 'hc_migrations',
        'default_environment' => 'wordpress',
        'wordpress' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => defined('DB_NAME') ? DB_NAME : '',
            'user' => defined('DB_USER') ? DB_USER : '',
            'pass' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'port' => (false !== strpos($host, '/')) ? null : ($port ?? '3306'),
            'charset' => $defaultCharset,
            'collation' => $defaultCollation,
            'table_prefix' => $tablePrefix,
        ],
    ],
    'version_order' => 'creation',
];
