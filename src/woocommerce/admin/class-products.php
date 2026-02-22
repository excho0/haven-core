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
    private const MASTER_SHEET_LAYOUT_FULL = 'full';
    private const MASTER_SHEET_LAYOUT_GRID = 'grid';
    private const MASTER_SHEET_DEFAULT_LAYOUT = self::MASTER_SHEET_LAYOUT_GRID;

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

        // Product info sheet (A4 HTML preview for admins)
        add_action('post_submitbox_misc_actions', [self::class, 'render_product_sheet_button']);
        add_action('admin_post_hc_product_info_sheet', [self::class, 'render_product_sheet_page']);
        add_action('admin_post_hc_product_info_sheet_master', [self::class, 'render_product_sheet_master_page']);
        add_action('admin_footer', [self::class, 'inject_master_sheet_button_script']);
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

    /**
     * Render "View Product Sheet" button in product edit sidebar.
     *
     * @return void
     */
    public static function render_product_sheet_button(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        if (!current_user_can('edit_product', $post->ID)) {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=hc_product_info_sheet&product_id=' . absint($post->ID) . '&autoprint=1'),
            'hc_product_info_sheet_' . absint($post->ID)
        );

        echo '<div class="misc-pub-section">';
        echo '<a class="button button-secondary" target="_blank" href="' . esc_url($url) . '">';
        echo esc_html__('View Product Info Sheet', 'woocommerce');
        echo '</a>';
        echo '</div>';
    }

    /**
     * Render standalone A4 product info sheet.
     *
     * @return void
     */
    public static function render_product_sheet_page(): void
    {
        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        if (!$product_id) {
            wp_die(esc_html__('Missing product ID.', 'woocommerce'));
        }
        if (!current_user_can('edit_product', $product_id)) {
            wp_die(esc_html__('You are not allowed to view this page.', 'woocommerce'));
        }
        check_admin_referer('hc_product_info_sheet_' . $product_id);

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_die(esc_html__('Product not found.', 'woocommerce'));
        }

        $template_data = self::prepare_product_sheet_template_data($product);
        $template_data['auto_print'] = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';

        nocache_headers();
        $template_path = HAVEN_CORE_PATH . 'views/admin/product-info-sheet-a4.php';
        if (!is_readable($template_path)) {
            wp_die(esc_html__('Product sheet template missing.', 'woocommerce'));
        }

        extract($template_data, EXTR_OVERWRITE);
        include $template_path;
        exit;
    }

    /**
     * Inject "Export Info Sheets" button next to WooCommerce Export on products list.
     *
     * @return void
     */
    public static function inject_master_sheet_button_script(): void
    {
        global $pagenow;
        if ($pagenow !== 'edit.php' || ($_GET['post_type'] ?? '') !== 'product') {
            return;
        }
        if (!current_user_can('edit_products')) {
            return;
        }

        $args = [
            'action' => 'hc_product_info_sheet_master',
            'layout' => self::get_master_sheet_default_layout(),
        ];
        foreach (['s', 'product_cat', 'product_type', 'stock_status'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $args[$key] = sanitize_text_field((string) $_GET[$key]);
            }
        }
        $args['_wpnonce'] = wp_create_nonce('hc_product_info_sheet_master');
        $url = add_query_arg($args, admin_url('admin-post.php'));
        ?>
        <script>
            (function() {
                const headingActions = document.querySelectorAll('.wrap .page-title-action');
                if (!headingActions.length) return;
                if (document.getElementById('hc-export-info-sheets')) return;

                const btn = document.createElement('a');
                btn.id = 'hc-export-info-sheets';
                btn.className = 'page-title-action';
                btn.href = <?php echo wp_json_encode($url); ?>;
                btn.target = '_blank';
                btn.textContent = 'Export Info Sheets';

                const exportBtn = Array.from(headingActions).find(a => (a.textContent || '').trim().toLowerCase() === 'export');
                if (exportBtn && exportBtn.parentNode) {
                    exportBtn.insertAdjacentElement('afterend', btn);
                } else {
                    headingActions[headingActions.length - 1].insertAdjacentElement('afterend', btn);
                }
            })();
        </script>
        <?php
    }

    /**
     * Render all filtered products as a multi-page print-ready booklet.
     * Users can Save as PDF from browser print dialog.
     *
     * @return void
     */
    public static function render_product_sheet_master_page(): void
    {
        if (!current_user_can('edit_products')) {
            wp_die(esc_html__('You are not allowed to view this page.', 'woocommerce'));
        }
        check_admin_referer('hc_product_info_sheet_master');

        $product_ids = self::resolve_master_export_product_ids();
        if (empty($product_ids)) {
            wp_die(esc_html__('No products matched your current filters.', 'woocommerce'));
        }

        $layout_mode = self::resolve_master_sheet_layout();
        if ($layout_mode === self::MASTER_SHEET_LAYOUT_GRID) {
            self::render_product_sheet_master_grid_page($product_ids);
        } else {
            self::render_product_sheet_master_full_page($product_ids);
        }
        exit;
    }

    /**
     * Render full-sheet mode (one product per page).
     *
     * @param int[] $product_ids
     * @return void
     */
    private static function render_product_sheet_master_full_page(array $product_ids): void
    {
        $template_path = HAVEN_CORE_PATH . 'views/admin/product-info-sheet-a4.php';
        if (!is_readable($template_path)) {
            wp_die(esc_html__('Product sheet template missing.', 'woocommerce'));
        }

        $css = self::extract_template_css($template_path);

        $articles = [];
        foreach ($product_ids as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (!$product) {
                continue;
            }
            $article_html = self::render_product_sheet_article_html($product, $template_path);
            if ($article_html !== '') {
                $articles[] = $article_html;
            }
        }

        if (empty($articles)) {
            wp_die(esc_html__('Unable to render product sheets.', 'woocommerce'));
        }

        nocache_headers();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php esc_html_e('Product Info Sheets Export', 'woocommerce'); ?></title>
            <style>
                <?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                .sheet-page-break { page-break-after: always; break-after: page; }
                .sheet-page-break:last-child { page-break-after: auto; break-after: auto; }
            </style>
        </head>
        <body>
            <?php foreach ($articles as $article_html) : ?>
                <div class="sheet-page-break">
                    <?php echo $article_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endforeach; ?>
            <script>
                window.addEventListener('load', () => setTimeout(() => window.print(), 180));
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Render grid mode (compact cards, many products per printed page).
     *
     * @param int[] $product_ids
     * @return void
     */
    private static function render_product_sheet_master_grid_page(array $product_ids): void
    {
        $cards = [];
        foreach ($product_ids as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (!$product) {
                continue;
            }
            $cards[] = self::render_product_sheet_grid_card_html($product);
        }

        if (empty($cards)) {
            wp_die(esc_html__('Unable to render product sheets.', 'woocommerce'));
        }

        $template_path = HAVEN_CORE_PATH . 'views/admin/product-info-sheet-grid.php';
        if (!is_readable($template_path)) {
            wp_die(esc_html__('Product sheet grid template missing.', 'woocommerce'));
        }

        $store_name = get_bloginfo('name');
        $logo_url = '';
        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full') ?: '';
        }
        $auto_print = true;

        nocache_headers();
        include $template_path;
    }

    /**
     * Render one compact card for grid export mode.
     *
     * @param \WC_Product $product
     * @return string
     */
    private static function render_product_sheet_grid_card_html(\WC_Product $product): string
    {
        $template_data = self::prepare_product_sheet_template_data($product);
        $hero_image = '';
        if (!empty($template_data['image_urls']) && !empty($template_data['image_urls'][0])) {
            $hero_image = (string) $template_data['image_urls'][0];
        } else {
            $hero_image = (string) wc_placeholder_img_src('woocommerce_single');
        }

        $category_text = wp_strip_all_tags((string) wc_get_product_category_list($product->get_id(), ', '));
        $short_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($template_data['short_description'] ?? ''))));
        $description_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($template_data['description'] ?? ''))));
        $overview = $short_plain !== '' ? $short_plain : $description_plain;
        if ($overview === '') {
            $overview = wp_strip_all_tags((string) __('No description available.', 'woocommerce'));
        }
        // $sections = [[
        //     'title' => (string) __('Overview', 'woocommerce'),
        //     'body' => $overview,
        // ]];
        $sections = [];

        foreach ((array) ($template_data['product_tabs'] ?? []) as $tab) {
            $title = trim((string) ($tab['title'] ?? ''));
            $body = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($tab['content'] ?? ''))));
            if ($title === '' || $body === '') {
                continue;
            }
            $sections[] = [
                'title' => $title,
                'body' => $body,
            ];
        }

        ob_start();
        ?>
        <article class="product-card">
            <div class="card-media">
                <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
            </div>
            <div class="card-body">
                <p class="card-category"><?php echo esc_html($category_text ?: __('Product', 'woocommerce')); ?></p>
                <h2 class="card-name"><?php echo esc_html($product->get_name()); ?></h2>
                <div class="card-sections">
                    <?php foreach ($sections as $section) : ?>
                        <div class="card-section">
                            <h3 class="card-section-title"><?php echo esc_html($section['title']); ?></h3>
                            <p class="card-summary"><?php echo esc_html($section['body']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Resolve master sheet layout mode.
     *
     * @return string
     */
    private static function resolve_master_sheet_layout(): string
    {
        $requested_layout = isset($_GET['layout']) ? sanitize_key((string) $_GET['layout']) : '';
        if (in_array($requested_layout, self::get_master_sheet_layout_modes(), true)) {
            return $requested_layout;
        }
        return self::get_master_sheet_default_layout();
    }

    /**
     * Return allowed layout modes.
     *
     * @return string[]
     */
    private static function get_master_sheet_layout_modes(): array
    {
        return [self::MASTER_SHEET_LAYOUT_FULL, self::MASTER_SHEET_LAYOUT_GRID];
    }

    /**
     * Return default master layout. Change this in code or override via filter.
     *
     * @return string
     */
    private static function get_master_sheet_default_layout(): string
    {
        $layout = apply_filters('haven_core_product_sheet_master_layout', self::MASTER_SHEET_DEFAULT_LAYOUT);
        $layout = sanitize_key((string) $layout);
        if (in_array($layout, self::get_master_sheet_layout_modes(), true)) {
            return $layout;
        }
        return self::MASTER_SHEET_DEFAULT_LAYOUT;
    }

    /**
     * Extract the template CSS block.
     *
     * @param string $template_path
     * @return string
     */
    private static function extract_template_css(string $template_path): string
    {
        $template_raw = (string) file_get_contents($template_path);
        preg_match('/<style>(.*?)<\/style>/s', $template_raw, $style_matches);
        return $style_matches[1] ?? '';
    }

    /**
     * Render a single product sheet article HTML from template.
     *
     * @param \WC_Product $product
     * @param string $template_path
     * @return string
     */
    private static function render_product_sheet_article_html(\WC_Product $product, string $template_path): string
    {
        $template_data = self::prepare_product_sheet_template_data($product);
        extract($template_data, EXTR_OVERWRITE);

        ob_start();
        include $template_path;
        $full_html = (string) ob_get_clean();
        preg_match('/<article class="sheet">.*<\/article>/sU', $full_html, $article_match);
        return $article_match[0] ?? '';
    }

    /**
     * Build template data payload for a product info sheet.
     *
     * @param \WC_Product $product
     * @return array<string,mixed>
     */
    private static function prepare_product_sheet_template_data(\WC_Product $product): array
    {
        $product_id = (int) $product->get_id();
        $store_name = get_bloginfo('name');
        $store_url = home_url('/');
        $store_email = get_option('admin_email');
        $store_phone = get_option('woocommerce_store_phone', '');
        $store_address_1 = get_option('woocommerce_store_address', '');
        $store_address_2 = get_option('woocommerce_store_address_2', '');
        $store_city = get_option('woocommerce_store_city', '');
        $store_postcode = get_option('woocommerce_store_postcode', '');
        $store_country_state = get_option('woocommerce_default_country', '');

        $store_country = '';
        $store_state = '';
        if (strpos($store_country_state, ':') !== false) {
            [$store_country, $store_state] = explode(':', $store_country_state, 2);
        } else {
            $store_country = $store_country_state;
        }

        $countries = function_exists('WC') && WC()->countries ? WC()->countries->get_countries() : [];
        $states = function_exists('WC') && WC()->countries ? WC()->countries->get_states($store_country) : [];
        $store_country_label = $countries[$store_country] ?? $store_country;
        $store_state_label = $states[$store_state] ?? $store_state;

        $logo_url = '';
        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full') ?: '';
        }

        $gallery_ids = $product->get_gallery_image_ids();
        $image_ids = [];
        if ($product->get_image_id()) {
            $image_ids[] = (int) $product->get_image_id();
        }
        foreach ((array) $gallery_ids as $gid) {
            $gid = (int) $gid;
            if ($gid) {
                $image_ids[] = $gid;
            }
        }
        $image_ids = array_values(array_unique($image_ids));
        $image_urls = array_values(array_filter(array_map(static fn($id) => wp_get_attachment_image_url($id, 'large'), $image_ids)));

        $attributes = [];
        foreach ($product->get_attributes() as $attribute) {
            if (!is_a($attribute, \WC_Product_Attribute::class)) {
                continue;
            }
            $label = wc_attribute_label($attribute->get_name(), $product);
            $value = wc_implode_text_attributes($attribute->get_options());
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $value = implode(', ', $terms);
                }
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $attributes[] = ['label' => $label, 'value' => $value];
            }
        }

        return [
            'product' => $product,
            'store_name' => $store_name,
            'store_url' => $store_url,
            'store_email' => $store_email,
            'store_phone' => $store_phone,
            'store_address_1' => $store_address_1,
            'store_address_2' => $store_address_2,
            'store_city' => $store_city,
            'store_postcode' => $store_postcode,
            'store_country_label' => $store_country_label,
            'store_state_label' => $store_state_label,
            'logo_url' => $logo_url,
            'image_urls' => $image_urls,
            'attributes' => $attributes,
            'product_tabs' => self::collect_product_tabs($product),
            'price_html' => wp_strip_all_tags((string) $product->get_price_html()),
            'description' => apply_filters('the_content', (string) $product->get_description()),
            'short_description' => apply_filters('woocommerce_short_description', (string) $product->get_short_description()),
        ];
    }

    /**
     * Resolve product IDs for master export based on current list filters.
     *
     * @return int[]
     */
    private static function resolve_master_export_product_ids(): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ];

        $search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        if ($search !== '') {
            $args['s'] = $search;
        }

        $product_cat = isset($_GET['product_cat']) ? sanitize_text_field((string) $_GET['product_cat']) : '';
        if ($product_cat !== '' && $product_cat !== '0') {
            if (ctype_digit($product_cat)) {
                $args['tax_query'][] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => [(int) $product_cat],
                ];
            } else {
                $args['tax_query'][] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => [$product_cat],
                ];
            }
        }

        $product_type = isset($_GET['product_type']) ? sanitize_text_field((string) $_GET['product_type']) : '';
        if ($product_type !== '' && $product_type !== 'all') {
            $args['tax_query'][] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => [$product_type],
            ];
        }

        $stock_status = isset($_GET['stock_status']) ? sanitize_text_field((string) $_GET['stock_status']) : '';
        if ($stock_status !== '' && $stock_status !== 'all') {
            $args['meta_query'][] = [
                'key' => '_stock_status',
                'value' => $stock_status,
            ];
        }

        if (!empty($args['tax_query']) && count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }
        if (!empty($args['meta_query']) && count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }

        $query = new \WP_Query($args);
        return array_values(array_map('intval', (array) $query->posts));
    }

    /**
     * Collect rendered product tabs content including custom tabs.
     *
     * @param \WC_Product $product
     * @return array<int, array{title:string,content:string}>
     */
    private static function collect_product_tabs(\WC_Product $product): array
    {
        $post = get_post($product->get_id());
        if (!$post) {
            return [];
        }

        $prev_product = $GLOBALS['product'] ?? null;
        $prev_post = $GLOBALS['post'] ?? null;

        $GLOBALS['product'] = $product;
        $GLOBALS['post'] = $post;

        $tabs = apply_filters('woocommerce_product_tabs', []);
        if (!is_array($tabs) || empty($tabs)) {
            $GLOBALS['product'] = $prev_product;
            $GLOBALS['post'] = $prev_post;
            return [];
        }

        uasort($tabs, static fn($a, $b) => (int)($a['priority'] ?? 0) <=> (int)($b['priority'] ?? 0));

        $rendered = [];
        foreach ($tabs as $key => $tab) {
            $title = trim((string) ($tab['title'] ?? ''));
            $callback = $tab['callback'] ?? null;
            if ($title === '' || !is_callable($callback)) {
                continue;
            }
            ob_start();
            try {
                call_user_func($callback, $key, $tab);
            } catch (\Throwable $e) {
                // Keep going if a custom tab callback fails.
            }
            $content = trim((string) ob_get_clean());
            if ($content !== '') {
                $rendered[] = [
                    'title' => wp_strip_all_tags($title),
                    'content' => $content,
                ];
            }
        }

        $GLOBALS['product'] = $prev_product;
        $GLOBALS['post'] = $prev_post;

        return $rendered;
    }
}
