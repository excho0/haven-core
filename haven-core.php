<?php
/*
 * Plugin Name: HavenCore
 * Description: Core supplier and admin utilities for WooCommerce.
 * Version: 0.0.1
 * Author: excho0
 * Text Domain: haven-core
 */

// Prevent direct access to this file for security reasons
if (!defined('ABSPATH')) exit;


// ================================
// Composer Autoloader
// ================================

$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // hook to admin_notices for displaying an error in the admin dashboard
    add_action('admin_notices', function() {
        if (!current_user_can('activate_plugins')) {
            return; // Only show to admins
        }

        $deactivate_url = wp_nonce_url(
            admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(__FILE__)),
            'deactivate-plugin_' . plugin_basename(__FILE__)
        );

        echo '<div class="notice notice-error">';
        echo '<h2>HavenCore Critical Error</h2>';
        echo '<p>Composer autoloader not found. Please run <code>composer install</code> or include the vendor directory.</p>';
        echo '<p>If the problem persists, please contact us at <a href="mailto:support@havencore.com">support@havencore.com</a>.</p>';
        echo '<p><a href="' . esc_url($deactivate_url) . '" class="button button-secondary">Disable Plugin</a></p>';
        echo '</div>';
    });

    // Prevent the rest of the plugin from loading
    return;
}

// ================================
// Lifecycle hook registration
// ================================

require_once plugin_dir_path(__FILE__) . 'src/class-lifecycle.php';
use HavenCore\Lifecycle\LifecycleManager;


// We use template_include to load plugin-based templates for pages like 'goodbye' and 'supplier-portal'.
// WordPress only supports assigning templates from theme directories via _wp_page_template.
// Since our templates live in the plugin (not the theme), we must override them here manually.
add_filter('template_include', [LifecycleManager::class, 'overrideTemplates'], 99);

register_activation_hook(__FILE__,    [LifecycleManager::class, 'activate']);
register_deactivation_hook(__FILE__, [LifecycleManager::class, 'deactivate']);
register_uninstall_hook(__FILE__,    [LifecycleManager::class, 'uninstall']);

/**
 * Bootstraps the plugin's core functionality.
 *
 * The logic and hook registration are organized in the `src/index.php` file
 * to keep this main plugin file clean and focused on metadata.
 *
 * This allows for better structure, scalability, and separation of concerns
 * as the plugin grows in complexity.
 */
require_once plugin_dir_path(__FILE__) . 'src/index.php';


// ================================
// Add "Settings" link in plugins list
// ================================

function havencore_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=havencore-app#/settings') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'havencore_plugin_action_links');
