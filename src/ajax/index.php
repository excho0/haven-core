<?php

namespace HavenCore\Ajax;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Auto-load all class-*.php files in this directory except this one
foreach (glob(__DIR__ . '/class-*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}

// use HavenCore\Ajax\SupplierManagerAjax;
// use HavenCore\Ajax\SettingsAjax;
// use HavenCore\Ajax\SupplierPortalAjax;

/**
 * Class AjaxManager
 *
 * Central registry for all AJAX handlers within the plugin.
 * This class is responsible for registering all AJAX-related hooks
 * in one centralized place.
 *
 * Usage:
 * Call AjaxManager::registerAll() to hook all AJAX endpoints.
 *
 * @package HavenCore\Ajax
 */
class AjaxManager
{
    /**
     * Register all AJAX handlers used by the plugin.
     *
     * This method should be called during the admin AJAX lifecycle,
     * typically inside a DOING_AJAX check.
     *
     * Example:
     * if (defined('DOING_AJAX') && DOING_AJAX) {
     *     AjaxManager::registerAll();
     * }
     *
     * @return void
     */
    public static function registerAll(): void
    {
        // SupplierManagerAjax::register();

        // SettingsAjax::register();

        // SupplierPortalAjax::register();

        // Add future handlers below...
        // ExampleHandler::register();
    }
}
