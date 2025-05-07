<?php

namespace HavenCore\WooCommerce\Admin;


use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Orders
 *
 * Handles WooCommerce admin-side logic for orders, including:
 * - Registering custom metaboxes
 * - Adding custom columns
 *
 * @package HavenCore\WooCommerce\Admin
 */
class Orders
{
    /**
     * Register all WooCommerce order-related logic.
     *
     * @return void
     */
    public static function register(): void
    {
        // Register supplier fulfillment metabox
        add_action('add_meta_boxes', [self::class, 'register_supplier_fulfillment_metabox']);

        // Register custom columns
        add_action('current_screen', [self::class, 'maybe_register_order_columns']);

    }

    /**
     * Registers the Supplier Fulfillment Info metabox in order admin.
     *
     * @return void
     */
    public static function register_supplier_fulfillment_metabox(): void
    {
        $screen = class_exists(CustomOrdersTableController::class) &&
            wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'supplier_fulfillment_box',
            __('Supplier Fulfillment Info', 'woocommerce'),
            function ($post) {
                require HAVEN_CORE_PATH . 'woocommerce/views/admin/orders/metabox-supplier-fulfillments.php';
            },
            $screen,
            'normal',
            'high'
        );
    }

    /**
     * Conditionally registers custom columns in the orders list screen.
     *
     * @param \WP_Screen $screen
     * @return void
     */
    public static function maybe_register_order_columns(\WP_Screen $screen): void
    {
        $target_screen = class_exists(CustomOrdersTableController::class) &&
            wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'edit-shop_order';

        if ($screen->id !== $target_screen) {
            return;
        }

        add_filter('woocommerce_shop_order_list_table_columns', [self::class, 'asf_add_order_list_column'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [self::class, 'asf_render_order_list_column'], 20, 2);

        // Classic table fallback
        add_filter('manage_edit-shop_order_columns', [self::class, 'asf_add_order_list_column'], 999);
        add_action('manage_shop_order_posts_custom_column', [self::class, 'asf_render_order_list_column'], 20, 2);
    }

    /**
     * Adds a custom "Supplier Fulfillment" column to the order list.
     *
     * @param array $columns
     * @return array
     */
    public static function asf_add_order_list_column(array $columns): array
    {
        $columns['supplier_fulfillment'] = __('Supplier Fulfillment Status', 'woocommerce');
        return $columns;
    }

    /**
     * Renders content for the custom supplier fulfillment column.
     *
     * @param string $column
     * @param mixed $order
     * @return void
     */
    public static function asf_render_order_list_column(string $column, $order): void
    {
        if ($column !== 'supplier_fulfillment') {
            return;
        }

        require HAVEN_CORE_PATH . 'woocommerce/views/admin/orders/column-supplier-fulfillment-status.php';
    }
}
