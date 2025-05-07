<?php

namespace HavenCore\WooCommerce\Ajax;
use HavenCore\Classes\HC_Settings;


/**
 * Class CustomerCheckoutAjax
 *
 * Handles AJAX actions related to customer checkout.
 *
 * Registered via Woo_AjaxManager::registerAll().
 *
 * @package HavenCore\WooCommerce\Ajax
 */
class CustomerCheckoutAjax
{
    /**
     * Registers all AJAX actions related to customer checkout.
     */
    public static function register(): void
    {
        $settings = new HC_Settings();

        if ($settings->get('WooCommerce.order_review_before_payment', false)) {
            add_action('wp_ajax_havencore_place_order', [self::class, 'placeOrder']);
            add_action('wp_ajax_nopriv_havencore_place_order', [self::class, 'placeOrder']);
        }

        add_action('wp_ajax_havencore_get_available_shipping_methods', [self::class, 'getAvailableShippingMethods']);
        add_action('wp_ajax_nopriv_havencore_get_available_shipping_methods', [self::class, 'getAvailableShippingMethods']);
    }

    /**
     * Retrieves the available shipping methods based on the user's shipping address.
     *
     * - For **logged-in customers**: Uses the saved shipping location unless it's missing,
     *   in which case it falls back to the provided shipping form data.
     * - For **guest users**: Uses the shipping data from the form request.
     * - If no country is provided, the store's base country is used by default.
     *
     * @return void Outputs a JSON response containing the list of available shipping methods.
     *
     * @example
     * wp_send_json_success([
     *     'shipping_methods' => [
     *         [
     *             'id'          => 'flat_rate:5',
     *             'label'       => 'Flat rate',
     *             'cost'        => 9.99,
     *             'method_id'   => 'flat_rate',
     *             'instance_id' => 5,
     *             'zone_id'     => 2,
     *         ],
     *         ...
     *     ]
     * ]);
     *
     * @global \WC_Customer $woocommerce->customer
     * @global \WC_Cart $woocommerce->cart
     */
    public static function getAvailableShippingMethods(): void
    {
        $form = json_decode(stripslashes($_POST['form'] ?? ''), true);

        if (!is_array($form)) {
            wp_send_json_error(['message' => 'Invalid form format'], 400);
        }

        $shipping = wc_clean($form['shipping'] ?? []);
        $country  = $shipping['country'] ?? WC()->countries->get_base_country();
        $state    = $shipping['state'] ?? '';
        $postcode = $shipping['postcode'] ?? '';
        $city     = $shipping['city'] ?? '';

        // if (is_user_logged_in()) {
        //     $stored_country = WC()->customer->get_shipping_country();

        //     if (empty($stored_country)) {
        //         WC()->customer->set_location($country, $state, $postcode, $city);
        //         WC()->customer->set_shipping_location($country, $state, $postcode, $city);
        //     }
        // } else {
            WC()->customer->set_location($country, $state, $postcode, $city);
            WC()->customer->set_shipping_location($country, $state, $postcode, $city);
        // }

        WC()->cart->calculate_shipping();
        $packages = WC()->shipping()->get_packages();

        if (empty($packages)) {
            wp_send_json_error(['message' => 'No shipping packages found'], 404);
        }

        $available_methods = [];

        foreach ($packages as $package) {
            $zone    = wc_get_shipping_zone($package);
            $zone_id = $zone->get_id();

            foreach ($package['rates'] as $rate) {
                $available_methods[] = [
                    'id'          => $rate->get_id(),
                    'label'       => $rate->get_label(),
                    'cost'        => (float) $rate->get_cost(),
                    'method_id'   => $rate->get_method_id(),
                    'instance_id' => $rate->get_instance_id(),
                    'zone_id'     => $zone_id,
                ];
            }
        }

        wp_send_json_success(['shipping_methods' => $available_methods]);
    }


