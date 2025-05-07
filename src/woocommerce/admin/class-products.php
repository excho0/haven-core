<?php

namespace HavenCore\WooCommerce\Admin;

use HavenCore\Services\HC_Supplier_Service;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Products
 *
 * Manages supplier-related functionality in the WooCommerce admin interface,
 * including custom product fields and product table columns.
 *
 * @package HavenCore\Admin
 */
class Products
{
    /**
     * Registers all admin hooks related to suppliers and custom fields.
     *
     * @return void
     */
    public static function register(): void
    {
        // Supplier Price field
        add_action('woocommerce_product_options_pricing', [self::class, 'add_supplier_price_field']);
        add_action('woocommerce_process_product_meta', [self::class, 'save_supplier_price_field']);

        // Supplier dropdown
        add_action('woocommerce_product_options_general_product_data', [self::class, 'add_supplier_dropdown']);
        add_action('woocommerce_process_product_meta', [self::class, 'save_supplier_dropdown']);

        // Admin columns
        add_filter('manage_edit-product_columns', [self::class, 'add_supplier_column'], 20);
        add_filter('manage_edit-product_columns', [self::class, 'add_supplier_price_column'], 21);
        add_action('manage_product_posts_custom_column', [self::class, 'render_supplier_column'], 10, 2);
        add_action('manage_product_posts_custom_column', [self::class, 'render_supplier_price_column'], 11, 2);
        add_filter('manage_edit-product_sortable_columns', [self::class, 'make_supplier_price_sortable']);
        add_action('pre_get_posts', [self::class, 'handle_supplier_price_sorting']);

        // Variation-level Supplier Price
        add_action('woocommerce_variation_options_pricing', [self::class, 'add_supplier_price_field_to_variations'], 10, 3);
        add_action('woocommerce_save_product_variation', [self::class, 'save_supplier_price_variation'], 10, 2);

        // Admin visuals and scripts
        add_action('admin_footer', [self::class, 'print_ajax_script']);
        add_action('admin_head', [self::class, 'add_admin_column_styles']);
        add_action('wp_ajax_save_supplier_from_table', [self::class, 'handle_ajax_supplier_save']);
    }

    /**
     * Adds the Supplier Price field to the product pricing section.
     *
     * @return void
     */
    public static function add_supplier_price_field(): void
    {
        $currency_symbol = get_woocommerce_currency_symbol();

        woocommerce_wp_text_input([
            'id' => '_supplier_price',
            'label' => __('Supplier Price', 'woocommerce') . ' (' . $currency_symbol . ')',
            'placeholder' => 'Enter Supplier Price',
            'desc_tip' => 'true',
            'description' => __('Enter the price set by your supplier for this product.', 'woocommerce'),
            'type' => 'text',
            'data_type' => 'price',
        ]);
    }

    /**
     * Saves the Supplier Price field value.
     *
     * @param int $post_id Product ID.
     * @return void
     */
    public static function save_supplier_price_field($post_id): void
    {
        if (isset($_POST['_supplier_price'])) {
            $supplier_price = sanitize_text_field($_POST['_supplier_price']);
            update_post_meta($post_id, '_supplier_price', $supplier_price);
        }
    }

    public static function add_supplier_price_field_to_variations($loop, $variation_data, $variation): void
    {
        $currency_symbol = get_woocommerce_currency_symbol();
        woocommerce_wp_text_input([
            'id'          => 'variable_supplier_price_' . $loop,
            'name'        => 'variable_supplier_price[' . $loop . ']',
            'label'       => __('Supplier Price', 'woocommerce') . ' (' . $currency_symbol . ')',
            'type'        => 'text',
            'data_type'   => 'price',
            'wrapper_class' => 'form-row form-row-full',
            'value'       => get_post_meta($variation->ID, '_supplier_price', true),
        ]);
    }

    public static function save_supplier_price_variation(int $variation_id, int $i): void
    {
        if (isset($_POST['variable_supplier_price'][$i])) {
            $supplier_price = sanitize_text_field($_POST['variable_supplier_price'][$i]);
            update_post_meta($variation_id, '_supplier_price', $supplier_price);
        }
    }

    /**
     * Adds a supplier dropdown field to the product general tab.
     *
     * @return void
     */

    public static function add_supplier_dropdown(): void
    {
        global $post;

        $current_supplier = get_post_meta($post->ID, '_supplier_id', true);
        $service = new HC_Supplier_Service();
        $suppliers = $service->all();

        if (!empty($suppliers)) {
            echo '<div class="options_group">';
            woocommerce_wp_select([
                'id' => '_supplier_id',
                'label' => __('Supplier', 'woocommerce'),
                'description' => __('Assign a supplier for this product (optional).', 'woocommerce'),
                'options' => ['' => '— No Supplier —'] + array_reduce($suppliers, function ($options, $supplier) {
                    $options[$supplier->get_id()] = $supplier->get_name() ?? '';
                    return $options;
                }, []),
                'value' => $current_supplier,
            ]);
            echo '</div>';
        }
    }


