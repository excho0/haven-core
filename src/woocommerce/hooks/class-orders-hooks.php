<?php

namespace HavenCore\WooCommerce\Hooks;

use HavenCore\Services\EmailVerificationService;
use HavenCore\Services\HC_Supplier_Service;
use HavenCore\Settings\WooCommerce as WCSettings;
use HavenCore\Settings\Notifications;
use WC_Order;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Orders
 *
 * Handles WooCommerce admin-side logic for orders, including:
 * - Registering async supplier email jobs after checkout
 * - Grouping products by supplier
 * - Sending supplier-specific order fulfillment emails
 *
 * Hooks:
 * - `woocommerce_checkout_order_processed`: Schedules async supplier notification.
 * - `havencore_supplier_order_assignment`: Triggers supplier email dispatching.
 *
 * @package HavenCore\WooCommerce\Hooks
 */
class Orders
{
    /**
     * Helper method to log Action Scheduler messages.
     *
     * @param string $message
     * @param int $action_id
     * @return void
     */
    private static function log(string $message, int $action_id = 0, int $order_id = 0): void
    {
        if (!$action_id && $order_id) {
            // Try to load action_id from order meta if not directly passed
            $order = wc_get_order($order_id);
            $stored_action_id = $order ? $order->get_meta('_supplier_email_action_id', true) : '';
            if ($stored_action_id) {
                $action_id = (int) $stored_action_id;
            }
        }
    
        if (class_exists('ActionScheduler_Logger')) {
            $logger = \ActionScheduler_Logger::instance();
            $logger->log($action_id, $message);
        } else {
            error_log("[No Action ID]" . $message);
        }
    }
    

