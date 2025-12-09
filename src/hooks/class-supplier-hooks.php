<?php

namespace HavenCore\Hooks;

use HavenCore\Classes\HC_Settings;
use HavenCore\Services\HC_Supplier_Service;

/**
 * Class SupplierHooks
 *
 * Registers background job hooks related to supplier workflows.
 *
 * @package HavenCore\Hooks
 */
class SupplierHooks
{
    /**
     * Register all background/async hooks related to suppliers.
     *
     * @return void
     */
    public static function register(): void
    {
		add_action(
			'havencore_send_supplier_welcome_email',
			[self::class, 'handleWelcomeEmailJob'],
			10,
			3 // ✅ Accept 3 arguments
		);

		add_action(
			'havencore_notify_admin_supplier_product_update',
			[self::class, 'sendSupplierProductUpdateAlert'],
			10,
			1
		);


        add_filter('login_redirect', [self::class, 'redirectSupplierLogin'], 10, 3);
        add_action('admin_init', [self::class, 'blockWpAdminForSuppliers']);
        add_filter('template_include', [self::class, 'restrictSupplierFromMyAccount']);
        add_action('wp_footer', [self::class, 'custom_supplier_nav_bar']);

        if (class_exists('AST_Pro_Actions') || class_exists('AST_Actions')) {
            add_action('ast_after_delete_tracking_item', [self::class, 'handle_reset_supplier_fulfillment_and_order_status'], 10, 2);
        } 
    }