    /**
     * Saves the selected supplier.
     *
     * @param int $post_id Product ID.
     * @return void
     */
    public static function save_supplier_dropdown($post_id): void
    {
        if (isset($_POST['_supplier_id'])) {
            $supplier_id = sanitize_text_field($_POST['_supplier_id']);
            if ($supplier_id) {
                update_post_meta($post_id, '_supplier_id', $supplier_id);
            } else {
                delete_post_meta($post_id, '_supplier_id');
            }
        }
    }

    /**
     * Adds the Supplier column next to the featured column.
     *
     * @param array $columns Original columns.
     * @return array Modified columns.
     */
    public static function add_supplier_column(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            if ($key === 'featured') {
                $new_columns['supplier'] = __('Supplier', 'woocommerce');
            }
            $new_columns[$key] = $label;
        }
        return $new_columns;
    }

    /**
     * Adds the Supplier Price column next to the Price column.
     *
     * @param array $columns Original columns.
     * @return array Modified columns.
     */
    public static function add_supplier_price_column(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'price') {
                $new_columns['supplier_price'] = __('Supplier Price', 'woocommerce');
            }
        }
        return $new_columns;
    }

    /**
     * Renders the supplier dropdown inside the Supplier column.
     *
     * @param string $column Column name.
     * @param int $post_id   Product ID.
     * @return void
     */
    public static function render_supplier_column(string $column, int $post_id): void
    {
        if ($column !== 'supplier') return;

        $current_supplier = get_post_meta($post_id, '_supplier_id', true);
        $service = new HC_Supplier_Service();
        $suppliers = $service->all();

        echo '<select class="supplier-dropdown" data-product_id="' . esc_attr($post_id) . '" style="min-width:120px;">';
        echo '<option value="">— None —</option>';

        foreach ($suppliers as $supplier) {
            $selected = $supplier->get_id() == $current_supplier ? 'selected' : '';
            echo '<option value="' . esc_attr($supplier->get_id()) . '" ' . $selected . '>' . esc_html($supplier->get_name()) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Displays the Supplier Price value in the product list.
     *
     * @param string $column Column name.
     * @param int $post_id   Product ID.
     * @return void
     */
    public static function render_supplier_price_column(string $column, int $post_id): void
    {
        if ($column !== 'supplier_price') return;

        $supplier_price = get_post_meta($post_id, '_supplier_price', true);
        echo $supplier_price ? wc_price($supplier_price) : '–';
    }

    /**
     * Makes the Supplier Price column sortable.
     *
     * @param array $columns Sortable columns.
     * @return array Modified sortable columns.
     */
    public static function make_supplier_price_sortable(array $columns): array
    {
        $columns['supplier_price'] = 'supplier_price';
        return $columns;
    }

    /**
     * Handles sorting by Supplier Price in the admin product list.
     *
     * @param \WP_Query $query Main query.
     * @return void
     */
    public static function handle_supplier_price_sorting(\WP_Query $query): void
    {
        if (is_admin() && $query->is_main_query() && $query->get('orderby') === 'supplier_price') {
            $query->set('meta_key', '_supplier_price');
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Outputs JavaScript to handle AJAX saving of supplier changes.
     *
     * @return void
     */
    public static function print_ajax_script(): void
    {
        global $pagenow;
        if ($pagenow !== 'edit.php' || ($_GET['post_type'] ?? '') !== 'product') return;

        ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.supplier-dropdown').on('change', function() {
                        const select = $(this);
                        const supplier_id = select.val();
                        const product_id = select.data('product_id');

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'save_supplier_from_table',
                                product_id: product_id,
                                supplier_id: supplier_id
                            },
                            success: function(response) {
                                if (response.success) {
                                    select.css('background-color', '#d4edda');
                                    setTimeout(() => select.css('background-color', ''), 1000);
                                } else {
                                    alert(response.data.message);
                                }
                            },
                            error: function() {
                                alert('Something went wrong while saving.');
                            }
                        });
                    });
                });
            </script>
        <?php
    }

    /**
     * Handles the AJAX request to save supplier from the product table.
     *
     * @return void
     */
    public static function handle_ajax_supplier_save(): void
    {
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => 'Not allowed']);
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $supplier_id = sanitize_text_field($_POST['supplier_id'] ?? '');

        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product']);
        }

        if ($supplier_id) {
            update_post_meta($product_id, '_supplier_id', $supplier_id);
        } else {
            delete_post_meta($product_id, '_supplier_id');
        }

        wp_send_json_success();
    }

    /**
     * Adds CSS to style the supplier and supplier price columns in the admin table.
     *
     * @return void
     */
    public static function add_admin_column_styles(): void
    {
        global $pagenow;
        if ($pagenow !== 'edit.php' || ($_GET['post_type'] ?? '') !== 'product') return;

        echo '
            <style>
                th.column-supplier, td.column-supplier,
                th.column-supplier_price, td.column-supplier_price {
                    width: 160px;
                    vertical-align: top;
                }
                td.column-supplier select {
                    width: 100%;
                    max-width: 140px;
                    padding: 2px 5px;
                    font-size: 12px;
                }
                th.column-supplier_price a {
                    display: inline-block;
                    text-align: center;
                    width: 100%;
                }
            </style>
        ';
    }
}