    /**
     * Register all WooCommerce order-related hooks and logic.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;

        // Trigger supplier job when a real payment is completed via any online gateway
        add_action('woocommerce_payment_complete', [self::class, 'schedule_supplier_email_job'], 10, 1);

        // Handle supplier assignment job (async) after it has been queued via Action Scheduler
        add_action('havencore_supplier_order_assignment', [self::class, 'handle_supplier_order_creation']);
        add_action('havencore_supplier_order_reassignment', [self::class, 'handle_supplier_reassignment_email'], 10, 2);

        // Send payment link email to customer when status is manually changed to 'pending' from admin
        add_action('woocommerce_order_status_changed', [self::class, 'send_customer_payment_email_on_manual_status_change'], 10, 4);

        // Catch manual/COD/late payments when status changes from 'pending' to 'processing'
        add_action('woocommerce_order_status_pending_to_processing', [self::class, 'schedule_supplier_email_job'], 10, 1);

        if (WCSettings::accountSecurityFlowEnabled()) {
            add_action('woocommerce_thankyou', [self::class, 'handle_guest_checkout_verification'], 25, 1);
        }

        add_action('woocommerce_checkout_order_processed', [self::class, 'assign_registered_user_to_guest_order'], 10, 1);
    }
    

    /**
     * Schedule async job to notify suppliers after checkout.
     *
     * @param int $order_id
     * @return void
     */
    public static function schedule_supplier_email_job($order_id): void
    {
        $order_id = (int) $order_id;
        self::log("🕓 schedule_supplier_email_job for order ID: $order_id", 0, $order_id);
    
        if (!$order_id) {
            self::log("⛔ Invalid order ID $order_id", 0, $order_id);
            return;
        }
        
        if (function_exists('as_enqueue_async_action')) {
            $action_id = as_enqueue_async_action(
                'havencore_supplier_order_assignment',
                [$order_id],
                'hc-assign-orders-to-suppliers' // <-- Action group name
            );            
            self::log("✅ [ActionScheduler] Enqueued supplier job for order $order_id, action ID: $action_id", $action_id, $order_id);
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_supplier_email_action_id', $action_id);
                $order->save();
            }
        } else {
            wp_schedule_single_event(time() + 10, 'havencore_supplier_order_assignment', [$order_id]);
            self::log("⚠️ Action Scheduler not found. Falling back to wp_schedule_single_event for order $order_id", 0, $order_id);
        }
        
    }

    public static function schedule_supplier_reassignment_email(int $order_id, int $supplier_id): void
    {
        $order_id    = (int) $order_id;
        $supplier_id = (int) $supplier_id;

        if (!$order_id || !$supplier_id) {
            return;
        }

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                'havencore_supplier_order_reassignment',
                [$order_id, $supplier_id],
                'hc-assign-orders-to-suppliers'
            );
        } else {
            wp_schedule_single_event(time() + 10, 'havencore_supplier_order_reassignment', [$order_id, $supplier_id]);
        }
    }
    
    

    /**
     * Processes the supplier email job, grouping items by supplier and sending notifications.
     *
     * @param int $order_id
     * @return void
     */
    public static function handle_supplier_order_creation($order_id): void
    {
        $order_id = (int) $order_id;
        self::log("📦 Handling supplier order creation for order ID: $order_id", 0, $order_id);

    
        if (!$order_id) return;
    
        $order = wc_get_order($order_id);
        if (!$order || !is_a($order, WC_Order::class)) {
            self::log("❌ Invalid WC_Order object for order ID $order_id", 0, $order_id);
            return;
        }
    
        if ($order->get_meta('_supplier_data_created', true)) {
            self::log("✅ Order $order_id already marked as processed. Skipping.", 0, $order_id);
            return;
        }
    
        $suppliers = [];
    
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            if (!$product) {
                self::log("❌ Could not load product for ID $product_id", 0, $order_id);
                continue;
            }

            $supplier_id = $product->get_meta('_supplier_id', true);

            if ($supplier_id) {
                if (!isset($suppliers[$supplier_id])) {
                    $suppliers[$supplier_id] = [
                        'ungrouped_products'     => [],
                        'tracking_groups_meta'   => [],
                    ];
                }

                $variation_id = (int) $item->get_variation_id();
                $quantity = max(1, (int) $item->get_quantity());

                $suppliers[$supplier_id]['ungrouped_products'][] = [
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id ?: null,
                    'quantity'     => $quantity,
                    'note'         => ''
                ];
            }
        }
    
        if (empty($suppliers)) {
            self::log("⚠️ No suppliers found for order $order_id", 0, $order_id);
            return;
        }
    
        foreach ($suppliers as $supplier_id => &$supplier_data) {
            $supplier_data['fulfillment_status'] = 'pending';

            $service = new HC_Supplier_Service();
            $supplier = $service->get($supplier_id);
            

            if ($supplier) {
                $supplier_id = $supplier->get_id();
                $service->assign_orders($supplier_id, [$order_id]);
            } else {
                self::log("❌ Could not load supplier $supplier_id to assign order $order_id", 0, $order_id);
            }

            self::log("📨 Sending email to supplier ID $supplier_id for order $order_id", 0, $order_id);
            self::send_supplier_email($order, $supplier_id, $supplier_data);
        }
    
        $order->update_meta_data('_supplier_data', $suppliers);
        $order->update_meta_data('_supplier_data_created', true);
        $order->delete_meta_data('_supplier_email_action_id'); // ✅ cleanup
        $order->save();
        self::log("✅ Supplier data marked as created for order $order_id", 0, $order_id);
    }

    public static function handle_supplier_reassignment_email($order_id, $supplier_id): void
    {
        $order_id    = (int) $order_id;
        $supplier_id = (int) $supplier_id;

        if (!$order_id || !$supplier_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || !is_a($order, WC_Order::class)) {
            return;
        }

        $supplier_data = $order->get_meta('_supplier_data', true);
        $segment       = [];

        if (is_array($supplier_data) && isset($supplier_data[$supplier_id]) && is_array($supplier_data[$supplier_id])) {
            $segment = $supplier_data[$supplier_id];
        }

        self::send_supplier_email($order, (string) $supplier_id, $segment, 'reassignment');
    }
    


    /**
     * Sends email to the supplier with order details and access link.
     *
     * @param WC_Order $order
     * @param string $supplier_id
     * @param array $supplier_data
     * @return void
     */
    public static function send_supplier_email(WC_Order $order, string $supplier_id, array $supplier_data, string $context = 'assignment'): void
    {
        $context_toggle_map = [
            'assignment'   => 'notifications.supplier_assignment_email',
            'reassignment' => 'notifications.supplier_reassignment_email',
        ];

        $toggle_key = $context_toggle_map[$context] ?? $context_toggle_map['assignment'];

        $enabled = true;
        if ($toggle_key === 'notifications.supplier_assignment_email') {
            $enabled = Notifications::supplierAssignmentEmailEnabled();
        } elseif ($toggle_key === 'notifications.supplier_reassignment_email') {
            $enabled = Notifications::supplierReassignmentEmailEnabled();
        }

        if (!$enabled) {
            return;
        }

        $order_id = $order->get_id();

        $service = new HC_Supplier_Service();
        $supplier = $service->get((int) $supplier_id);
    
        $supplier_email = $supplier->get_email();

        if (! $supplier_email) {
            self::log("❌ Supplier $supplier_id does not have a valid email address.", 0, $order_id);
            return;
        }
    
        $fulfillment_link = esc_url(home_url('/supplier-portal')) . '#/orders?order_id=' . urlencode($order_id);

    
        $subject = $context === 'reassignment'
            ? sprintf('Order #%d Has Been Reassigned to You', $order_id)
            : sprintf('New Order #%d Assigned to You', $order_id);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
    
        $template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH .'supplier-fulfillment-email.php';
    
        if (!is_readable($template_path)) {
            self::log("❌ Email template not found at $template_path.", 0, $order_id);
            return;
        }
    
        $products = $supplier_data['ungrouped_products'];
        $supplier_name = $supplier ? $supplier->get_name() : 'Unknown Supplier';
    
        ob_start();
        include $template_path;
        $message = ob_get_clean();
    
        if (empty($message)) {
            self::log("❌ Email message is empty for supplier $supplier_id, order $order_id.", 0, $order_id);
            return;
        }
    
        $sent = wp_mail($supplier_email, $subject, $message, $headers);
    
        if ($sent) {
            self::log("✅ Email successfully sent to $supplier_email for order $order_id.", 0, $order_id);
        } else {
            self::log("❌ Failed to send email to $supplier_email for order $order_id.", 0, $order_id);
        }
    }


    /**
     * Sends a payment reminder email to the customer when an order status is manually changed to "pending" by an admin.
     *
     * This hook ensures the email is only sent when:
     * - The status changes to "pending"
     * - The change occurs in the admin interface
     * - The customer has a valid billing email address
     * 
     * It uses a custom email template defined in `customer-payment-reminder-email.php`, located at
     * `HAVEN_CORE_EMAIL_TEMPLATES_PATH`. Email content is sent using wp_mail() with HTML headers.
     *
     * Logs success/failure using Action Scheduler logger if available.
     *
     * Hooked into: woocommerce_order_status_changed
     *
     * @param int      $order_id   The ID of the WooCommerce order.
     * @param string   $old_status The previous order status (e.g., 'on-hold').
     * @param string   $new_status The new order status (e.g., 'pending').
     * @param WC_Order $order      The WooCommerce order object.
     * 
     * @return void
     */
    public static function send_customer_payment_email_on_manual_status_change($order_id, $old_status, $new_status, $order): void
    {
        if (!Notifications::customerPaymentReminderEmailEnabled()) {
            return;
        }

        if ($new_status !== 'pending') {
            return;
        }

        // Check if this was likely triggered from the admin manually
        if (!is_admin() || !current_user_can('edit_shop_orders')) {
            return;
        }

        $customer_email = $order->get_billing_email();
        if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid customer email for order $order_id.", 0, $order_id);
            return;
        }

        $subject = sprintf('Your Order #%d – Payment Link', $order_id);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Path to your new template
        $template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'customer-payment-reminder-email.php';

        if (!is_readable($template_path)) {
            error_log("❌ Customer email template not found at $template_path.", 0, $order_id);
            return;
        }

        // Build the template context
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        if (empty($message)) {
            error_log("❌ Email message is empty for customer on order $order_id.", 0, $order_id);
            return;
        }

        $sent = wp_mail($customer_email, $subject, $message, $headers);

        if ($sent) {
            error_log("✅ Customer payment email sent to $customer_email for order $order_id.", 0, $order_id);
        } else {
            error_log("❌ Failed to send customer payment email for order $order_id.", 0, $order_id);
        }
    }

    /**
     * Capture guest checkout details and send the verification email if the feature is enabled.
     *
     * @param int $order_id
     * @return void
     */
    public static function handle_guest_checkout_verification($order_id): void
    {
        $order_id = (int) $order_id;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || !($order instanceof WC_Order)) {
            return;
        }

        // Skip already-linked users.
        if ($order->get_user_id()) {
            return;
        }

        $email = sanitize_email($order->get_billing_email());
        if (!$email) {
            return;
        }

        $billing_meta = self::extract_billing_meta($order);
        $meta_payload = [
            'billing'   => $billing_meta,
            'order_ids' => [$order_id],
        ];

        $service = new EmailVerificationService();
        $existing_request = EmailVerificationService::get_request_by_email($email);

        if ($existing_request) {
            $meta_payload = self::merge_meta_payload($existing_request['meta'] ?? [], $meta_payload);
            $existing_request['meta'] = $meta_payload;
            $existing_request['expires_at'] = current_time('timestamp') + DAY_IN_SECONDS;
            $existing_request['expire_date'] = wp_date('Y-m-d H:i:s', $existing_request['expires_at']);
            EmailVerificationService::save_request($existing_request['token'], $existing_request, DAY_IN_SECONDS);
            $record = $existing_request;
        } else {
            $record = $service->create_request([
                'username'   => self::generate_username_from_email($email),
                'user_email' => $email,
                'context'    => 'registration',
                'meta'       => $meta_payload,
            ], DAY_IN_SECONDS);
        }

        if (!$record) {
            return;
        }

        $verification_link = add_query_arg('token', $record['token'], home_url('/' . HAVEN_CORE_PASSWORD_RESET_SLUG . '/'));
        Customer::send_verification_email($email, $record['username'] ?? '', $verification_link);
    }

    /**
     * If a guest checks out using an email that already belongs to a registered user,
     * automatically attach the order to that account.
     *
     * @param int $order_id
     * @return void
     */
    public static function assign_registered_user_to_guest_order($order_id): void
    {
        $order_id = (int) $order_id;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_user_id()) {
            return;
        }

        $billing_email = sanitize_email($order->get_billing_email());
        if (!$billing_email) {
            return;
        }

        $user = get_user_by('email', $billing_email);
        if (!$user) {
            return;
        }

        $order->set_customer_id($user->ID);
        $order->save();
    }

    /**
     * Extract sanitized billing data from an order.
     *
     * @param WC_Order $order
     * @return array
     */
    private static function extract_billing_meta(WC_Order $order): array
    {
        $fields = [
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone',
            'billing_email',
        ];

        $data = [];
        foreach ($fields as $field) {
            $getter = 'get_' . $field;
            if (method_exists($order, $getter)) {
                $value = $order->{$getter}();
                if ($value !== '') {
                    $data[$field] = sanitize_text_field((string) $value);
                }
            }
        }

        return $data;
    }

    /**
     * Generate a safe username from an email address.
     *
     * @param string $email
     * @return string
     */
    private static function generate_username_from_email(string $email): string
    {
        $local_part = strstr($email, '@', true);
        $local_part = $local_part !== false ? $local_part : $email;

        $username = sanitize_user($local_part, true);

        return $username ?: sanitize_user('customer_' . wp_generate_password(6, false), true);
    }

    /**
     * Merge existing meta payload with newly captured data.
     *
     * @param array $existing
     * @param array $incoming
     * @return array
     */
    private static function merge_meta_payload(array $existing, array $incoming): array
    {
        $merged = $existing;

        $merged['billing'] = array_filter(
            array_merge($existing['billing'] ?? [], $incoming['billing'] ?? []),
            static fn($value) => $value !== ''
        );

        $existing_orders = array_map('absint', (array) ($existing['order_ids'] ?? []));
        $incoming_orders = array_map('absint', (array) ($incoming['order_ids'] ?? []));
        $merged['order_ids'] = array_values(array_unique(array_filter(array_merge($existing_orders, $incoming_orders))));

        return $merged;
    }
}
