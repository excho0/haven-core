<?php

namespace HavenCore\WooCommerce\Admin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Auto-load all class-*.php files in this directory except this one
foreach (glob(__DIR__ . '/class-*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}

use HavenCore\WooCommerce\Admin\Orders;
use HavenCore\WooCommerce\Admin\Products;


/**
 * Class AdminManager
 *
 * Central registry for all admin functionality within the plugin.
 * This class is responsible for registering all admin-related hooks
 * in one centralized place.
 *
 * Usage:
 * Call AdminManager::registerAll() to hook all admin functionality.
 *
 * @package HavenCore\Admin
 */
class AdminManager
{
    /**
     * Register all admin handlers used by the plugin.
     *
     * This method should be called during the admin lifecycle.
     * Typically, you'd call this function during the `admin_init` hook.
     *
     * Example:
     * add_action('admin_init', [AdminManager::class, 'registerAll']);
     *
     * @return void
     */
    public static function registerAll(): void
    {
        // Register all admin classes here
        Orders::register(); // Register order-related admin functionality
        Products::register(); // Register product-related admin functionality

        // In the future, you can register additional admin handlers:
        // AnotherAdminClass::register();
    }
}
