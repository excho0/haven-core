<?php

namespace HavenCore\Hooks;

// Auto-load all class-*.php files in this directory except this one
foreach (glob(__DIR__ . '/class-*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}

use HavenCore\Hooks\SupplierHooks;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class HookManager
 *
 * Central registry for registering all plugin-wide background (async) hooks,
 * typically used by Action Scheduler or similar systems.
 *
 * Usage:
 * Call HookManager::registerAll() during plugin bootstrap (outside of AJAX).
 *
 * @package HavenCore\Hooks
 */
class HookManager
{
    /**
     * Register all async/background job hooks used by the plugin.
     *
     * This should be called during the plugin's normal initialization,
     * not only during AJAX or admin requests.
     *
     * @return void
     */
    public static function registerAll(): void
    {
        SupplierHooks::register();

        WP_Hooks::register();

        // Future hook groups can be registered here:
        // OrderHooks::register();
        // PaymentHooks::register();
    }
}
