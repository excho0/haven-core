<?php
if (!defined('ABSPATH')) exit;

// Admin Menu: Supplier Manager Page
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Manage Suppliers',
        'Manage Suppliers',
        'manage_options',
        'manage-suppliers',
        function () {
            require_once HAVEN_CORE_PATH . 'views/admin/page-supplier-manager.php';
        }
    );
});

// Style Adjustments
add_action('admin_head', function () {
    $current_page = $_GET['page'] ?? '';

    if ($current_page === 'manage-suppliers') {
        echo '
        <style>
            .notice, .update-nag, .wrap > h1 {
                display: none !important;
            }
            #wpcontent {
                padding-left: 0px !important;
                padding-right: 0px !important;
                scrollbar-gutter: stable !important;
            }
        </style>';

        if (isset($_GET['standalone']) && $_GET['standalone'] === 'true') {
            echo '
            <style>
                html.wp-toolbar {
                    padding-top: 0 !important;
                }
                #adminmenumain,
                #wpadminbar,
                #screen-meta,
                #screen-meta-links,
                #contextual-help-link-wrap {
                    display: none !important;
                }
                #wpcontent, #wpbody, #wpbody-content {
                    margin: 0 !important;
                    padding: 0 !important;
                }
            </style>';
        }
    }
});

// Optional: Disable the admin bar completely in standalone mode
add_filter('show_admin_bar', function ($show) {
    return (isset($_GET['standalone']) && $_GET['standalone'] === 'true') ? false : $show;
});

// Add custom body classes
add_filter('admin_body_class', function($classes) {
    $current_page = $_GET['page'] ?? '';

    if ($current_page === 'manage-suppliers') {
        $classes .= ' manage_supplier_setting_admin_settings';
        if (isset($_GET['standalone']) && $_GET['standalone'] === 'true') {
            $classes .= ' havencore-manage-suppliers-standalone';
        }
    }
    return $classes;
});