    public static function custom_supplier_nav_bar() {
        // Check if the user has the 'supplier' role
        if (wc_current_user_has_role('supplier')) {
            // List of paths to check against
            $exclude_paths = ['/supplier-portal', '/checkout', '/cart', '/order-received']; 

            // Check if the current page URL does NOT start with any of the paths in the list
            $show_nav_bar = true;
            foreach ($exclude_paths as $path) {
                if (strpos($_SERVER['REQUEST_URI'], $path) === 0) {
                    $show_nav_bar = false;
                    break;
                }
            }

            // If the page doesn't match any of the exclude paths, show the navigation bar
            if ($show_nav_bar) {
                ?>
                <link rel="stylesheet" href="<?= HAVEN_CORE_URL . 'assets/css/primeicons/primeicons.css'; ?>">

                <div class="supplier-admin-bar">
                    <ul>
                        <li>
                            <a href="/supplier-portal">
                                <i class="pi pi-chart-bar"></i> <!-- PrimeIcons Chart Bar Icon -->
                                <?php echo esc_html(__('Dashboard', HAVEN_CORE_TEXT_DOMAIN)); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo wp_logout_url(); ?>">
                                <i class="pi pi-sign-out"></i> <!-- PrimeIcons Logout Icon -->
                                <?php echo esc_html(__('Logout', HAVEN_CORE_TEXT_DOMAIN)); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <style>
                    .supplier-admin-bar {
                        position: fixed;
                        overflow-x: hidden;
                        bottom: -60px; /* Initially off-screen at the bottom */
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(255, 255, 255, 0.4); /* Semi-transparent white background for frosted effect */
                        color: #333;
                        padding: 12px 25px; /* Adjusted padding for better balance */
                        border-radius: 50px; /* Smooth, more rounded corners */
                        border: 2px solid rgba(255, 255, 255, 0.5); /* Soft glassy border */
                        box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.15), inset 0px 0px 15px rgba(0, 0, 0, 0.1); /* Softer shadow for elegance */
                        backdrop-filter: blur(10px); /* Adds frosted glass effect */
                        z-index: 1000;
                        display: flex;
                        justify-content: center;
                        width: 65%;
                        max-width: 400px;
                        transition: bottom 0.6s ease, transform 0.6s ease, opacity 0.6s ease;
                        opacity: 0; /* Start hidden */
                    }

                    .supplier-admin-bar.show {
                        bottom: 20px; /* Slide to visible position */
                        opacity: 1; /* Make it visible */
                    }

                    .supplier-admin-bar ul {
                        display: flex;
                        justify-content: center;
                        margin: 0;
                        padding: 0;
                        width: 100%;
                        gap: 20px; /* Adds space between the list items */
                    }

                    .supplier-admin-bar li {
                        list-style: none;
                    }

                    .supplier-admin-bar a {
                        display: inline-flex; /* Changed to inline-flex to avoid unnecessary extra width */
                        gap: 10px; /* Adds space between the list items */
                        align-items: center; /* Centers the icon and text vertically */
                        justify-content: center; /* Ensures content is horizontally centered */
                        color: #333;
                        text-decoration: none;
                        font-weight: 700;
                        font-size: 15px;
                        font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Updated to Poppins font */
                        padding: 14px 25px;
                        border-radius: 40px; /* Rounded buttons with a softer curve */
                        transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
                        position: relative; /* Ensures control over the internal positioning */
                    }

                    .supplier-admin-bar a .pi {
                        font-size: 20px;
                        color: #555;
                        flex-shrink: 0; /* Prevents the icon from shrinking */
                    }

                    .supplier-admin-bar a span {
                        white-space: nowrap; /* Prevents the text from wrapping to the next line */
                    }

                    .supplier-admin-bar a:hover {
                        box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2); /* Stronger shadow with increased spread */
                        transform: translateY(-4px); /* Slightly stronger lift effect */
                    }

                    /* Responsive styles */
                    @media (max-width: 600px) {
                        .supplier-admin-bar {
                            padding: 15px 25px; /* Increased padding for mobile for more touch-friendly design */
                            width: 90%;
                            max-width: 600px;
                        }

                        .supplier-admin-bar a {
                            padding: 12px 20px; /* Adjust button size for mobile */
                        }
                    }
                </style>

                <script>
                    // Add a class to trigger the transition for the supplier admin bar
                    window.addEventListener('DOMContentLoaded', function () {
                        const bar = document.querySelector('.supplier-admin-bar');
                        bar.classList.add('show');
                    });
                </script>

                <?php
            }
        }
    }


    private static function log(string $message, string $email): void
    {
        if (!class_exists('\ActionScheduler_Logger')) {
            error_log("[Fallback Log] $message");
            return;
        }
    
        $action_id = (int) get_option("_supplier_email_action_{$email}");
    
        if ($action_id) {
            \ActionScheduler_Logger::instance()->log($action_id, $message);
        } else {
            error_log("[No Action ID] $message");
        }
    }
    


    /**
     * Sends the welcome email to a supplier asynchronously.
     *
     * Triggered by Action Scheduler via `havencore_send_supplier_welcome_email`.
     *
     * @param array $args {
     *     @type string $email         Supplier's email address.
     *     @type string $supplier_name Supplier's name.
     *     @type string $user_id       Supplier's User ID.
     * }
     *
     * @return void
     */
    public static function handleWelcomeEmailJob(string $email, string $supplier_name, string $user_id): void
    {
        if (!(new HC_Settings())->get('notifications.supplier_welcome_email', true)) {
            return;
        }

        self::log("🚀 handleWelcomeEmailJob started for $email", $email);
    
        if (empty($email) || empty($supplier_name) || empty($user_id)) {
            self::log("❌ Missing email, supplier_name, or user_id.", $email);
            return;
        }

        // ✅ Retrieve the temporary password stored in user meta
        $password = get_user_meta((int) $user_id, '_temporary_supplier_password', true);
        delete_user_meta((int) $user_id, '_temporary_supplier_password');

        if (empty($password)) {
            self::log("⚠️ No password found for user ID $user_id", $email);
            return;
        }
    
        $site_name = get_bloginfo('name');
        $subject   = 'Welcome to ' . $site_name;
        $headers   = ['Content-Type: text/html; charset=UTF-8'];
    
        ob_start();
        include HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'new-supplier-welcome.php';
        $message = ob_get_clean();
    
        if (empty($message)) {
            self::log("❌ Email content is empty for $email", $email);
            return;
        }
    
        $sent = wp_mail($email, $subject, $message, $headers);
    
        if ($sent) {
            self::log("✅ Welcome email successfully sent to $email", $email);

            // Update supplier record to mark welcome email sent
            $service = new HC_Supplier_Service();
            $supplier = $service->get_by_email($email);

            if ($supplier) {
                $timestamp = current_time('mysql');

                // Safely fetch, modify, and set updated attributes
                $attributes = $supplier->get_attributes();
                $attributes['greeting_email_sent_at'] = $timestamp;
                $supplier->set_attributes($attributes);
                $supplier->save();

                self::log("📌 Supplier updated with greeting_email_sent_at: $timestamp", $email);
            } else {
                self::log("⚠️ Could not find supplier to update for $email", $email);
            }
        
        } else {
            self::log("❌ Failed to send welcome email to $email", $email);
        }
        
    
        // ✅ Only delete the action ID after all logging is complete
        delete_option("_supplier_email_action_{$email}");
    }


	/**
	 * Sends an alert email to the site administrator whenever a supplier updates product inventory data.
	 *
	 * Triggered asynchronously via `havencore_notify_admin_supplier_product_update`.
	 *
	 * @param array $args {
	 *     @type array $payload The email context assembled by the REST controller.
	 * }
	 * @return void
	 */
	public static function sendSupplierProductUpdateAlert( $args ): void {
		$settings = new HC_Settings();
		if ( ! $settings->get( 'notifications.notify_admin_supplier_product_updates', true ) ) {
			return;
		}

		$payload = is_array( $args ) && array_key_exists( 'payload', $args ) ? $args['payload'] : ( is_array( $args ) ? $args : [] );
		if ( empty( $payload ) || empty( $payload['changes'] ) ) {
			return;
		}

		$recipient = apply_filters( 'havencore/supplier_update_alert_recipient', get_option( 'admin_email' ), $payload );
		if ( ! $recipient || ! is_email( $recipient ) ) {
			return;
		}

		$template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'supplier-product-update-admin-alert.php';
		if ( ! is_readable( $template_path ) ) {
			error_log( '[' . PLUGIN_NAME . '] Missing supplier product update email template.' );
			return;
		}

		$supplier     = $payload['supplier'] ?? [];
		$product      = $payload['product'] ?? [];
		$changes      = $payload['changes'] ?? [];
		$actor        = $payload['actor'] ?? $supplier;
		$triggered_at = $payload['triggered_at'] ?? current_time( 'mysql' );

		$subject_product_label = $product['name'] ?? ( $product['sku'] ?? ( '#' . ( $product['id'] ?? '' ) ) );
		$subject = sprintf( '[%s] Supplier update: %s', get_bloginfo( 'name' ), $subject_product_label );
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		ob_start();
		include $template_path;
		$message = ob_get_clean();

		if ( empty( $message ) ) {
			return;
		}

		wp_mail( $recipient, $subject, $message, $headers );
	}


    /*
     * Redirect suppliers to their portal after login.
     */
    public static function redirectSupplierLogin($redirect_to, $request, $user)
    {
        if (isset($user->roles) && in_array('supplier', $user->roles, true)) {
            return home_url('/supplier-portal');
        }
        return $redirect_to;
    }

    /**
     * Prevent suppliers from accessing /wp-admin directly.
     */
    public static function blockWpAdminForSuppliers(): void
    {
        if (
            is_user_logged_in() &&
            current_user_can('supplier') &&
            !defined('DOING_AJAX') &&
            !wp_doing_ajax() &&
            is_admin() &&                      // <- important!
            !wp_doing_cron()
        ) {
            wp_redirect(home_url('/supplier-portal'));
            exit;
        }
    }

    /**
     * Block supplier users from accessing WooCommerce my-account page.
     *
     * @param string $template The path to the template file that will be loaded.
     * @return string The modified template path.
     */
    public static function restrictSupplierFromMyAccount($template)
    {
        if (is_account_page() && is_user_logged_in()) {
            $user = wp_get_current_user();
            
            // Check if the current user has the 'supplier' role
            if (in_array('supplier', $user->roles)) {
                // Redirect to the supplier portal or another page
                wp_redirect(home_url('/supplier-portal'));
                exit;
            }
        }

        return $template;
    }


    /**
     * Manually Modified `meta_box_delete_tracking` inside the `AST_Pro_Actions` class.
     * 
     * Location: wp-content/plugins/ast-pro/includes/class-ast-pro-actions.php
     * 
     * Changes made:
     * - Added the action hook `ast_after_delete_tracking_item` after the deletion of tracking numbers.
     * - Triggered the hook to allow for custom actions (in this case, resetting supplier fulfillment status and order status).
     * - This custom action allows for better integration with the order fulfillment process and ensures the order and supplier statuses are correctly updated when a tracking number is deleted.
     *
     * 
     *  the code we have added:
     * ---
     * `
     * do_action('ast_after_delete_tracking_item', $order_id, $tracking_number); // Hook triggered after deletion
     * `
     * 
     * The function below listens for the `ast_after_delete_tracking_item` hook, 
     * and upon deletion of a tracking number, updates the supplier fulfillment status accordingly:
     * 1. Clears the tracking number for the product.
     * 2. Checks if any other products are fulfilled; if not, sets the supplier to 'pending'.
     * 3. Checks if there is at least one fulfilled supplier, and updates the overall order status to 'processing' or 'partially shipped'.
     *
     * This modification is required for the custom order management functionality related to supplier fulfillment.
     *
     * @see AST_Pro_Actions::meta_box_delete_tracking in class-ast-pro-actions.php
     */
    public static function handle_reset_supplier_fulfillment_and_order_status($order_id, $tracking_number) {
        // error_log("🔍 Starting reset process for Order ID: {$order_id} and Tracking Number: {$tracking_number}");

        $supplier_data = get_post_meta($order_id, '_supplier_data', true);

        if (empty($supplier_data) || !is_array($supplier_data)) {
            // error_log("⚠️ No supplier data found or data is not an array for Order ID: {$order_id}.");
            return;
        }

        $tracking_number_deleted = false;
        $updated_supplier_data = $supplier_data;

        foreach ($updated_supplier_data as $supplier_id => $data) {
            // error_log("➡️ Checking supplier ID: {$supplier_id}");

            if (!empty($data['grouped_products']) && is_array($data['grouped_products'])) {
                foreach ($data['grouped_products'] as $group_index => $group) {
                    $group_tracking_number = strval($group_index);
                    // error_log("🔎 Checking group at index {$group_index} with tracking number: {$group_tracking_number}");

                    if ($group_tracking_number === strval($tracking_number)) {
                        // error_log("✅ Found matching group with tracking number {$group_tracking_number}. Moving products back to ungrouped.");

                        if (!isset($updated_supplier_data[$supplier_id]['ungrouped_products'])) {
                            $updated_supplier_data[$supplier_id]['ungrouped_products'] = [];
                        }

                        // Move each product in this group to ungrouped, formatting as originally created
                        foreach ($group as $product_id => $product_data) {
                            $updated_supplier_data[$supplier_id]['ungrouped_products'][] = [
                                'product_id'   => $product_id,
                                'variation_id' => isset($product_data['variation_id']) ? (int) $product_data['variation_id'] : null,
                                'quantity'     => isset($product_data['quantity']) ? (int) $product_data['quantity'] : 1,
                                'note'         => ''
                            ];
                        }

                        unset($updated_supplier_data[$supplier_id]['grouped_products'][$group_index]);
                        if (isset($updated_supplier_data[$supplier_id]['tracking_groups_meta'][$group_tracking_number])) {
                            unset($updated_supplier_data[$supplier_id]['tracking_groups_meta'][$group_tracking_number]);
                        }

                        $tracking_number_deleted = true;
                        // error_log("✅ Group removed. Products moved to ungrouped. Updated supplier data prepared.");
                        break;
                    }
                }
            }

            // Update fulfillment status for this supplier
            if ($tracking_number_deleted) {
                $has_other_fulfilled = false;
                foreach ($updated_supplier_data[$supplier_id]['grouped_products'] as $group) {
                    if (!empty($group['tracking_number'])) {
                        $has_other_fulfilled = true;
                        break;
                    }
                }

                $updated_supplier_data[$supplier_id]['fulfillment_status'] = $has_other_fulfilled
                    ? 'partially-fulfilled'
                    : 'pending';

                // error_log("📦 Updated fulfillment status for supplier ID {$supplier_id} to: " . $updated_supplier_data[$supplier_id]['fulfillment_status']);
            }
        }

        if ($tracking_number_deleted) {
            $all_unfulfilled = true;
            foreach ($updated_supplier_data as $check_supplier_id => $check_data) {
                if (!empty($check_data['fulfillment_status']) && $check_data['fulfillment_status'] === 'fulfilled') {
                    $all_unfulfilled = false;
                    break;
                }
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                error_log("❌ Order not found for ID: {$order_id}");
                return;
            }

            if ($all_unfulfilled) {
                $order->update_status('processing', __('All suppliers have unfulfilled items. Order status reverted to processing.', 'your-text-domain'));
                $order->add_order_note(__('All suppliers have unfulfilled items. Status reset to processing.', 'your-text-domain'));
                // error_log("🔄 All suppliers are unfulfilled. Order status set to processing.");
            } else {
                $order->update_status('processing', __('One tracking number was removed, but other suppliers are fulfilled.', 'your-text-domain'));
                $order->add_order_note(__('One or more suppliers have fulfilled items, status set to processing.', 'your-text-domain'));
                // error_log("🔄 Some suppliers are still fulfilled. Order status set to processing.");
            }

            update_post_meta($order_id, '_supplier_data', $updated_supplier_data);
            // error_log("✅ Supplier data updated successfully for Order ID: {$order_id}: " . print_r($updated_supplier_data, true));
        } else {
            // error_log("ℹ️ No matching tracking number found to delete for Order ID: {$order_id}.");
        }

        // error_log("🎉 Finished processing Order ID: {$order_id}");
    }

}
