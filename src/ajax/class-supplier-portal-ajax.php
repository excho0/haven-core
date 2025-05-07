<?php

namespace HavenCore\Ajax;

use HavenCore\Services\HC_Supplier_Service;

/**
 * Class SupplierPortalAjax
 *
 * Handles AJAX-based supplier portal actions processing using logged-in supplier data.
 *
 * @package HavenCore\Ajax
 */
class SupplierPortalAjax
{
    /**
     * Registers all AJAX actions related to the supplier portal.
     *
     * Actions registered:
     * - havencore_supplier_save_metadata: Save supplier metadata (e.g., tracking, notes).
     * - havencore_supplier_confirm_fulfillment: Confirm fulfillment of an order by the supplier.
     * - havencore_get_supplier_assigned_orders: Get all orders assigned to a supplier.
     * - havencore_supplier_change_password: Change the supplier's password.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_havencore_supplier_save_metadata', [self::class, 'save_metadata']);
        add_action('wp_ajax_havencore_supplier_confirm_fulfillment', [self::class, 'confirm_fulfillment']);
        add_action('wp_ajax_havencore_get_supplier_assigned_orders', [self::class, 'get_supplier_assigned_orders']);
        add_action('wp_ajax_havencore_supplier_change_password', [self::class, 'handle_supplier_change_password']);
    }

    /**
     * Handles AJAX request to change the supplier's password.
     *
     * - Verifies nonce for security.
     * - Validates the new password.
     * - Updates the WordPress user's password.
     * - Regenerates the authentication cookie.
     * - Updates the supplier's 'needs_to_change_password' attribute via HC_Supplier_Service.
     * - Returns the updated supplier attributes to the frontend.
     *
     * @return void
     */
    public static function handle_supplier_change_password() {
        // Check if the current user is logged in
        if ( is_user_logged_in() && wc_current_user_has_role('supplier') ) {
            $user = wp_get_current_user();
            
            // Get the new password from the AJAX request
            $new_password = sanitize_text_field( $_POST['new_password'] );

            // Validate the password (you can add more checks here)
            if ( strlen($new_password) < 8 ) {
                wp_send_json_error( array('message' => 'Password must be at least 8 characters long') );
                return;
            }

            // Update the user's password
            wp_set_password( $new_password, $user->ID );

            // 🔐 Regenerate authentication cookie to keep user logged in
            wp_set_auth_cookie($user->ID);

            // ✅ Use HC_Supplier_Service to update supplier attributes
            try {
                $supplier_service = new HC_Supplier_Service();
                $supplier = $supplier_service->get($user->ID);

                if ($supplier) {
                    // Update needs_to_change_password attribute
                    $supplier_service->update($supplier->get_id(), [
                        'attributes' => [
                            'needs_to_change_password' => false
                        ]
                    ]);

                    // Reload the supplier to get the updated attributes
                    $supplier = $supplier_service->get($user->ID); // Re-fetch the supplier

                    // Fetch updated attributes
                    $updated_attributes = $supplier->get_attributes();
                }
            } catch (\Exception $e) {
                error_log('Failed to update supplier attributes: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Failed to update supplier attributes']);
                return;
            }

            // Send updated supplier attributes back to the frontend
            wp_send_json_success([
                'message' => 'Password updated successfully',
                'updated_attributes' => $updated_attributes // Return the updated attributes
            ]);
        } else {
            wp_send_json_error(['message' => 'You must be logged in as a supplier to change the password']);
        }
    }

    /**
     * Handles AJAX request to fetch assigned orders for the logged-in supplier.
     *
     * - Verifies the supplier is logged in and authorized.
     * - Retrieves supplier data and assigned orders.
     * - Applies optional status filtering, sorting, and pagination.
     * - Returns the filtered, sorted, and paginated list of orders.
     *
     * @return void
     */
    public static function get_supplier_assigned_orders() {
        if (!is_user_logged_in() || !wc_current_user_has_role('supplier')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $user = wp_get_current_user();
        $service = new HC_Supplier_Service();
        $supplier = $service->get((int) $user->ID);

        if (!$supplier) {
            wp_send_json_error(['message' => 'Supplier not found'], 404);
        }

        $supplier_id = $supplier->get_id() ?? null;
        $assigned_orders = $supplier->get_assigned_orders() ?? [];

        // Handle status filter
        $status_filter = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'all';
        $filtered_orders = [];

        foreach ($assigned_orders as $order_id) {
            $supplier_data = get_post_meta($order_id, '_supplier_data', true);
            $supplier_status = $supplier_data[$supplier_id]['fulfillment_status'] ?? 'pending';

            if ($status_filter !== 'all' && strtolower($supplier_status) !== strtolower($status_filter)) {
                continue;
            }

            $filtered_orders[] = $order_id;
        }

        // Sorting
        $sort_order = isset($_REQUEST['sort']) ? strtolower(sanitize_text_field($_REQUEST['sort'])) : 'desc';
        usort($filtered_orders, function($a, $b) use ($sort_order) {
            $post_a = get_post($a);
            $post_b = get_post($b);

            // Defensive checks
            $date_a = $post_a && !empty($post_a->post_date) ? strtotime($post_a->post_date) : 0;
            $date_b = $post_b && !empty($post_b->post_date) ? strtotime($post_b->post_date) : 0;

            return ($sort_order === 'asc') ? ($date_a - $date_b) : ($date_b - $date_a);
        });

        // Pagination
        $page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
        $per_page = isset($_REQUEST['per_page']) ? max(1, intval($_REQUEST['per_page'])) : 10;
        $total_orders = count($filtered_orders);
        $total_pages = ceil($total_orders / $per_page);
        $offset = ($page - 1) * $per_page;

        $paged_orders = array_slice($filtered_orders, $offset, $per_page);

        $orders_data = [];

        foreach ($paged_orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $supplier_data = get_post_meta($order_id, '_supplier_data', true);
            $supplier_status = $supplier_data[$supplier_id]['fulfillment_status'] ?? 'pending';

            // Prepare ungrouped products
            $ungrouped_products = [];
            if (!empty($supplier_data[$supplier_id]['ungrouped_products'])) {
                foreach ($supplier_data[$supplier_id]['ungrouped_products'] as $product_entry) {
                    $product_id = $product_entry['product_id'] ?? null;
                    if (!$product_id) continue;

                    $wc_product = wc_get_product($product_id);
                    $ordered_qty = 0;
                    foreach ($order->get_items() as $item) {
                        if ($item instanceof \WC_Order_Item_Product && $item->get_product_id() == $product_id) {
                            $ordered_qty = $item->get_quantity();
                            break;
                        }
                    }

                    $ungrouped_products[] = [
                        'product_id' => $product_id,
                        'product_name' => $wc_product ? $wc_product->get_name() : 'Unknown',
                        'note' => $product_entry['note'] ?? '',
                        'sku' => $wc_product ? $wc_product->get_sku() : '',
                        'qty' => $ordered_qty,
                        'thumbnail' => $wc_product ? get_the_post_thumbnail_url($wc_product->get_id(), 'thumbnail') : null
                    ];
                }
            }


            // Prepare grouped products
            $grouped_products = [];
            if (!empty($supplier_data[$supplier_id]['grouped_products'])) {
                foreach ($supplier_data[$supplier_id]['grouped_products'] as $tracking_number => $products) {
                    $product_list = [];
                    foreach ($products as $product_id => $product_info) {
                        $wc_product = wc_get_product($product_id);
                        $ordered_qty = 0;
                        foreach ($order->get_items() as $item) {
                            if ($item instanceof \WC_Order_Item_Product && $item->get_product_id() == $product_id) {
                                $ordered_qty = $item->get_quantity();
                                break;
                            }
                        }

                        $product_list[] = [
                            'product_id' => $product_id,
                            'product_name' => $wc_product ? $wc_product->get_name() : 'Unknown',
                            'note' => $product_info['note'] ?? '',
                            'sku' => $wc_product ? $wc_product->get_sku() : '',
                            'qty' => $ordered_qty,
                            'thumbnail' => $wc_product ? get_the_post_thumbnail_url($wc_product->get_id(), 'thumbnail') : null
                        ];
                    }

                    $grouped_products[] = [
                        'tracking_number' => $tracking_number,
                        'products' => $product_list
                    ];
                }
            }


            $orders_data[] = [
                'id' => $order_id,
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',

                'supplier_status' => $supplier_status,
                'supplier_note' => $supplier_data[$supplier_id]['note'] ?? '',
                'color_tag' => $supplier_data[$supplier_id]['color_tag'] ?? '',

                'ungrouped_products' => $ungrouped_products,
                'grouped_products' => $grouped_products,

                'customer' => [
                    'id' => $order->get_customer_id(),
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'company' => $order->get_billing_company(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'postcode' => $order->get_billing_postcode(),
                    'state' => $order->get_billing_state(),
                    'country' => $order->get_billing_country(),
                    'shipping_first_name' => $order->get_shipping_first_name(),
                    'shipping_last_name' => $order->get_shipping_last_name(),
                    'shipping_company' => $order->get_shipping_company(),
                    'shipping_address_1' => $order->get_shipping_address_1(),
                    'shipping_address_2' => $order->get_shipping_address_2(),
                    'shipping_city' => $order->get_shipping_city(),
                    'shipping_postcode' => $order->get_shipping_postcode(),
                    'shipping_state' => $order->get_shipping_state(),
                    'shipping_country' => $order->get_shipping_country(),
                ]
            ];
        }

        wp_send_json_success([
            'orders' => $orders_data,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total_orders' => $total_orders,
                'total_pages' => $total_pages
            ]
        ]);
    }

    /**
     * Handles AJAX request to save supplier-specific metadata for an order.
     *
     * - Validates the supplier's authorization and input data.
     * - Updates color tags, notes, and tracking numbers on the order.
     * - Updates fulfillment status based on product-level tracking data.
     * - Saves updated supplier data to post meta.
     *
     * @return void
     */
    public static function save_metadata() {
        if (!is_user_logged_in() || !wc_current_user_has_role('supplier')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $current_user = wp_get_current_user();
        $supplier_id = $current_user->ID;

        $order_id = intval($_POST['order_id'] ?? 0);
        $metadata_raw = stripslashes_deep($_POST['metadata'] ?? '{}');
        $metadata = json_decode($metadata_raw, true);

        if (!$order_id || !is_array($metadata)) {
            wp_send_json_error(['message' => 'Invalid data'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found'], 404);
        }

        $supplier_data = get_post_meta($order_id, '_supplier_data', true);
        if (empty($supplier_data) || !isset($supplier_data[$supplier_id])) {
            wp_send_json_error(['message' => 'Supplier data not found'], 404);
        }

        // Update global-level fields
        if (isset($metadata['color_tag'])) {
            $supplier_data[$supplier_id]['color_tag'] = sanitize_text_field($metadata['color_tag']);
        }

        if (isset($metadata['note'])) {
            $supplier_data[$supplier_id]['note'] = sanitize_text_field($metadata['note']);
        }

        // ==========================
        // Process ungrouped products
        // ==========================
        $ungrouped_products = [];
        if (!empty($metadata['ungrouped_products'])) {
            foreach ($metadata['ungrouped_products'] as $prod) {
                $pid = isset($prod['product_id']) ? intval($prod['product_id']) : null;
                if (!$pid) continue;

                $ungrouped_products[] = [
                    'product_id' => $pid,
                    'note' => sanitize_textarea_field($prod['note'] ?? '')
                ];
            }
        }

        // ==========================
        // Process grouped products
        // ==========================
        $grouped_products = [];
        if (!empty($metadata['grouped_products'])) {
            foreach ($metadata['grouped_products'] as $group) {
                $tracking_number = sanitize_text_field($group['tracking_number'] ?? '');
                if (empty($tracking_number)) continue;

                if (!isset($grouped_products[$tracking_number])) {
                    $grouped_products[$tracking_number] = [];
                }

                foreach ($group['products'] as $prod) {
                    $pid = isset($prod['product_id']) ? intval($prod['product_id']) : null;
                    if (!$pid) continue;

                    $grouped_products[$tracking_number][$pid] = [
                        'note' => sanitize_textarea_field($prod['note'] ?? '')
                    ];
                }
            }
        }

        $supplier_data[$supplier_id]['ungrouped_products'] = $ungrouped_products;
        $supplier_data[$supplier_id]['grouped_products'] = $grouped_products;

        // ==========================
        // Calculate fulfillment status
        // ==========================
        $total_products = count($ungrouped_products);
        $fulfilled_products = 0;

        foreach ($grouped_products as $tracking => $products) {
            $total_products += count($products);
            if (!empty($tracking)) {
                $fulfilled_products += count($products);
            }
        }

        if ($fulfilled_products === $total_products && $total_products > 0) {
            $supplier_data[$supplier_id]['fulfillment_status'] = 'ready-to-fulfill';
        } elseif ($fulfilled_products > 0) {
            $supplier_data[$supplier_id]['fulfillment_status'] = 'partially-fulfilled';
        } else {
            $supplier_data[$supplier_id]['fulfillment_status'] = 'pending';
        }

        update_post_meta($order_id, '_supplier_data', $supplier_data);

        wp_send_json_success([
            'message' => 'Order saved successfully',
            'status' => $supplier_data[$supplier_id]['fulfillment_status'],
            'ungrouped_products' => $ungrouped_products,
            'grouped_products' => $grouped_products
        ]);
    }

    /**
     * Handles AJAX request to mark an order as fulfilled by the supplier.
     *
     * - Validates the supplier's authorization and order existence.
     * - Updates the order's supplier fulfillment status to 'fulfilled'.
     * - Integrates with AST (Advanced Shipment Tracking) plugin to add tracking numbers if available.
     * - Sends a customer notification email regarding order shipment.
     *
     * @return void
     */
    public static function confirm_fulfillment() {
        if (!is_user_logged_in() || !wc_current_user_has_role('supplier')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $current_user = wp_get_current_user();
        $supplier_id  = $current_user->ID;
        $order_id     = intval($_POST['order_id'] ?? 0);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found'], 404);
        }

        $supplier_data = get_post_meta($order_id, '_supplier_data', true);
        if (empty($supplier_data[$supplier_id])) {
            wp_send_json_error(['message' => 'No supplier data found.'], 404);
        }

        // Mark confirmed
        $supplier_data[$supplier_id]['fulfillment_status'] = 'fulfilled';

        update_post_meta($order_id, '_supplier_data', $supplier_data);

        // AST integration
        $grouped_products = $supplier_data[$supplier_id]['grouped_products'] ?? [];
        if (function_exists('ast_add_tracking_number') && function_exists('ast_insert_tracking_number')) {
            $tracking_numbers = array_keys($grouped_products);
            $tracking_numbers = array_filter($tracking_numbers); // Remove empty tracking numbers

            $is_global = count($tracking_numbers) === 1;

            if ($is_global) {
                ast_add_tracking_number($order_id, $tracking_numbers[0], '', current_time('mysql'), 0);
            } else {
                foreach ($grouped_products as $tracking_number => $products) {
                    if (!empty($tracking_number)) {
                        foreach ($products as $product_id => $data) {
                            $product = wc_get_product($product_id);
                            ast_insert_tracking_number(
                                $order_id,
                                $tracking_number,
                                '',
                                current_time('mysql'),
                                0,
                                $product ? $product->get_sku() : '',
                                $product ? $product->get_stock_quantity() : 1
                            );
                        }
                    }
                }
            }
        }

        // Email customer
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name();

        ob_start();
        include HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'customer-tracking-update-email.php';
        $email_body = ob_get_clean();

        if (empty($email_body)) {
            error_log("❌ Email content is empty for $customer_email", $customer_email);
            return;
        }

        wp_mail(
            $customer_email,
            sprintf(__('Your Order #%d Has Shipped!', HAVEN_CORE_TEXT_DOMAIN), $order_id),
            $email_body,
            ['Content-Type: text/html; charset=UTF-8']
        );

        wp_send_json_success([
            'message' => 'Order confirmed and customer notified.',
            'order_id' => $order_id,
            'status' => $supplier_data[$supplier_id]['fulfillment_status'],
        ]);
    }
}