    /**
     * Handles placing an order via AJAX using WooCommerce's native checkout flow.
     *
     * @return void
     */
    public static function placeOrder(): void
    {
        $form = json_decode(stripslashes($_POST['form'] ?? ''), true);

        if (!isset($form['shipping'], $form['billing'], $form['email'])) {
            wp_send_json_error(['message' => 'Invalid input data'], 400);
        }

        $email              = sanitize_email($form['email']);
        $shipping_address   = wc_clean($form['shipping']);
        $billing_address    = wc_clean($form['billing']);
        $shipping_method_id = sanitize_text_field($form['shipping_method'] ?? '');
        $order_notes        = sanitize_textarea_field($form['orderNotes'] ?? '');

        // Set location for tax/shipping calculations
        WC()->customer->set_billing_location(
            $billing_address['country'] ?? '',
            $billing_address['state'] ?? '',
            $billing_address['postcode'] ?? '',
            $billing_address['city'] ?? ''
        );

        WC()->customer->set_shipping_location(
            $shipping_address['country'] ?? '',
            $shipping_address['state'] ?? '',
            $shipping_address['postcode'] ?? '',
            $shipping_address['city'] ?? ''
        );

        // Apply shipping method to session
        if ($shipping_method_id) {
            WC()->session->set('chosen_shipping_methods', [$shipping_method_id]);
        }

        // Apply any coupons
        foreach (WC()->cart->get_applied_coupons() as $coupon_code) {
            WC()->cart->apply_coupon($coupon_code);
        }

        // Calculate totals (now that we have all locations/methods/coupons)
        WC()->cart->calculate_totals();

        $checkout_data = [];

        foreach ($billing_address as $key => $value) {
            $checkout_data["billing_$key"] = $value;
        }

        foreach ($shipping_address as $key => $value) {
            $checkout_data["shipping_$key"] = $value;
        }

        $checkout_data['payment_method']  = 'manual';
        $checkout_data['shipping_method'] = [$shipping_method_id];
        $checkout_data['customer_note']   = $order_notes;
        $checkout_data['customer_id']     = get_current_user_id() ?: 0;

        // Create the order
        $checkout = WC()->checkout();
        $order_id = $checkout->create_order($checkout_data);

        if (is_wp_error($order_id)) {
            wp_send_json_error(['message' => $order_id->get_error_message()], 500);
        }

        $order = wc_get_order($order_id);
        $order->set_billing_email($email); // Email isn't always included in addresses
        $order->set_customer_note($order_notes);
        $order->update_status('on-hold', 'Awaiting admin approval for payment');
        $order->save();

        // Custom hooks (optional)
        $order_hooks = new \HavenCore\WooCommerce\Hooks\Orders();
        $order_hooks->schedule_supplier_email_job($order->get_id());

        WC()->cart->empty_cart();

        $customer_id = WC()->customer->get_id();

        if ($customer_id) {
            // Update the user's stored customer profile if necessary
            self::maybe_update_customer_profile($customer_id, $billing_address, $shipping_address, $email);
        }


        wp_send_json_success([
            'message'      => 'Order placed successfully!',
        ]);
    }

    /**
     * Updates the current WooCommerce customer's saved billing and shipping addresses
     * if any fields differ from the submitted form data.
     *
     * This ensures that next time the user places an order, their address data is pre-filled.
     *
     * @param int $current_user_id The currently logged-in user's ID.
     * @param array $billing_address The submitted billing address from the checkout form.
     * @param array $shipping_address The submitted shipping address from the checkout form.
     * @param string $email The submitted email address from the form.
     */
    private static function maybe_update_customer_profile(
        int $customer_id,
        array $billing_address,
        array $shipping_address,
        string $email
    ): void
    {
        if (!$customer_id) {
            return;
        }

        $customer = new \WC_Customer($customer_id);

        foreach ($billing_address as $key => $value) {
            $getter = "get_billing_$key";
            $setter = "set_billing_$key";

            if (method_exists($customer, $getter) && method_exists($customer, $setter)) {
                if ($customer->$getter() !== $value) {
                    $customer->$setter($value);
                }
            }
        }

        foreach ($shipping_address as $key => $value) {
            $getter = "get_shipping_$key";
            $setter = "set_shipping_$key";

            if (method_exists($customer, $getter) && method_exists($customer, $setter)) {
                if ($customer->$getter() !== $value) {
                    $customer->$setter($value);
                }
            }
        }

        // Optionally update user email (if it differs)
        if ($customer->get_email() !== $email) {
            $customer->set_email($email);
        }

        $customer->save(); // Persist changes
    }

}