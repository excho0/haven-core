<?php

namespace HavenCore\WooCommerce;

require_once HAVEN_CORE_PATH . 'woocommerce/admin/index.php';
use HavenCore\WooCommerce\Admin\AdminManager;


// Auto-load all class-*.php files in the 'hooks' directory.
foreach (glob(__DIR__ . '/hooks/class-*.php') as $file) {
    require_once $file;
}

use HavenCore\WooCommerce\Hooks\Orders;
use HavenCore\WooCommerce\Hooks\Customer;

/**
 * Class WooCommerceBootstrap
 *
 * Master bootstrapper for all WooCommerce-related functionality in Haven Core.
 * Centralized entry point for initializing both admin and frontend logic.
 *
 * This should be invoked during plugin bootstrap (e.g., from haven-core.php),
 * and will load relevant logic based on the current request context.
 *
 * - Admin logic is initialized via AdminManager
 * - Frontend logic can be added directly in the frontend block
 *
 * @package HavenCore\WooCommerce
 */
class WooCommerceBootstrap
{
    /**
     * Bootstraps all WooCommerce integration for both admin and frontend.
     *
     * @return void
     */
    public static function init(): void
    {
        if (is_admin()) {
            // Admin-specific WooCommerce logic (metaboxes, settings, etc.)
            add_action('admin_init', [AdminManager::class, 'registerAll']);
        }

        // Register all WooCommerce order-related hooks
        Orders::registerHooks();

        Customer::registerHooks();

    }
}
