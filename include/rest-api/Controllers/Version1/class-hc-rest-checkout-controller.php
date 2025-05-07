<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WC_Customer;


class HC_REST_CustomerCheckout_V1_Controller extends HC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'hc/v1/customers';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'checkout';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/shipping-methods',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_shipping_methods' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/place-order',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'place_order' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function get_shipping_methods( WP_REST_Request $request ) {
		$form = $request->get_json_params()['form'] ?? [];

		if ( ! is_array( $form ) ) {
			error_log( '[Shipping Debug] Invalid form format received.' );
			return new WP_Error( 'invalid_form', 'Invalid form format', [ 'status' => 400 ] );
		}

		// Load WooCommerce cart (this also initializes WC()->customer)
		if ( ! WC()->cart ) {
			error_log( '[Shipping Debug] WC()->cart not found. Calling wc_load_cart().' );
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
		}

		if ( ! WC()->cart ) {
			error_log( '[Shipping Debug] Failed to load cart. Aborting.' );
			return new WP_Error( 'no_cart', 'WooCommerce cart is not available', [ 'status' => 500 ] );
		}

		if ( WC()->cart->is_empty() ) {
			return new WP_Error( 'empty_cart', 'Cannot calculate shipping for empty cart.', [ 'status' => 422 ] );
		}


		if ( ! is_a( WC()->customer, 'WC_Customer' ) ) {
			error_log( '[Shipping Debug] WC()->customer not initialized. Aborting.' );
			return new WP_Error( 'no_customer', 'WooCommerce customer is not available', [ 'status' => 500 ] );
		}

		// Extract shipping address
		$shipping = wc_clean( $form['shipping'] ?? [] );
		$country  = $shipping['country'] ?? WC()->countries->get_base_country();
		$state    = $shipping['state'] ?? '';
		$postcode = $shipping['postcode'] ?? '';
		$city     = $shipping['city'] ?? '';

		error_log( '[Shipping Debug] Shipping location received: ' . json_encode( [
			'country'  => $country,
			'state'    => $state,
			'postcode' => $postcode,
			'city'     => $city,
		] ) );

		// Set customer shipping context
		WC()->customer->set_location( $country, $state, $postcode, $city );
		WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		error_log( '[Shipping Debug] Customer shipping location set.' );

		// Recalculate shipping
		WC()->cart->calculate_shipping();
		$packages = WC()->shipping()->get_packages();

		if ( empty( $packages ) ) {
			error_log( '[Shipping Debug] No shipping packages found after calculation.' );
			return new WP_Error( 'no_packages', 'No shipping options available', [ 'status' => 404 ] );
		}

		error_log( '[Shipping Debug] Shipping packages found: ' . count( $packages ) );

		$available_methods = [];

		foreach ( $packages as $package_index => $package ) {
			$zone    = wc_get_shipping_zone( $package );
			$zone_id = $zone->get_id();

			error_log( "[Shipping Debug] Package #{$package_index} - Zone ID: {$zone_id}" );

			foreach ( $package['rates'] as $rate ) {
				error_log( '[Shipping Debug] Found shipping method: ' . $rate->get_label() );

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

		error_log( '[Shipping Debug] Available methods: ' . json_encode( $available_methods ) );

		return new WP_REST_Response( [ 'shipping_methods' => $available_methods ], 200 );
	}



	public function place_order( WP_REST_Request $request ) {
		$form = $request->get_json_params()['form'] ?? [];

		if ( ! isset( $form['shipping'], $form['billing'], $form['email'] ) ) {
			return new WP_Error( 'invalid_data', 'Missing required data', [ 'status' => 400 ] );
		}

		$email              = sanitize_email( $form['email'] );
		$shipping_address   = wc_clean( $form['shipping'] );
		$billing_address    = wc_clean( $form['billing'] );
		$shipping_method_id = sanitize_text_field( $form['shipping_method'] ?? '' );
		$order_notes        = sanitize_textarea_field( $form['orderNotes'] ?? '' );

		// 🧠 Ensure WooCommerce session and customer are ready
		if ( ! WC()->session || ! is_a( WC()->customer, 'WC_Customer' ) ) {
			error_log( '[Checkout Debug] Initializing WooCommerce session/cart.' );
			wc_load_cart();
		}

		// Double check again after cart load
		if ( ! is_a( WC()->customer, 'WC_Customer' ) ) {
			return new WP_Error( 'customer_error', 'Customer session could not be initialized', [ 'status' => 500 ] );
		}

		// 🗺️ Set locations
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

		// 🚚 Store chosen method
		if ( $shipping_method_id ) {
			WC()->session->set( 'chosen_shipping_methods', [ $shipping_method_id ] );
		}

		// 💰 Calculate totals
		WC()->cart->calculate_totals();

		// 🧾 Prepare checkout data
		$checkout_data = [];

		foreach ( $billing_address as $key => $value ) {
			$checkout_data[ "billing_$key" ] = $value;
		}
		foreach ( $shipping_address as $key => $value ) {
			$checkout_data[ "shipping_$key" ] = $value;
		}

		$checkout_data['payment_method']  = 'manual';
		$checkout_data['shipping_method'] = [ $shipping_method_id ];
		$checkout_data['customer_note']   = $order_notes;
		$checkout_data['customer_id']     = get_current_user_id() ?: 0;

		// 🛒 Place order
		$checkout = WC()->checkout();
		$order_id = $checkout->create_order( $checkout_data );

		if ( is_wp_error( $order_id ) ) {
			return new WP_Error( 'order_error', $order_id->get_error_message(), [ 'status' => 500 ] );
		}

		$order = wc_get_order( $order_id );
		$order->set_billing_email( $email );
		$order->set_customer_note( $order_notes );
		$order->update_status( 'on-hold', 'Awaiting admin approval for payment' );
		$order->save();

		// 🔔 Optional hook
		// $hook_handler = new \HavenCore\WooCommerce\Hooks\Orders();
		// $hook_handler->schedule_supplier_email_job( $order->get_id() );

		WC()->cart->empty_cart();

		// 👤 Update customer info if needed
		if ( $customer_id = WC()->customer->get_id() ) {
			self::maybe_update_customer_profile( $customer_id, $billing_address, $shipping_address, $email );
		}

		return new WP_REST_Response( [ 'message' => 'Order placed successfully!' ], 200 );
	}

	private static function maybe_update_customer_profile( int $customer_id, array $billing_address, array $shipping_address, string $email ): void {
		$customer = new WC_Customer( $customer_id );

		foreach ( $billing_address as $key => $value ) {
			$getter = "get_billing_$key";
			$setter = "set_billing_$key";
			if ( method_exists( $customer, $getter ) && method_exists( $customer, $setter ) && $customer->$getter() !== $value ) {
				$customer->$setter( $value );
			}
		}

		foreach ( $shipping_address as $key => $value ) {
			$getter = "get_shipping_$key";
			$setter = "set_shipping_$key";
			if ( method_exists( $customer, $getter ) && method_exists( $customer, $setter ) && $customer->$getter() !== $value ) {
				$customer->$setter( $value );
			}
		}

		if ( $customer->get_email() !== $email ) {
			$customer->set_email( $email );
		}

		$customer->save();
	}
}