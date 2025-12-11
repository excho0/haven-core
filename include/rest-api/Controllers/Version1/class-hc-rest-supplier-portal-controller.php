<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HavenCore\Classes\HC_Settings;
use HavenCore\Services\HC_Supplier_Service;
use HavenCore\WooCommerce\Hooks\Orders;

class HC_REST_Supplier_Portal_V1_Controller extends HC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'hc/v1/suppliers';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'portal';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/change-password',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'change_password' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/save-metadata',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_metadata' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/confirm-fulfillment',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'confirm_fulfillment' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset-fulfillment',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reset_fulfillment' ],
				'permission_callback' => function () {
					return is_user_logged_in() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/orders/(?P<order_id>\d+)/suppliers',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_order_suppliers' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/orders/reassign-supplier',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reassign_order_supplier' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/assigned-orders',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_assigned_orders' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_supplier_products' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products/update-stock',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_supplier_product_stock' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		// List variations for a supplier-owned variable product
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products/(?P<product_id>\d+)/variations',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_supplier_product_variations' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'product_id' => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
				],
			]
		);

		// Update a single variation's stock/status
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/variations/update-stock',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_supplier_variation_stock' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

	}

	public function get_supplier_product_variations( WP_REST_Request $request ) {
		try {
			if ( ! $this->current_user_is_supplier_or_admin() ) {
				return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
			}

			$current = wp_get_current_user();
			$supplier_id = (int) $current->ID;
			$product_id = (int) $request->get_param( 'product_id' );
			if ( ! $product_id ) {
				return new WP_Error( 'invalid_params', 'Missing product_id', [ 'status' => 400 ] );
			}

			$product = wc_get_product( $product_id );
			if ( ! $product || $product->get_type() !== 'variable' ) {
				return new WP_Error( 'not_found', 'Variable product not found', [ 'status' => 404 ] );
			}

			$owner = (int) get_post_meta( $product_id, '_supplier_id', true );
			if ( $owner !== $supplier_id && ! wc_current_user_has_role( 'administrator' ) ) {
				return new WP_Error( 'forbidden', 'You cannot list variations for this product.', [ 'status' => 403 ] );
			}

			$variation_ids = $product->get_children();
			$items = [];
			foreach ( $variation_ids as $vid ) {
				$variation = wc_get_product( $vid );
				if ( ! $variation ) { continue; }
				$attrs = $variation->get_attributes();
				$attr_labels = [];
				if ( is_array( $attrs ) ) {
					foreach ( $attrs as $tax => $val ) {
						$tax_name = is_string( $tax ) ? $tax : (string) $tax;
						$label = function_exists('wc_attribute_label') ? wc_attribute_label( $tax_name ) : $tax_name;
						$val_str = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
						$attr_labels[] = trim( $label . ': ' . $val_str );
					}
				}
				$items[] = [
					'id'             => $variation->get_id(),
					'sku'            => $variation->get_sku(),
					'attributes'     => $attr_labels,
					'manage_stock'   => (bool) $variation->get_manage_stock(),
					'stock_status'   => $variation->get_stock_status(),
					'stock_quantity' => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
					'price'          => $variation->get_price(),
					'supplier_price' => get_post_meta( $variation->get_id(), '_supplier_price', true ),
					'thumbnail'      => get_the_post_thumbnail_url( $variation->get_id(), 'thumbnail' ),
				];
			}

			return new WP_REST_Response( [ 'variations' => $items ] );
		} catch ( \Throwable $e ) {
			error_log( '[HC Supplier Portal] get_supplier_product_variations error: ' . $e->getMessage() );
			return new WP_Error( 'internal_server_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	public function update_supplier_variation_stock( WP_REST_Request $request ) {
		try {
			if ( ! $this->current_user_is_supplier_or_admin() ) {
				return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
			}

			$body = $request->get_json_params();
			$variation_id   = isset( $body['variation_id'] ) ? (int) $body['variation_id'] : 0;
			$manage_stock   = array_key_exists( 'manage_stock', (array) $body ) ? (bool) $body['manage_stock'] : null;
			$stock_quantity = array_key_exists( 'stock_quantity', (array) $body ) ? $body['stock_quantity'] : null;
			$stock_status   = isset( $body['stock_status'] ) ? sanitize_text_field( $body['stock_status'] ) : null;
			$supplier_price = isset( $body['supplier_price'] ) ? sanitize_text_field( (string) $body['supplier_price'] ) : null;
			$sku            = array_key_exists( 'sku', (array) $body ) ? (string) $body['sku'] : null;

			if ( ! $variation_id ) {
				return new WP_Error( 'invalid_params', 'Missing variation_id', [ 'status' => 400 ] );
			}

			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				return new WP_Error( 'not_found', 'Variation not found', [ 'status' => 404 ] );
			}

		$parent_id = $variation->get_parent_id();
		$owner = (int) get_post_meta( $parent_id, '_supplier_id', true );
		$current = wp_get_current_user();
		if ( $owner !== (int) $current->ID && ! wc_current_user_has_role( 'administrator' ) ) {
			return new WP_Error( 'forbidden', 'You cannot update this variation.', [ 'status' => 403 ] );
		}

		$before_state = $this->capture_inventory_snapshot( $variation );

		// Optionally update manage_stock first so quantity updates apply
		if ( $manage_stock !== null ) {
			$variation->set_manage_stock( (bool) $manage_stock );
		}

			// Apply rules similar to simple products; do not handle backorders here
			if ( $variation->get_manage_stock() && $stock_quantity !== null ) {
				$variation->set_stock_quantity( max( 0, (int) $stock_quantity ) );
			}

			if ( $stock_status !== null ) {
				$allowed = [ 'instock', 'outofstock' ];
				if ( in_array( strtolower( $stock_status ), $allowed, true ) ) {
					$variation->set_stock_status( strtolower( $stock_status ) );
				}
			}

			if ( $sku !== null ) {
				$variation->set_sku( wc_clean( $sku ) );
			}

			$variation->save();

			// Update supplier price meta if provided
		if ( $supplier_price !== null ) {
			$normalized = str_replace( ',', '.', preg_replace( '/[^0-9\.,-]/', '', $supplier_price ) );
			update_post_meta( $variation_id, '_supplier_price', $normalized );
		}

		$after_state = $this->capture_inventory_snapshot( $variation );
		$changes = $this->detect_inventory_changes( $before_state, $after_state );
		$attributes = $this->format_variation_attributes( $variation );
		$thumb_source = get_the_post_thumbnail_url( $variation->get_id(), 'medium' );
		if ( ! $thumb_source ) {
			$thumb_source = get_the_post_thumbnail_url( $parent_id, 'medium' );
		}

		$this->schedule_supplier_product_update_alert(
			(int) $current->ID,
			[
				'id'           => $variation->get_id(),
				'parent_id'    => $parent_id,
				'name'         => $variation->get_name() ?: get_the_title( $parent_id ),
				'sku'          => $variation->get_sku(),
				'type'         => 'variation',
				'is_variation' => true,
				'attributes'   => $attributes,
				'permalink'    => get_permalink( $parent_id ),
				'edit_link'    => get_edit_post_link( $variation->get_id(), '' ) ?: admin_url( 'post.php?post=' . $variation->get_id() . '&action=edit' ),
				'thumbnail'    => $thumb_source ?: '',
			],
			$changes
		);

		return new WP_REST_Response( [
			'message' => 'Variation stock updated',
				'variation' => [
					'id'             => $variation->get_id(),
					'manage_stock'   => (bool) $variation->get_manage_stock(),
					'stock_status'   => $variation->get_stock_status(),
					'stock_quantity' => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
					'supplier_price' => get_post_meta( $variation->get_id(), '_supplier_price', true ),
				]
			] );
		} catch ( \Throwable $e ) {
			error_log( '[HC Supplier Portal] update_supplier_variation_stock error: ' . $e->getMessage() );
			return new WP_Error( 'internal_server_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	protected function current_user_is_supplier_or_admin(): bool {
		return is_user_logged_in() && ( wc_current_user_has_role( 'supplier' ) || wc_current_user_has_role( 'administrator' ) );
	}


	public function permissions_check( $request ) {
		return $this->current_user_is_supplier_or_admin();
	}

	public function validate_numeric( $value, $request, $param ) {
		return is_numeric( $value );
	}

	private function capture_inventory_snapshot( \WC_Product $product ): array {
		$raw_price = get_post_meta( $product->get_id(), '_supplier_price', true );
		return [
			'manage_stock'   => (bool) $product->get_manage_stock(),
			'stock_quantity' => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null,
			'stock_status'   => $product->get_stock_status(),
			'supplier_price' => $this->normalize_supplier_price( $raw_price ),
			'sku'            => $product->get_sku(),
		];
	}

	private function detect_inventory_changes( array $before, array $after ): array {
		$fields = [
			'manage_stock'   => 'Stock Management',
			'stock_quantity' => 'Stock Quantity',
			'stock_status'   => 'Stock Status',
			'supplier_price' => 'Supplier Price',
			'sku'            => 'SKU',
		];

		$changes = [];
		foreach ( $fields as $field => $label ) {
			$previous = $before[ $field ] ?? null;
			$current  = $after[ $field ] ?? null;
			if ( $this->normalize_value_for_compare( $field, $previous ) === $this->normalize_value_for_compare( $field, $current ) ) {
				continue;
			}

			$changes[] = [
				'field'  => $label,
				'before' => $this->format_change_value( $field, $previous ),
				'after'  => $this->format_change_value( $field, $current ),
			];
		}

		return $changes;
	}

	private function normalize_value_for_compare( string $field, $value ) {
		if ( $value === '' ) {
			$value = null;
		}

			switch ( $field ) {
				case 'manage_stock':
					return (bool) $value;
				case 'stock_quantity':
					return $value === null ? null : (int) $value;
				case 'stock_status':
					return $value === null ? null : strtolower( (string) $value );
				case 'supplier_price':
					return $value === null ? null : (float) $value;
				case 'sku':
					return $value === null ? null : (string) $value;
				default:
					return $value;
			}
	}

	private function format_change_value( string $field, $value ): string {
		if ( $value === null ) {
			return '—';
		}

			switch ( $field ) {
				case 'manage_stock':
					return $value ? 'Enabled' : 'Disabled';
				case 'stock_quantity':
					return number_format_i18n( (int) $value );
				case 'stock_status':
					$statuses = function_exists( 'wc_get_stock_statuses' ) ? wc_get_stock_statuses() : [];
					$lookup = strtolower( (string) $value );
					return $statuses[ $lookup ] ?? ucwords( $lookup );
				case 'supplier_price':
					return $this->format_currency_value( (float) $value );
				case 'sku':
					return (string) $value;
				default:
					return (string) $value;
			}
	}

	private function normalize_supplier_price( $value ) {
		if ( $value === null || $value === '' ) {
			return null;
		}

		$normalized = str_replace( ',', '.', preg_replace( '/[^0-9\.,-]/', '', (string) $value ) );
		return is_numeric( $normalized ) ? (float) $normalized : null;
	}

	private function format_currency_value( float $value ): string {
		if ( function_exists( 'wc_price' ) ) {
			$formatted = wc_price( $value );
			$formatted = wp_strip_all_tags( $formatted );
			return trim( html_entity_decode( $formatted, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		}

		return (string) $value;
	}

	private function schedule_supplier_product_update_alert( int $supplier_id, array $product_context, array $changes ): void {
		if ( empty( $changes ) ) {
			return;
		}

		$settings = new HC_Settings();
		if ( ! $settings->get( 'notifications.notify_admin_supplier_product_updates', true ) ) {
			return;
		}

		$supplier = get_user_by( 'id', $supplier_id );
		if ( ! $supplier ) {
			return;
		}

		$current_user = wp_get_current_user();
		$payload = [
			'supplier' => [
				'id'    => $supplier_id,
				'name'  => $supplier->display_name ?: $supplier->user_login,
				'email' => $supplier->user_email,
			],
			'product' => $product_context,
			'changes' => $changes,
			'actor'   => [
				'id'    => (int) $current_user->ID,
				'name'  => $current_user->display_name ?: $current_user->user_login,
				'email' => $current_user->user_email ?? '',
			],
			'triggered_at' => current_time( 'mysql' ),
		];

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'havencore_notify_admin_supplier_product_update', [ 'payload' => $payload ], 'hc-supplier-emails' );
			return;
		}

		wp_schedule_single_event( time() + 10, 'havencore_notify_admin_supplier_product_update', [ 'payload' => $payload ] );
	}

	private function format_variation_attributes( \WC_Product $variation ): string {
		if ( ! method_exists( $variation, 'get_attributes' ) ) {
			return '';
		}

		$attributes = $variation->get_attributes();
		if ( empty( $attributes ) || ! is_array( $attributes ) ) {
			return '';
		}

		$parts = [];
		foreach ( $attributes as $taxonomy => $raw_value ) {
			$label = is_string( $taxonomy ) && function_exists( 'wc_attribute_label' )
				? wc_attribute_label( $taxonomy )
				: ( is_string( $taxonomy ) ? $taxonomy : (string) $taxonomy );
			$value = is_array( $raw_value ) ? implode( ', ', $raw_value ) : (string) $raw_value;
			$parts[] = trim( sprintf( '%s: %s', $label, $value ) );
		}

		return implode( ', ', array_filter( $parts ) );
	}

	public function change_password( WP_REST_Request $request ) {
		$user = wp_get_current_user();
		$new_password = sanitize_text_field( $request['new_password'] );

		if ( strlen( $new_password ) < 8 ) {
			return new WP_Error( 'password_too_short', 'Password must be at least 8 characters.', [ 'status' => 400 ] );
		}

		wp_set_password( $new_password, $user->ID );
		wp_set_auth_cookie( $user->ID );

		try {
			$service  = new HC_Supplier_Service();
			$supplier = $service->get( $user->ID );

			if ( $supplier ) {
				$service->update( $supplier->get_id(), [
					'attributes' => [ 'needs_to_change_password' => false ]
				] );
				$supplier = $service->get( $user->ID );
				return new WP_REST_Response([
					'message' => 'Password updated successfully',
					'updated_attributes' => $supplier->get_attributes()
				]);
			}
		} catch ( \Exception $e ) {
			return new WP_Error( 'update_failed', 'Failed to update supplier attributes.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'message' => 'Password updated.' ], 200 );
	}

	public function save_metadata( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! ( wc_current_user_has_role( 'supplier' ) || wc_current_user_has_role( 'administrator' ) ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$current_user = wp_get_current_user();
		$supplier_id  = $current_user->ID;

		// Extract JSON body (assumes Content-Type: application/json)
		$body     = $request->get_json_params();
		$order_id = isset( $body['order_id'] ) ? (int) $body['order_id'] : 0;
		$metadata = $body['metadata'] ?? [];

		if ( ! $order_id || ! is_array( $metadata ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found', [ 'status' => 404 ] );
		}

		$supplier_data = $order->get_meta( '_supplier_data', true );
		if ( empty( $supplier_data ) || ! isset( $supplier_data[ $supplier_id ] ) ) {
			return new WP_Error( 'supplier_data_not_found', 'Supplier data not found', [ 'status' => 404 ] );
		}

		// Color tag and note
		if ( isset( $metadata['color_tag'] ) ) {
			$supplier_data[ $supplier_id ]['color_tag'] = sanitize_text_field( $metadata['color_tag'] );
		}
		if ( isset( $metadata['note'] ) ) {
			$supplier_data[ $supplier_id ]['note'] = sanitize_text_field( $metadata['note'] );
		}

		// Ungrouped products
		$ungrouped_products = [];
		// Build a map of existing quantities to preserve when client omits quantity
		$existing_ungrouped = isset( $supplier_data[ $supplier_id ]['ungrouped_products'] ) && is_array( $supplier_data[ $supplier_id ]['ungrouped_products'] )
			? $supplier_data[ $supplier_id ]['ungrouped_products']
			: [];
		$existing_qty_map = [];
		foreach ( $existing_ungrouped as $e ) {
			$epid = isset( $e['product_id'] ) ? (int) $e['product_id'] : 0;
			$evid = isset( $e['variation_id'] ) ? (int) $e['variation_id'] : 0;
			if ( ! $epid ) { continue; }
			$existing_qty_map[ $epid . ':' . $evid ] = isset( $e['quantity'] ) ? (int) $e['quantity'] : 0;
		}
		if ( ! empty( $metadata['ungrouped_products'] ) ) {
			foreach ( $metadata['ungrouped_products'] as $prod ) {
				$pid = isset( $prod['product_id'] ) ? (int) $prod['product_id'] : null;
				if ( ! $pid ) continue;

				$vid = isset( $prod['variation_id'] ) ? (int) $prod['variation_id'] : 0;
				$qty = isset( $prod['quantity'] ) ? (int) $prod['quantity'] : 0;
				if ( $qty <= 0 ) {
					$k = $pid . ':' . $vid;
					if ( isset( $existing_qty_map[ $k ] ) && (int) $existing_qty_map[ $k ] > 0 ) {
						$qty = (int) $existing_qty_map[ $k ];
					} else {
						$order_item = $this->find_matching_order_item( $order, (int) $pid, (int) $vid );
						$qty = $order_item ? (int) $order_item->get_quantity() : 1;
					}
				}

				$ungrouped_products[] = [
					'product_id'   => $pid,
					'variation_id' => $vid ?: null,
					'quantity'     => max( 1, (int) $qty ),
					'note'         => sanitize_textarea_field( $prod['note'] ?? '' ),
				];
			}
		}

		// Grouped products
		$grouped_products = [];
		$tracking_meta = [];
		$existing_grouped = isset( $supplier_data[ $supplier_id ]['grouped_products'] ) && is_array( $supplier_data[ $supplier_id ]['grouped_products'] )
			? $supplier_data[ $supplier_id ]['grouped_products']
			: [];
		if ( ! empty( $metadata['grouped_products'] ) ) {
			foreach ( $metadata['grouped_products'] as $group ) {
				$tracking = sanitize_text_field( $group['tracking_number'] ?? '' );
				if ( empty( $tracking ) ) continue;

				$tracking_meta[ $tracking ] = [
					'carrier_code'       => sanitize_text_field( $group['carrier_code'] ?? '' ),
					'carrier_name_other' => sanitize_text_field( $group['carrier_name_other'] ?? '' ),
				];

				if ( ! isset( $grouped_products[ $tracking ] ) ) {
					$grouped_products[ $tracking ] = [];
				}

				foreach ( $group['products'] as $prod ) {
					$pid = isset( $prod['product_id'] ) ? (int) $prod['product_id'] : null;
					if ( ! $pid ) continue;

					$vid = isset( $prod['variation_id'] ) ? (int) $prod['variation_id'] : 0;
					$qty = isset( $prod['quantity'] ) ? (int) $prod['quantity'] : 0;
					if ( $qty <= 0 ) {
						$prev = $existing_grouped[ $tracking ][ $pid ] ?? null;
						if ( is_array( $prev ) ) {
							$prev_vid = isset( $prev['variation_id'] ) ? (int) $prev['variation_id'] : 0;
							$prev_qty = isset( $prev['quantity'] ) ? (int) $prev['quantity'] : 0;
							if ( $prev_qty > 0 && $prev_vid === $vid ) {
								$qty = $prev_qty;
							}
						}
						if ( $qty <= 0 ) {
							$order_item = $this->find_matching_order_item( $order, (int) $pid, (int) $vid );
							$qty = $order_item ? (int) $order_item->get_quantity() : 1;
						}
					}

					$grouped_products[ $tracking ][ $pid ] = [
						'variation_id' => $vid ?: null,
						'quantity'     => max( 1, (int) $qty ),
						'note'         => sanitize_textarea_field( $prod['note'] ?? '' ),
					];
				}
			}
		}

		$supplier_data[ $supplier_id ]['ungrouped_products'] = $ungrouped_products;
		$supplier_data[ $supplier_id ]['grouped_products']   = $grouped_products;
		$supplier_data[ $supplier_id ]['tracking_groups_meta'] = $tracking_meta;
		$supplier_data[ $supplier_id ]['date_modified'] = current_time('Y-m-d\TH:i:s\Z'); // e.g. '2025-06-21T20:45:00Z'

		// Fulfillment status
		$current_status = $supplier_data[ $supplier_id ]['fulfillment_status'] ?? 'pending';
		$total          = count( $ungrouped_products );
		$fulfilled      = 0;

		foreach ( $grouped_products as $tracking => $products ) {
			$total     += count( $products );
			$fulfilled += count( $products );
		}

		if ( 'fulfilled' === $current_status ) {
			$supplier_data[ $supplier_id ]['fulfillment_status'] = 'fulfilled';
		} elseif ( $fulfilled === $total && $total > 0 ) {
			$supplier_data[ $supplier_id ]['fulfillment_status'] = 'ready-to-fulfill';
		} elseif ( $fulfilled > 0 ) {
			$supplier_data[ $supplier_id ]['fulfillment_status'] = 'partially-fulfilled';
		} else {
			$supplier_data[ $supplier_id ]['fulfillment_status'] = 'pending';
		}

		$order->update_meta_data( '_supplier_data', $supplier_data );
		$order->save();

		return new WP_REST_Response( [
			'message'            => 'Order saved successfully',
			'status'             => $supplier_data[ $supplier_id ]['fulfillment_status'],
			'ungrouped_products' => $ungrouped_products,
			'grouped_products'   => $grouped_products,
			'tracking_meta'      => $tracking_meta,
		] );
	}

	public function confirm_fulfillment( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! wc_current_user_has_role( 'supplier' ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$current_user = wp_get_current_user();
		$supplier_id = $current_user->ID;
		$order_id = (int) $request->get_param( 'order_id' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found', [ 'status' => 404 ] );
		}

		$supplier_data = $order->get_meta( '_supplier_data', true );
		if ( empty( $supplier_data[ $supplier_id ] ) ) {
			return new WP_Error( 'supplier_data_missing', 'No supplier data found.', [ 'status' => 404 ] );
		}

		$settings = new HC_Settings();

		$supplier_data[ $supplier_id ]['fulfillment_status'] = 'fulfilled';
		$order->update_meta_data( '_supplier_data', $supplier_data );
		$order->save();

		// Optional: AST integration
		if ( function_exists( 'ast_add_tracking_number' ) || function_exists( 'ast_insert_tracking_number' ) ) {
			$grouped = $supplier_data[$supplier_id]['grouped_products'] ?? [];
			$tracking_numbers = array_keys( $grouped );
			$tracking_numbers = array_filter( $tracking_numbers );

			if ( count( $tracking_numbers ) === 1 ) {
				ast_add_tracking_number( $order_id, $tracking_numbers[0], '', current_time( 'mysql' ), 0 );
			} else {
				foreach ( $grouped as $tracking_number => $products ) {
					foreach ( $products as $product_id => $data ) {
						$product = wc_get_product( $product_id );
						ast_insert_tracking_number(
							$order_id,
							$tracking_number,
							'',
							current_time( 'mysql' ),
							0,
							$product ? $product->get_sku() : '',
							$product ? $product->get_stock_quantity() : 1
						);
					}
				}
			}
		}

		if ( $settings->get( 'integrations.paypal.auto_tracking', false ) ) {
			$this->maybe_sync_paypal_tracking_with_paypal( $order, $supplier_data[ $supplier_id ] );
		}

		// Optional: Send email
		$customer_email = $order->get_billing_email();
		$customer_name  = $order->get_billing_first_name();

		ob_start();
		include HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'customer-tracking-update-email.php';
		$email_body = ob_get_clean();

		if (
			$settings->get( 'notifications.customer_tracking_emails', true ) &&
			! empty( $email_body )
		) {
			wp_mail(
				$customer_email,
				sprintf( __( 'Your Order #%d Has Shipped!', HAVEN_CORE_TEXT_DOMAIN ), $order_id ),
				$email_body,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}

		return new WP_REST_Response( [
			'message' => 'Order confirmed and customer notified.',
			'order_id' => $order_id,
			'status' => $supplier_data[$supplier_id]['fulfillment_status'],
		] );
	}

	public function reset_fulfillment( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$order_id    = (int) $request->get_param( 'order_id' );
		$supplier_id = (int) $request->get_param( 'supplier_id' );

		if ( ! $order_id || ! $supplier_id ) {
			return new WP_Error( 'invalid_params', 'Missing order_id or supplier_id', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found', [ 'status' => 404 ] );
		}

		$supplier_data = $order->get_meta( '_supplier_data', true );
		if ( empty( $supplier_data ) || empty( $supplier_data[ $supplier_id ] ) ) {
			return new WP_Error( 'supplier_data_missing', 'No supplier data found.', [ 'status' => 404 ] );
		}

		// Rebuild ungrouped products from order items that belong to this supplier (precise)
		$rebuilt_ungrouped = [];
		foreach ( $order->get_items() as $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) { continue; }
			$pid = (int) $item->get_product_id();
				if ( ! $pid ) { continue; }
				$vid = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

				// Prefer variation supplier assignment; fallback to parent product
				$assigned = 0;
				if ( $vid ) { $assigned = (int) get_post_meta( $vid, '_supplier_id', true ); }
				if ( ! $assigned ) { $assigned = (int) get_post_meta( $pid, '_supplier_id', true ); }
				if ( $assigned !== $supplier_id ) { continue; }

				$rebuilt_ungrouped[] = [
					'product_id'   => $pid,
					'variation_id' => $vid ?: null,
					'quantity'     => (int) $item->get_quantity(),
					'note'         => '',
				];
			}

        $supplier_data[ $supplier_id ]['ungrouped_products'] = $rebuilt_ungrouped;
        $supplier_data[ $supplier_id ]['grouped_products']   = [];
        $supplier_data[ $supplier_id ]['tracking_groups_meta'] = [];
        $supplier_data[ $supplier_id ]['fulfillment_status'] = 'pending';
        $supplier_data[ $supplier_id ]['date_modified']      = current_time('Y-m-d\TH:i:s\Z');

		$order->update_meta_data( '_supplier_data', $supplier_data );
		$order->save();

		return new WP_REST_Response( [
			'message' => 'Supplier fulfillment reset.',
			'order_id' => $order_id,
			'supplier_id' => $supplier_id,
			'status' => $supplier_data[ $supplier_id ]['fulfillment_status'] ?? 'pending',
		] );
	}

	public function get_order_suppliers( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Unauthorized', [ 'status' => 403 ] );
		}

		$order_id = (int) $request->get_param( 'order_id' );
		if ( ! $order_id ) {
			return new WP_Error( 'invalid_params', 'Missing order_id.', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
		}

		$cards = $this->build_supplier_cards( $order );

		return new WP_REST_Response( [
			'suppliers' => $cards,
		], 200 );
	}

	private function maybe_sync_paypal_tracking_with_paypal( \WC_Order $order, array $supplier_entry ): void {
		if ( empty( $supplier_entry['grouped_products'] ) ) {
			return;
		}

		if ( ! class_exists( '\WooCommerce\PayPalCommerce\PPCP' ) || ! function_exists( '\WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order' ) ) {
			return;
		}

		try {
			$container        = \WooCommerce\PayPalCommerce\PPCP::container();
			$endpoint         = $container->get( 'order-tracking.endpoint.controller' );
			$shipment_factory = $container->get( 'order-tracking.shipment.factory' );
		} catch ( \Throwable $e ) {
			$this->log_paypal_tracking_error( sprintf( 'Failed to bootstrap PayPal tracking services for order #%d: %s', $order->get_id(), $e->getMessage() ) );
			return;
		}

		if ( ! $endpoint || ! $shipment_factory ) {
			return;
		}

		try {
			$paypal_order = \WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order( $order );
			$capture_id   = $endpoint->get_paypal_order_transaction_id( $paypal_order );
		} catch ( \Throwable $e ) {
			$this->log_paypal_tracking_error( sprintf( 'Unable to derive PayPal capture for order #%d: %s', $order->get_id(), $e->getMessage() ) );
			return;
		}

		if ( empty( $capture_id ) ) {
			return;
		}

		$item_map      = $this->map_order_items_for_tracking( $order );
		$tracking_meta = $supplier_entry['tracking_groups_meta'] ?? [];

		$this->log_paypal_tracking_message( sprintf( 'Attempting PayPal tracking sync for order #%d (%d tracking groups).', $order->get_id(), count( (array) $supplier_entry['grouped_products'] ) ), 'info' );

		foreach ( (array) $supplier_entry['grouped_products'] as $tracking_number => $products ) {
			$tracking_number = trim( (string) $tracking_number );
			if ( '' === $tracking_number ) {
				continue;
			}

			$meta             = $tracking_meta[ $tracking_number ] ?? [];
			$carrier_code     = strtoupper( $meta['carrier_code'] ?? 'OTHER' );
			$carrier_friendly = 'OTHER' === $carrier_code ? ( $meta['carrier_name_other'] ?? '' ) : '';

			$current_products = isset( $products['products'] ) && is_array( $products['products'] )
				? $products['products']
				: $products;

			$line_items = $this->resolve_line_items_for_tracking_group( (array) $current_products, $item_map );

			try {
				$shipment  = $shipment_factory->create_shipment(
					$order->get_id(),
					$capture_id,
					$tracking_number,
					'SHIPPED',
					$carrier_code,
					$carrier_friendly,
					$line_items
				);
				$existing = $endpoint->get_tracking_information( $order->get_id(), $tracking_number );

				if ( $existing ) {
					$endpoint->update_tracking_information( $shipment, $order->get_id() );
				} else {
					$endpoint->add_tracking_information( $shipment, $order->get_id() );
				}

				$this->log_paypal_tracking_message(
					sprintf(
						'PayPal tracking sync OK for order #%1$d (tracking %2$s, carrier %3$s, items %4$s).',
						$order->get_id(),
						$tracking_number,
						$carrier_code,
						implode( ',', $line_items )
					),
					'info'
				);
			} catch ( \Throwable $e ) {
				$this->log_paypal_tracking_error( sprintf( 'PayPal tracking sync failed for order #%1$d (tracking %2$s): %3$s', $order->get_id(), $tracking_number, $e->getMessage() ) );
			}
		}
	}

	private function map_order_items_for_tracking( \WC_Order $order ): array {
		$map = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}

			$product_ids = [
				(int) $item->get_product_id(),
				(int) $item->get_variation_id(),
			];

			foreach ( array_filter( $product_ids ) as $product_id ) {
				if ( ! isset( $map[ $product_id ] ) ) {
					$map[ $product_id ] = [];
				}
				$map[ $product_id ][] = (int) $item_id;
			}
		}

		return $map;
	}

	private function resolve_line_items_for_tracking_group( array $products, array $item_map ): array {
		if ( isset( $products['products'] ) && is_array( $products['products'] ) ) {
			$products = $products['products'];
		}

		$line_items = [];

		foreach ( array_keys( $products ) as $product_id ) {
			$pid = (int) $product_id;
			if ( isset( $item_map[ $pid ] ) ) {
				$line_items = array_merge( $line_items, $item_map[ $pid ] );
			}
		}

		return array_values( array_unique( array_map( 'intval', $line_items ) ) );
	}

	private function log_paypal_tracking_message( string $message, string $level = 'warning' ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			if ( method_exists( $logger, $level ) ) {
				$logger->{$level}( $message, [ 'source' => 'havencore-paypal-tracking' ] );
			} else {
				$logger->log( $level, $message, [ 'source' => 'havencore-paypal-tracking' ] );
			}
		} else {
			error_log( $message );
		}
	}

	private function log_paypal_tracking_error( string $message ): void {
		$this->log_paypal_tracking_message( $message, 'warning' );
	}

	public function get_assigned_orders( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! wc_current_user_has_role( 'supplier' ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$user = wp_get_current_user();
		$service = new \HavenCore\Services\HC_Supplier_Service();
		$supplier = $service->get( (int) $user->ID );

		if ( ! $supplier ) {
			return new WP_Error( 'not_found', 'Supplier not found', [ 'status' => 404 ] );
		}

		$supplier_id = $supplier->get_id();
		$assigned_orders = $supplier->get_assigned_orders() ?? [];

		// Filtering
		$status_filter = sanitize_text_field( $request->get_param( 'status' ) ?? 'all' );
		$search_raw    = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$search        = strtolower( $search_raw );
		$filtered_orders = [];

		foreach ( $assigned_orders as $order_id ) {
			$order_tmp = wc_get_order( $order_id );
			$data = $order_tmp ? $order_tmp->get_meta( '_supplier_data', true ) : [];
			$status = $data[ $supplier_id ]['fulfillment_status'] ?? 'pending';

			if ( $status_filter !== 'all' && strtolower( $status ) !== strtolower( $status_filter ) ) {
				continue;
			}

			// Apply server-side search if provided
			if ( $search !== '' ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) { continue; }

				$parts = [];
				$parts[] = (string) $order_id;
				$parts[] = (string) $order->get_billing_first_name();
				$parts[] = (string) $order->get_billing_last_name();
				$parts[] = (string) $order->get_billing_email();
				$parts[] = (string) $order->get_billing_phone();
				$parts[] = (string) $order->get_billing_address_1();
				$parts[] = (string) $order->get_billing_address_2();
				$parts[] = (string) $order->get_billing_city();
				$parts[] = (string) $order->get_billing_state();
				$parts[] = (string) $order->get_billing_postcode();
				$parts[] = (string) $order->get_billing_country();
				$parts[] = (string) $order->get_shipping_first_name();
				$parts[] = (string) $order->get_shipping_last_name();
				$parts[] = (string) $order->get_shipping_address_1();
				$parts[] = (string) $order->get_shipping_address_2();
				$parts[] = (string) $order->get_shipping_city();
				$parts[] = (string) $order->get_shipping_state();
				$parts[] = (string) $order->get_shipping_postcode();
				$parts[] = (string) $order->get_shipping_country();

				// Supplier-specific products: ungrouped
				foreach ( (array) ( $data[ $supplier_id ]['ungrouped_products'] ?? [] ) as $entry ) {
					$pid = isset( $entry['product_id'] ) ? (int) $entry['product_id'] : 0;
					if ( ! $pid ) { continue; }
					$product = wc_get_product( $pid );
					if ( $product ) {
						$parts[] = (string) $product->get_name();
						$parts[] = (string) $product->get_sku();
					}
					$parts[] = (string) ( $entry['note'] ?? '' );
				}

				// Supplier-specific products: grouped
				foreach ( (array) ( $data[ $supplier_id ]['grouped_products'] ?? [] ) as $tracking => $products ) {
					$parts[] = (string) $tracking;
					foreach ( (array) $products as $pid => $info ) {
						$pid = (int) $pid;
						if ( ! $pid ) { continue; }
						$product = wc_get_product( $pid );
						if ( $product ) {
							$parts[] = (string) $product->get_name();
							$parts[] = (string) $product->get_sku();
						}
						$parts[] = (string) ( $info['note'] ?? '' );
					}
				}

				$haystack = strtolower( implode( ' ', array_filter( $parts, static function( $v ) { return $v !== null && $v !== ''; } ) ) );
				if ( strpos( $haystack, $search ) === false ) {
					continue; // does not match search
				}
			}

			$filtered_orders[] = $order_id;
		}

		// Sorting
		$sort_order = strtolower( $request->get_param( 'sort' ) ?? 'desc' );
		usort( $filtered_orders, function ( $a, $b ) use ( $sort_order ) {
			$oa = wc_get_order( $a );
			$ob = wc_get_order( $b );
			$timeA = ( $oa && $oa->get_date_created() ) ? (int) $oa->get_date_created()->getTimestamp() : 0;
			$timeB = ( $ob && $ob->get_date_created() ) ? (int) $ob->get_date_created()->getTimestamp() : 0;
			return ( $sort_order === 'asc' ) ? $timeA - $timeB : $timeB - $timeA;
		} );

		// Pagination
		$page = max( 1, (int) $request->get_param( 'page' ) ?? 1 );
		$per_page = max( 1, (int) $request->get_param( 'per_page' ) ?? 10 );
		$total_orders = count( $filtered_orders );
		$total_pages = ceil( $total_orders / $per_page );
		$offset = ( $page - 1 ) * $per_page;

		$paged_orders = array_slice( $filtered_orders, $offset, $per_page );
		$orders_data = [];

		foreach ( $paged_orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) continue;

			$data = $order->get_meta( '_supplier_data', true );
			$supplier_status = $data[ $supplier_id ]['fulfillment_status'] ?? 'pending';
			$tracking_meta = $data[ $supplier_id ]['tracking_groups_meta'] ?? [];

			// Calculate supplier total strictly from supplier meta (ungrouped + grouped)
			$supplier_total = 0.0;
			$calc_price = function( int $product_id, int $variation_id ) : float {
				$ids = [];
				if ( $variation_id ) { $ids[] = $variation_id; }
				if ( $product_id ) { $ids[] = $product_id; }
				foreach ( $ids as $sid ) {
					$sp_raw = get_post_meta( $sid, '_supplier_price', true );
					if ( $sp_raw === '' || $sp_raw === null ) { continue; }
					$sp_norm = is_string( $sp_raw ) ? str_replace( ',', '.', preg_replace( '/[^0-9\.,-]/', '', $sp_raw ) ) : (string) $sp_raw;
					if ( is_numeric( $sp_norm ) ) { return max( 0.0, (float) $sp_norm ); }
				}
				return 0.0;
			};

			$ungrouped_source = $data[ $supplier_id ]['ungrouped_products'] ?? [];
			foreach ( $ungrouped_source as $entry ) {
				$pid = isset( $entry['product_id'] ) ? (int) $entry['product_id'] : 0;
				$vid = isset( $entry['variation_id'] ) ? (int) $entry['variation_id'] : 0;
				$qty = isset( $entry['quantity'] ) ? (int) $entry['quantity'] : 0;
				if ( ! $vid ) {
					$order_item = $this->find_matching_order_item( $order, $pid, $vid );
					if ( $order_item && $order_item->get_variation_id() ) {
						$vid = (int) $order_item->get_variation_id();
					}
				}
				if ( $qty <= 0 ) {
					$order_item = $this->find_matching_order_item( $order, $pid, $vid );
					$qty = $order_item ? (int) $order_item->get_quantity() : 0;
				}
				$supplier_total += $calc_price( $pid, $vid ) * max( 0, $qty );
			}

			foreach ( ( $data[ $supplier_id ]['grouped_products'] ?? [] ) as $tracking => $products ) {
				$current_products = isset( $products['products'] ) ? $products['products'] : $products;
				foreach ( $current_products as $pid => $info ) {
					$pid = (int) $pid;
					$vid = isset( $info['variation_id'] ) ? (int) $info['variation_id'] : 0;
					$qty = isset( $info['quantity'] ) ? (int) $info['quantity'] : 0;
					if ( ! $vid ) {
						$order_item = $this->find_matching_order_item( $order, $pid, $vid );
						if ( $order_item && $order_item->get_variation_id() ) {
							$vid = (int) $order_item->get_variation_id();
						}
					}
					if ( $qty <= 0 ) {
						$order_item = $this->find_matching_order_item( $order, $pid, $vid );
						$qty = $order_item ? (int) $order_item->get_quantity() : 0;
					}
					$supplier_total += $calc_price( $pid, $vid ) * max( 0, $qty );
				}
			}

			// Ungrouped products
			$ungrouped = [];
			$ungrouped_source = $data[ $supplier_id ]['ungrouped_products'] ?? [];
			foreach ( $ungrouped_source as $entry ) {
				$product_id   = isset( $entry['product_id'] ) ? (int) $entry['product_id'] : 0;
				if ( ! $product_id ) {
					continue;
				}

				$variation_id = isset( $entry['variation_id'] ) ? (int) $entry['variation_id'] : 0;
				$product      = wc_get_product( $variation_id ?: $product_id );
				$order_item   = $this->find_matching_order_item( $order, $product_id, $variation_id );
				if ( ! $variation_id && $order_item && $order_item->get_variation_id() ) {
					$variation_id = (int) $order_item->get_variation_id();
				}

				$qty = isset( $entry['quantity'] ) ? (int) $entry['quantity'] : 0;
				if ( $qty <= 0 && $order_item ) {
					$qty = (int) $order_item->get_quantity();
				}

				$variation_text = $order_item ? $this->format_item_variation( $order_item ) : '';

				$thumbnail = '';
				if ( $variation_id ) {
					$thumbnail = get_the_post_thumbnail_url( $variation_id, 'thumbnail' ) ?: '';
				}
				if ( ! $thumbnail ) {
					$thumbnail = get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '';
				}

				// Keep product_name without variation suffix for variations
				$base_name = 'Unknown';
				if ( $product ) {
					if ( $variation_id && method_exists( $product, 'get_parent_id' ) ) {
						$parent = wc_get_product( (int) $product->get_parent_id() );
						$base_name = $parent ? $parent->get_name() : $product->get_name();
					} else {
						$base_name = $product->get_name();
					}
				}

				$ungrouped[] = [
					'product_id'   => $product_id,
					'variation_id' => $variation_id ?: null,
					'product_name' => $base_name,
					'variation'    => $variation_text,
					'note'         => $entry['note'] ?? '',
					'sku'          => $product ? $product->get_sku() : '',
					'qty'          => $qty,
					'thumbnail'    => $thumbnail,
				];
			}

			// Grouped products
			$grouped = [];
			foreach ( $data[ $supplier_id ]['grouped_products'] ?? [] as $tracking => $products ) {
				$list = [];
				$current_products = $products;
				if ( isset( $products['products'] ) ) {
					$current_products = $products['products'];
				}

				foreach ( $current_products as $pid => $info ) {
					$pid = (int) $pid;
					if ( ! $pid ) { continue; }

					$variation_id = isset( $info['variation_id'] ) ? (int) $info['variation_id'] : 0;
					$product      = wc_get_product( $variation_id ?: $pid );
					$order_item   = $this->find_matching_order_item( $order, $pid, $variation_id );
					if ( ! $variation_id && $order_item && $order_item->get_variation_id() ) {
						$variation_id = (int) $order_item->get_variation_id();
					}

					$qty = isset( $info['quantity'] ) ? (int) $info['quantity'] : 0;
					if ( $qty <= 0 && $order_item ) {
						$qty = (int) $order_item->get_quantity();
					}

					$variation_text = $order_item ? $this->format_item_variation( $order_item ) : '';

					$thumbnail = '';
					if ( $variation_id ) {
						$thumbnail = get_the_post_thumbnail_url( $variation_id, 'thumbnail' ) ?: '';
					}
					if ( ! $thumbnail ) {
						$thumbnail = get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '';
					}

					// Keep product_name without variation suffix for variations
					$base_name = 'Unknown';
					if ( $product ) {
						if ( $variation_id && method_exists( $product, 'get_parent_id' ) ) {
							$parent = wc_get_product( (int) $product->get_parent_id() );
							$base_name = $parent ? $parent->get_name() : $product->get_name();
						} else {
							$base_name = $product->get_name();
						}
					}

					$list[] = [
						'product_id'   => $pid,
						'variation_id' => $variation_id ?: null,
						'product_name' => $base_name,
						'variation'    => $variation_text,
						'note'         => $info['note'] ?? '',
						'sku'          => $product ? $product->get_sku() : '',
						'qty'          => $qty,
						'thumbnail'    => $thumbnail,
					];
				}
				$meta = $tracking_meta[ (string) $tracking ] ?? [];
				$grouped[] = [
					'tracking_number'     => $tracking,
					'carrier_code'        => $meta['carrier_code'] ?? '',
					'carrier_name_other'  => $meta['carrier_name_other'] ?? '',
					'products'            => $list
				];
			}

			// Remove ungrouped entries that appear in grouped (dedupe by product_id:variation_id)
			if ( ! empty( $grouped ) && ! empty( $ungrouped ) ) {
				$gkeys = [];
				foreach ( $grouped as $grp ) {
					foreach ( $grp['products'] as $gp ) {
						$k = ((int) $gp['product_id']) . ':' . ((int) ( $gp['variation_id'] ?? 0 ));
						$gkeys[ $k ] = true;
					}
				}
				$ungrouped = array_values( array_filter( $ungrouped, function( $u ) use ( $gkeys ) {
					$k = ((int) $u['product_id']) . ':' . ((int) ( $u['variation_id'] ?? 0 ));
					return empty( $gkeys[ $k ] );
				} ) );
			}

			$supplier_total_formatted = wc_price( $supplier_total );
			$supplier_total_formatted = html_entity_decode( wp_strip_all_tags( $supplier_total_formatted ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			$supplier_total_formatted = str_replace( "\xc2\xa0", ' ', $supplier_total_formatted );

			$orders_data[] = [
				'id' => $order_id,
				'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
				'date_modified' => $data[ $supplier_id ]['date_modified'] ?? '',
				'supplier_status' => $supplier_status,
				'supplier_note' => $data[ $supplier_id ]['note'] ?? '',
				'color_tag' => $data[ $supplier_id ]['color_tag'] ?? '',
				'supplier_total' => (float) $supplier_total,
				'supplier_total_formatted' => $supplier_total_formatted,
				'currency_symbol' => get_woocommerce_currency_symbol(),
				'ungrouped_products' => $ungrouped,
				'grouped_products' => $grouped,
				'tracking_groups_meta' => $tracking_meta,
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

		return new WP_REST_Response( [
			'orders' => $orders_data,
			'pagination' => [
				'page' => $page,
				'per_page' => $per_page,
				'total_orders' => $total_orders,
				'total_pages' => $total_pages
			]
		] );
	}

	public function get_supplier_products( WP_REST_Request $request ) {
		if ( ! $this->current_user_is_supplier_or_admin() ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$current = wp_get_current_user();
		$supplier_id = (int) $current->ID;

		$page     = max( 1, (int) $request->get_param( 'page' ) ?? 1 );
		$per_page = max( 1, min( 50, (int) $request->get_param( 'per_page' ) ?? 10 ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		$args = [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'private' ],
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => '_supplier_id',
					'value' => $supplier_id,
				]
			],
			's'              => $search ?: '',
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		];

		$q = new \WP_Query( $args );
		$ids = $q->posts;

		$items = [];
		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) continue;

			// Aggregate variation stock info for variable products
			$var_total_qty = 0;
			$var_instock   = 0;
			$var_total     = 0;
			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				$var_total = is_array( $children ) ? count( $children ) : 0;
				if ( $children ) {
					foreach ( $children as $vid ) {
						$v = wc_get_product( $vid );
						if ( ! $v ) { continue; }
						if ( $v->get_manage_stock() ) {
							$var_total_qty += (int) max( 0, (int) $v->get_stock_quantity() );
						}
						if ( $v->get_stock_status() === 'instock' ) {
							$var_instock++;
						}
					}
				}
			}

			$items[] = [
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'sku'            => $product->get_sku(),
				'type'           => $product->get_type(),
				'manage_stock'   => (bool) $product->get_manage_stock(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null,
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'supplier_price' => get_post_meta( $product->get_id(), '_supplier_price', true ),
				'thumbnail'      => get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ),
				'permalink'      => get_permalink( $product->get_id() ),
				// Aggregates for variable parents
				'variation_stock_total'   => $product->is_type( 'variable' ) ? (int) $var_total_qty : null,
				'variation_instock_count' => $product->is_type( 'variable' ) ? (int) $var_instock   : null,
				'variation_total_count'   => $product->is_type( 'variable' ) ? (int) $var_total     : null,
			];
		}

		return new WP_REST_Response( [
			'products' => $items,
			'pagination' => [
				'page'       => (int) $page,
				'per_page'   => (int) $per_page,
				'total'      => (int) $q->found_posts,
				'total_pages'=> (int) $q->max_num_pages,
			]
		] );
	}

	public function update_supplier_product_stock( WP_REST_Request $request ) {
		if ( ! $this->current_user_is_supplier_or_admin() ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 403 ] );
		}

		$current = wp_get_current_user();
		$supplier_id = (int) $current->ID;

		$body = $request->get_json_params();
		$product_id     = isset( $body['product_id'] ) ? (int) $body['product_id'] : 0;
		$manage_stock   = isset( $body['manage_stock'] ) ? (bool) $body['manage_stock'] : null;
		$stock_quantity = isset( $body['stock_quantity'] ) ? max( 0, (int) $body['stock_quantity'] ) : null;
		$stock_status   = isset( $body['stock_status'] ) ? sanitize_text_field( $body['stock_status'] ) : null;
		$supplier_price = isset( $body['supplier_price'] ) ? sanitize_text_field( (string) $body['supplier_price'] ) : null;
		$sku            = array_key_exists( 'sku', (array) $body ) ? (string) $body['sku'] : null;

		if ( ! $product_id ) {
			return new WP_Error( 'invalid_params', 'Missing product_id', [ 'status' => 400 ] );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
		}

		$owner = (int) get_post_meta( $product_id, '_supplier_id', true );
		if ( $owner !== $supplier_id && ! wc_current_user_has_role( 'administrator' ) ) {
			return new WP_Error( 'forbidden', 'You cannot update stock for this product.', [ 'status' => 403 ] );
		}

		$before_state = $this->capture_inventory_snapshot( $product );

		if ( $manage_stock !== null ) {
			$product->set_manage_stock( (bool) $manage_stock );
		}

		if ( $product->get_manage_stock() && $stock_quantity !== null ) {
			$product->set_stock_quantity( $stock_quantity );
		}

		if ( $stock_status !== null ) {
			$allowed = [ 'instock', 'outofstock', 'onbackorder' ];
			if ( in_array( strtolower( $stock_status ), $allowed, true ) ) {
				$product->set_stock_status( strtolower( $stock_status ) );
			}
		}

		if ( $sku !== null ) {
			$product->set_sku( wc_clean( $sku ) );
		}

		$product->save();

		// Update supplier price meta if provided
		if ( $supplier_price !== null ) {
			// Normalize decimal format (allow only numbers, dot, comma -> convert comma to dot)
			$normalized = str_replace( ',', '.', preg_replace( '/[^0-9\.,-]/', '', $supplier_price ) );
			update_post_meta( $product_id, '_supplier_price', $normalized );
		}

		$after_state = $this->capture_inventory_snapshot( $product );
		$changes = $this->detect_inventory_changes( $before_state, $after_state );

		$thumbnail = get_the_post_thumbnail_url( $product->get_id(), 'medium' );
		$this->schedule_supplier_product_update_alert(
			$supplier_id,
			[
				'id'           => $product->get_id(),
				'parent_id'    => null,
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'type'         => $product->get_type(),
				'is_variation' => false,
				'permalink'    => get_permalink( $product->get_id() ),
				'edit_link'    => get_edit_post_link( $product->get_id(), '' ) ?: admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
				'thumbnail'    => $thumbnail ?: '',
			],
			$changes
		);

		return new WP_REST_Response( [
			'message' => 'Stock updated',
			'product' => [
				'id'             => $product->get_id(),
				'manage_stock'   => (bool) $product->get_manage_stock(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null,
				'supplier_price' => get_post_meta( $product->get_id(), '_supplier_price', true ),
			]
		] );
	}

	public function reassign_order_supplier( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Unauthorized', [ 'status' => 403 ] );
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$from_id  = (int) $request->get_param( 'from_supplier_id' );
		$to_id    = (int) $request->get_param( 'to_supplier_id' );

		if ( ! $order_id || ! $from_id || ! $to_id ) {
			return new WP_Error( 'invalid_params', 'Missing parameters.', [ 'status' => 400 ] );
		}

		if ( $from_id === $to_id ) {
			return new WP_Error( 'invalid_params', 'Source and target suppliers must differ.', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
		}

		$supplier_data = $order->get_meta( '_supplier_data', true );
		if ( empty( $supplier_data ) || ! is_array( $supplier_data ) || ! isset( $supplier_data[ $from_id ] ) ) {
			return new WP_Error( 'source_missing', 'Source supplier data not found on this order.', [ 'status' => 400 ] );
		}

		if ( isset( $supplier_data[ $to_id ] ) ) {
			return new WP_Error( 'target_exists', 'Target supplier already assigned to this order.', [ 'status' => 400 ] );
		}

		$target_user = get_user_by( 'id', $to_id );
		if ( ! $target_user || ! in_array( 'supplier', (array) $target_user->roles, true ) ) {
			return new WP_Error( 'invalid_target', 'Target user is not a supplier.', [ 'status' => 400 ] );
		}

		$segment  = $supplier_data[ $from_id ];
		$original = $supplier_data;
		unset( $supplier_data[ $from_id ] );

		$new_data = [];
		foreach ( $original as $sid => $entry ) {
			if ( (int) $sid === $from_id ) {
				$new_data[ $to_id ] = $segment;
				continue;
			}
			$new_data[ $sid ] = $entry;
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( '_supplier_data', $new_data );
			$order->save();
		}

		$service = new HC_Supplier_Service();
		$service->unassign_order( $from_id, $order_id );
		$service->assign_orders( $to_id, [ $order_id ] );

		Orders::schedule_supplier_reassignment_email( $order_id, $to_id );

		return new WP_REST_Response( [ 'message' => 'Supplier reassigned.' ], 200 );
	}

	private function build_supplier_cards( \WC_Order $order ): array {
		$supplier_data = $order->get_meta( '_supplier_data', true );
		if ( empty( $supplier_data ) || ! is_array( $supplier_data ) ) {
			return [];
		}

		$status_map = [
			'fulfilled'           => [
				'label' => __( 'Fulfilled', 'woocommerce' ),
				'badge' => 'success',
			],
			'pending'             => [
				'label' => __( 'Pending', 'woocommerce' ),
				'badge' => 'warning',
			],
			'partially-fulfilled' => [
				'label' => __( 'Partially Fulfilled', 'woocommerce' ),
				'badge' => 'info',
			],
			'ready-to-fulfill'    => [
				'label' => __( 'Ready to Fulfill', 'woocommerce' ),
				'badge' => 'info',
			],
		];

		$service = new HC_Supplier_Service();
		$cards   = [];
		foreach ( $supplier_data as $supplier_id => $entry ) {
			$supplier_id = (int) $supplier_id;
			if ( ! $supplier_id ) {
				continue;
			}

			$cards[] = $this->format_supplier_card( $order, $supplier_id, is_array( $entry ) ? $entry : [], $service, $status_map );
		}

		return $cards;
	}

	private function format_supplier_card( \WC_Order $order, int $supplier_id, array $entry, HC_Supplier_Service $service, array $status_map ): array {
		$supplier_instance = $service->get( $supplier_id );
		$supplier_name     = $supplier_instance ? $supplier_instance->get_name() : __( 'Unknown Supplier', 'woocommerce' );
		$status_key        = $entry['fulfillment_status'] ?? 'pending';
		$status_details    = $status_map[ $status_key ] ?? $status_map['pending'];

		$grand_total  = 0;
		$ungrouped    = $this->map_ungrouped_products( $order, $entry['ungrouped_products'] ?? [], $grand_total );
		$grouped_data = $this->map_grouped_products( $order, $entry['grouped_products'] ?? [], $grand_total );

		return [
			'id'                 => $supplier_id,
			'name'               => $supplier_name,
			'status'             => [
				'value' => $status_key,
				'label' => $status_details['label'],
				'badge' => $status_details['badge'],
			],
			'ungrouped_products' => $ungrouped,
			'grouped_products'   => $grouped_data,
			'grand_total'        => $grand_total > 0 ? $this->format_price_display( $grand_total ) : null,
		];
	}

	private function map_ungrouped_products( \WC_Order $order, array $items, float &$grand_total ): array {
		$result = [];
		foreach ( $items as $product ) {
			$product_id = isset( $product['product_id'] ) ? (int) $product['product_id'] : 0;
			if ( ! $product_id ) {
				continue;
			}

			$variation_id = isset( $product['variation_id'] ) ? (int) $product['variation_id'] : 0;
			$quantity     = isset( $product['quantity'] ) ? (int) $product['quantity'] : $this->resolve_order_item_quantity( $order, $product_id, $variation_id );
			$entry        = $this->build_product_entry( $order, $product_id, $quantity, $variation_id );
			if ( ! $entry ) {
				continue;
			}

			$grand_total += $entry['line_total_raw'];
			unset( $entry['line_total_raw'] );
			$result[] = $entry;
		}

		return $result;
	}

	private function map_grouped_products( \WC_Order $order, array $groups, float &$grand_total ): array {
		$result = [];
		foreach ( $groups as $tracking_number => $products ) {
			$formatted_products = [];
			foreach ( $products as $product_id => $details ) {
				$product_id = (int) $product_id;
				if ( ! $product_id ) {
					continue;
				}

				$variation_id = isset( $details['variation_id'] ) ? (int) $details['variation_id'] : 0;
				$qty          = isset( $details['quantity'] ) ? (int) $details['quantity'] : $this->resolve_order_item_quantity( $order, $product_id, $variation_id );
				$entry        = $this->build_product_entry( $order, $product_id, $qty, $variation_id );
				if ( ! $entry ) {
					continue;
				}

				$grand_total += $entry['line_total_raw'];
				unset( $entry['line_total_raw'] );
				$formatted_products[] = $entry;
			}

			$result[] = [
				'tracking_number' => $tracking_number,
				'products'        => $formatted_products,
			];
		}

		return $result;
	}

	private function resolve_order_item_quantity( \WC_Order $order, int $product_id, int $variation_id = 0 ): int {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$matches = $variation_id
				? (int) $item->get_variation_id() === $variation_id
				: (int) $item->get_product_id() === $product_id;

			if ( $matches ) {
				return max( 1, (int) $item->get_quantity() );
			}
		}

		return 1;
	}

	private function build_product_entry( \WC_Order $order, int $product_id, int $quantity, int $variation_id = 0 ): ?array {
		$quantity     = max( 1, $quantity );
		$order_item   = $this->find_matching_order_item( $order, $product_id, $variation_id );
		if ( ! $variation_id && $order_item && $order_item->get_variation_id() ) {
			$variation_id = (int) $order_item->get_variation_id();
		}

		$target_id     = $variation_id ?: $product_id;
		$product             = $target_id ? wc_get_product( $target_id ) : null;
		$parent              = null;
		$product_name        = __( 'Product not found', 'woocommerce' );
		$variation           = '';
		$product_url         = '';
		$thumbnail_url       = '';
		$variation_thumbnail = 0;

		if ( $product && $product->is_type( 'variation' ) ) {
			$variation_thumbnail = $product->get_image_id();
			$parent_id = $product->get_parent_id();
			if ( $parent_id ) {
				$parent = wc_get_product( $parent_id );
			}
		}

		$name_source = $parent ?: $product;
		if ( $name_source ) {
			$product_name = sanitize_text_field( wp_strip_all_tags( html_entity_decode( $name_source->get_name(), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
			$product_url  = $name_source->get_permalink();
			$thumbnail_id = $variation_thumbnail ?: ( $name_source ? $name_source->get_image_id() : 0 );
			if ( ! $thumbnail_id && $parent ) {
				$thumbnail_id = $parent->get_image_id();
			}

			if ( $thumbnail_id ) {
				$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) ?: '';
			}
		} else {
			$product_url   = get_permalink( $product_id ) ?: '';
			$thumbnail_url = get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '';
		}

		if ( $order_item ) {
			$variation = $this->format_item_variation( $order_item );
		}

		$price_candidates = array_filter( [ $variation_id ?: null, $product_id, $parent ? $parent->get_id() : null ] );
		$price_value      = 0;
		foreach ( $price_candidates as $candidate ) {
			$price = get_post_meta( $candidate, '_supplier_price', true );
			if ( is_numeric( $price ) && (float) $price > 0 ) {
				$price_value = (float) $price;
				break;
			}
		}

		$line_total = $price_value > 0 ? $price_value * $quantity : 0;

		return [
			'product_id'               => $product_id,
			'variation_id'             => $variation_id ?: null,
			'quantity'                 => $quantity,
			'name'                     => $product_name,
			'variation'                => $variation,
			'product_url'              => $product_url,
			'thumbnail'                => $thumbnail_url,
			'supplier_price'           => $price_value > 0 ? $price_value : null,
			'supplier_price_formatted' => $price_value > 0 ? $this->format_price_display( $price_value ) : null,
			'line_total_formatted'     => $line_total > 0 ? $this->format_price_display( $line_total ) : null,
			'line_total_raw'           => $line_total,
		];
	}

	private function find_matching_order_item( \WC_Order $order, int $product_id, int $variation_id = 0 ): ?\WC_Order_Item_Product {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$matches = $variation_id
				? (int) $item->get_variation_id() === $variation_id
				: (int) $item->get_product_id() === $product_id;

			if ( $matches ) {
				return $item;
			}
		}

		return null;
	}

	private function format_item_variation( \WC_Order_Item_Product $item ): string {
		$meta_data = $item->get_meta_data();
		$parts     = [];
		foreach ( $meta_data as $meta ) {
			$meta_key   = method_exists( $meta, 'get_data' ) ? ( $meta->get_data()['key'] ?? '' ) : ( $meta->key ?? '' );
			$meta_value = method_exists( $meta, 'get_data' ) ? ( $meta->get_data()['value'] ?? '' ) : ( $meta->value ?? '' );
			if ( $meta_value === '' || $meta_value === null ) { continue; }
			// Exclude system/internal metas
			if ( is_string( $meta_key ) && strlen( $meta_key ) > 0 && $meta_key[0] === '_' ) { continue; }

			$label = '';
			// attribute_{taxonomy or name}
			if ( strpos( $meta_key, 'attribute_' ) === 0 ) {
				$label = wc_attribute_label( str_replace( 'attribute_', '', $meta_key ) );
			// Global attribute taxonomy keys on some setups (pa_*)
			} elseif ( strpos( $meta_key, 'pa_' ) === 0 ) {
				$label = wc_attribute_label( $meta_key );
			// Custom attribute keys (non-underscore)
			} else {
				$label = $meta_key;
			}

			$label = sanitize_text_field( wp_strip_all_tags( (string) $label ) );
			$value = sanitize_text_field( wp_strip_all_tags( is_scalar( $meta_value ) ? (string) $meta_value : wp_json_encode( $meta_value ) ) );
			if ( $label && $value ) {
				$parts[] = $label . ': ' . $value;
			}
		}

		// Fallback: if no order-item meta attributes found, use the variation product attributes
		if ( empty( $parts ) ) {
			$variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			if ( $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$attrs = method_exists( $variation, 'get_attributes' ) ? (array) $variation->get_attributes() : [];
					foreach ( $attrs as $akey => $aval ) {
						if ( ! is_string( $akey ) || $akey === '' ) { continue; }
						$label = '';
						if ( strpos( $akey, 'pa_' ) === 0 ) {
							$label = wc_attribute_label( $akey );
						} else {
							$label = $akey;
						}
						$label = sanitize_text_field( wp_strip_all_tags( (string) $label ) );
						$value = sanitize_text_field( wp_strip_all_tags( is_scalar( $aval ) ? (string) $aval : wp_json_encode( $aval ) ) );
						if ( $label && $value ) {
							$parts[] = $label . ': ' . $value;
						}
					}
				}
			}
		}

		return implode( ', ', $parts );
	}

	private function format_price_display( float $amount ): ?string {
		if ( $amount <= 0 ) {
			return null;
		}

		$text = wp_strip_all_tags( wc_price( $amount ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );

		return trim( $text );
	}

}
