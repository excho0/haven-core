<?php
/**
 * Register HavenCore Admin Menu and Separator
 *
 * Adds a custom admin menu for HavenCore with a visual separator above it.
 * The menu is placed just below any plugin using position 55.4 (e.g., TrackShip)
 * and just above WooCommerce (typically at 55.5).
 *
 * @package HavenCore
 */

/**
 * Registers the HavenCore admin menu and a separator.
 */
add_action('admin_menu', 'havencore_register_admin_menu');

function havencore_register_admin_menu() {
    global $menu;

    // Add a visual separator above the HavenCore menu
    $menu['55.39'] = [
        '',                      // Menu title (empty for separators)
        'read',                  // Capability
        'separator-havencore',   // Unique slug
        '',                      // No link
        'wp-menu-separator'      // CSS class
    ];

    // Add the HavenCore top-level menu
    add_menu_page(
        'HavenCore',             // Page title (browser tab)
        'HavenCore',             // Menu title (sidebar label)
        'manage_options',        // Capability required
        'havencore-app',         // Menu slug
        function () {
            require_once HAVEN_CORE_PATH . 'views/admin/page-havencore-app.php';
        },
        'dashicons-admin-generic', // Dashicon for the menu
        55.4                     // Position in the admin sidebar
    );
}

/**
 * Dequeue all styles and scripts except Dashicons on the HavenCore page.
 */
add_action('admin_enqueue_scripts', function ($hook_suffix) {
    if (isset($_GET['page']) && $_GET['page'] === 'havencore-app') {
        global $wp_styles, $wp_scripts;

        // Dequeue all styles
        if (!empty($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                wp_dequeue_style($handle);
            }
        }

        // Dequeue all scripts
        if (!empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                wp_dequeue_script($handle);
            }
        }

        // Re-enqueue Dashicons
        wp_enqueue_style('dashicons');
    }
}, 1);

/**
 * Inject early CSS to hide admin elements ASAP in the head.
 */
add_action('admin_head', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'havencore-app') {
        echo '
        <style>
            body.wp-admin #adminmenuwrap,
            body.wp-admin #adminmenumain,
            body.wp-admin #adminmenuback,
            body.wp-admin #wpadminbar,
            body.wp-admin #screen-meta,
            body.wp-admin #screen-meta-links,
            body.wp-admin #contextual-help-link,
            body.wp-admin .update-nag,
            body.wp-admin .notice,
            body.wp-admin .wrap > h1,
            body.wp-admin .wp-header-end,
            body.wp-admin #wpfooter {
                display: none !important;
            }
            body.wp-admin #wpcontent,
            body.wp-admin #wpbody,
            body.wp-admin #wpbody-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            html.wp-toolbar {
                padding-top: 0 !important;
                overflow: hidden; 
                overscroll-behavior: none;
            }
        </style>
        ';
    }
}, 0); // priority 0: run as early as possible
