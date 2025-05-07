<?php

/**
 * HavenCore Plugin Bootstrap File
 *
 * Initializes plugin constants, encryption, core logic, and admin-specific hooks.
 */

// ===============================================
// Constants: Define plugin paths and URLs
// ===============================================

/**
 * Define the custom table prefix of the plugin's tables.
 */
define('HAVEN_CORE_DB_PREFIX', 'hc_'); 

/**
 * The text domain used for plugin translations.
 */
define('HAVEN_CORE_TEXT_DOMAIN', 'haven-core');

/**
 * Absolute path to the /src/ directory of the plugin.
 */
define('HAVEN_CORE_PATH', plugin_dir_path(__DIR__) . 'src/');

/**
 * Public URL path to the /src/ directory of the plugin.
 */
define('HAVEN_CORE_URL', plugin_dir_url(__DIR__) . 'src/');


/**
 * Absolute path to templates directory.
 */
define('HAVEN_CORE_TEMPLATES_PATH', HAVEN_CORE_PATH . 'templates/');

/**
 * Absolute path to pages directory.
 */
define('HAVEN_CORE_PAGE_TEMPLATES_PATH', HAVEN_CORE_TEMPLATES_PATH . 'pages/');

/**
 * Absolute path to email templates directory.
 */
define('HAVEN_CORE_EMAIL_TEMPLATES_PATH', HAVEN_CORE_TEMPLATES_PATH . 'emails/');

/**
 * Path to shared email base styles (PHP include).
 */
define('HAVEN_CORE_EMAIL_STYLES_PATH', HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'email-base-styles.php');

/**
 * Slug used for the account security/password reset page.
 */
define('HAVEN_CORE_PASSWORD_RESET_SLUG', 'account-security');

// ===============================================
// Stylesheet Enqueuing
// ===============================================

/**
 * Enqueue the core plugin stylesheet with automatic cache busting.
 *
 * Fires in the admin area only.
 */
function haven_core_enqueue_styles() {
    $style_path = HAVEN_CORE_PATH . 'assets/css/main.css';
    wp_enqueue_style(
        'haven-core-style',
        HAVEN_CORE_URL . 'assets/css/main.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : null,
        'all'
    );
}
add_action('wp_enqueue_scripts', 'haven_core_enqueue_styles');
add_action('admin_enqueue_scripts', 'haven_core_enqueue_styles');

// ===============================================
// 1. Load Core Logic (Classes, Business Logic)
// ===============================================


/**
 * Ensures core WordPress user-related functions are available before using them.
 *
 * This is especially useful in plugin contexts like REST endpoints, CLI commands,
 * or custom loaders that may execute before WordPress includes all required files.
 *
 * - `pluggable.php` contains many user/auth-related functions such as `wp_get_current_user()`.
 * - `user.php` (from wp-admin) defines critical user management functions like `wp_delete_user()`.
 *
 * WordPress does not load these by default in all contexts (e.g., REST API requests),
 * so we must include them manually if not already loaded.
 *
 * This function checks if the needed functions exist, and only includes the files if necessary.
 *
 * @return void
 */
function hc_ensure_user_functions_loaded() {
    // Load pluggable.php if core auth/user functions aren't yet available
    if ( ! function_exists( 'wp_get_current_user' ) ) {
        require_once ABSPATH . WPINC . '/pluggable.php';
    }

    // Load wp-admin user functions like wp_delete_user() if not present
    if ( ! function_exists( 'wp_delete_user' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
}

// Ensure availability before calling user functions like wp_delete_user()
hc_ensure_user_functions_loaded();



use HavenCore\Classes\HC_Settings;

$settings = new HC_Settings();

// Load AJAX functionality
require_once HAVEN_CORE_PATH . 'ajax/index.php';
use HavenCore\Ajax\AjaxManager;

require_once HAVEN_CORE_PATH . 'woocommerce/ajax/index.php';
use HavenCore\WooCommerce\Ajax\Woo_AjaxManager;

// Load WooCommerce-related integrations
require_once HAVEN_CORE_PATH . 'woocommerce/index.php';
use HavenCore\WooCommerce\WooCommerceBootstrap;

// Load and register all async background hooks
require_once HAVEN_CORE_PATH . 'hooks/index.php';
use HavenCore\Hooks\HookManager;

// Immediate registration (runs for all contexts, including Action Scheduler background)
HookManager::registerAll();

// Initialize WooCommerce logic during the 'init' hook
add_action('init', function () {
    WooCommerceBootstrap::init();
});

// Include widgets
foreach (glob(HAVEN_CORE_PATH . 'views/widgets/*.php') as $file) {
    require_once $file;
}

// ===============================================
// 2. Load Admin-Only Logic
// ===============================================
if (is_admin()) {

    // Clean up WordPress admin footer branding
    add_filter('admin_footer_text', '__return_empty_string');
    add_filter('update_footer', '__return_empty_string', 11);

    // Load core admin styles
    add_action('admin_enqueue_scripts', 'haven_core_enqueue_styles');

    // Include all admin-specific scripts
    foreach (glob(HAVEN_CORE_PATH . 'admin/*.php') as $file) {
        require_once $file;
    }


    // Register AJAX handlers during AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        AjaxManager::registerAll();
        Woo_AjaxManager::registerAll();
    }
}

use HavenCore\RestApi\Server;

add_action( 'plugins_loaded', function () {
    Server::instance()->init();
} );

// ===============================================
// 3. Load Custom WP Login hooks/styles
// ===============================================
if ($settings->get('general.custom_login_page', true)) {
    // If a custom login UI is enabled, load the custom login hooks
    require_once HAVEN_CORE_PAGE_TEMPLATES_PATH . 'wp-login.php';
}
