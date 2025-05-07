<?php

namespace HavenCore\WooCommerce\Ajax;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Auto-load all class-*.php files in this directory except this one
foreach (glob(__DIR__ . '/class-*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}

// use HavenCore\WooCommerce\Ajax\CustomerCheckoutAjax;

/**
 * Class Woo_AjaxManager
 *
 * Central registry for all AJAX handlers related to WooCommerce in the plugin.
 * This class is responsible for registering all AJAX-related hooks in a single location.
 * It ensures that all AJAX endpoints are hooked correctly and consistently.
 *
 * Usage:
 * Call Woo_AjaxManager::registerAll() to hook all AJAX endpoints.
 *
 * @package HavenCore\WooCommerce\Ajax
 */
class Woo_AjaxManager
{
    /**
     * Register all AJAX handlers for the plugin.
     *
     * This method should be called during the AJAX lifecycle,
     * typically inside a DOING_AJAX check (e.g., in functions.php).
     *
     * Example:
     * if (defined('DOING_AJAX') && DOING_AJAX) {
     *     Woo_AjaxManager::registerAll();
     * }
     *
     * @return void
     */
    public static function registerAll(): void
    {
        // CustomerCheckoutAjax::register();

    }
}
