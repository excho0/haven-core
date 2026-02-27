<?php
/**
 * Registers HavenCore V1 order abilities for MCP adapter.
 *
 * @package HavenCore\Mcp\Abilities\V1\Orders
 */

namespace HavenCore\Mcp\Abilities\V1\Orders;

use HavenCore\Mcp\Contracts\AbilitiesV1RegistrarContract;
use HavenCore\RestApi\Controllers\V1\HC_REST_Supplier_Portal_V1_Controller;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Orders ability registration and execution callbacks (V1).
 */
class OrdersAbilitiesV1Registrar implements AbilitiesV1RegistrarContract {
	/**
	 * Ability namespace.
	 */
	private const ABILITY_NAMESPACE = 'havencore';

	/**
	 * Ability category slug.
	 */
	private const CATEGORY_SLUG = 'havencore-orders';

	/**
	 * Register orders ability categories.
	 *
	 * @return void
	 */
	public static function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_is_ability_category_registered' ) && wp_is_ability_category_registered( self::CATEGORY_SLUG ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => 'HavenCore Orders',
				'description' => 'Order and fulfillment abilities exposed by HavenCore.',
			)
		);
	}

	/**
	 * Register orders abilities.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			self::ability_id( 'order-suppliers-get' ),
			'Get supplier fulfillment cards for a WooCommerce order.',
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_order_suppliers_get' )
		);

		self::register_ability(
			self::ability_id( 'order-supplier-reassign' ),
			'Reassign supplier segment on an order from one supplier to another.',
			array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'from_supplier_id', 'to_supplier_id' ),
				'properties' => array(
					'order_id'         => array( 'type' => 'integer', 'minimum' => 1 ),
					'from_supplier_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'to_supplier_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_order_supplier_reassign' )
		);

		self::register_ability(
			self::ability_id( 'order-fulfillment-reset' ),
			'Reset supplier fulfillment state for an order (admin).',
			array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'supplier_id' ),
				'properties' => array(
					'order_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
					'supplier_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_order_fulfillment_reset' )
		);

		self::register_ability(
			self::ability_id( 'order-fulfillment-confirm' ),
			'Confirm fulfillment for current supplier on an order (supplier role).',
			array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_supplier' ),
			array( self::class, 'execute_order_fulfillment_confirm' )
		);

		self::register_ability(
			self::ability_id( 'supplier-assigned-orders-list' ),
			'List assigned orders for current supplier with filtering and pagination.',
			array(
				'type'       => 'object',
				'properties' => array(
					'status'   => array( 'type' => 'string' ),
					'search'   => array( 'type' => 'string' ),
					'sort'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ) ),
					'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_supplier' ),
			array( self::class, 'execute_supplier_assigned_orders_list' )
		);
	}

	/**
	 * Return ability IDs exposed by this registrar.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array {
		return array(
			self::ability_id( 'order-suppliers-get' ),
			self::ability_id( 'order-supplier-reassign' ),
			self::ability_id( 'order-fulfillment-reset' ),
			self::ability_id( 'order-fulfillment-confirm' ),
			self::ability_id( 'supplier-assigned-orders-list' ),
		);
	}

	/**
	 * Admin permission check.
	 *
	 * @return bool
	 */
	public static function permissions_check_admin(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Supplier permission check.
	 *
	 * @return bool
	 */
	public static function permissions_check_supplier(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( function_exists( 'wc_current_user_has_role' ) ) {
			return wc_current_user_has_role( 'supplier' );
		}

		$user = wp_get_current_user();
		return in_array( 'supplier', (array) $user->roles, true );
	}

	/**
	 * Get order suppliers callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_order_suppliers_get( array $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		if ( ! $order_id ) {
			return new WP_Error( 'invalid_params', 'Missing order_id.' );
		}

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'order_id', $order_id );

		return self::rest_result_to_payload( self::portal_controller()->get_order_suppliers( $request ) );
	}

	/**
	 * Reassign order supplier callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_order_supplier_reassign( array $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		$from_id  = absint( $args['from_supplier_id'] ?? 0 );
		$to_id    = absint( $args['to_supplier_id'] ?? 0 );

		if ( ! $order_id || ! $from_id || ! $to_id ) {
			return new WP_Error( 'invalid_params', 'Missing required parameters.' );
		}

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', $order_id );
		$request->set_param( 'from_supplier_id', $from_id );
		$request->set_param( 'to_supplier_id', $to_id );

		return self::rest_result_to_payload( self::portal_controller()->reassign_order_supplier( $request ) );
	}

	/**
	 * Reset supplier fulfillment callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_order_fulfillment_reset( array $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		$supplier_id = absint( $args['supplier_id'] ?? 0 );

		if ( ! $order_id || ! $supplier_id ) {
			return new WP_Error( 'invalid_params', 'Missing order_id or supplier_id.' );
		}

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', $order_id );
		$request->set_param( 'supplier_id', $supplier_id );

		return self::rest_result_to_payload( self::portal_controller()->reset_fulfillment( $request ) );
	}

	/**
	 * Confirm fulfillment callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_order_fulfillment_confirm( array $args ) {
		$order_id = absint( $args['order_id'] ?? 0 );
		if ( ! $order_id ) {
			return new WP_Error( 'invalid_params', 'Missing order_id.' );
		}

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', $order_id );

		return self::rest_result_to_payload( self::portal_controller()->confirm_fulfillment( $request ) );
	}

	/**
	 * List assigned supplier orders callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_supplier_assigned_orders_list( array $args ) {
		$request = new WP_REST_Request( 'GET' );

		if ( isset( $args['status'] ) ) {
			$request->set_param( 'status', sanitize_text_field( (string) $args['status'] ) );
		}
		if ( isset( $args['search'] ) ) {
			$request->set_param( 'search', sanitize_text_field( (string) $args['search'] ) );
		}
		if ( isset( $args['sort'] ) ) {
			$request->set_param( 'sort', sanitize_text_field( (string) $args['sort'] ) );
		}
		if ( isset( $args['page'] ) ) {
			$request->set_param( 'page', max( 1, absint( $args['page'] ) ) );
		}
		if ( isset( $args['per_page'] ) ) {
			$request->set_param( 'per_page', max( 1, min( 100, absint( $args['per_page'] ) ) ) );
		}

		return self::rest_result_to_payload( self::portal_controller()->get_assigned_orders( $request ) );
	}

	/**
	 * Build compliant ability ID.
	 *
	 * @param string $ability_name Ability slug.
	 * @return string
	 */
	private static function ability_id( string $ability_name ): string {
		$ability_name = strtolower( preg_replace( '/[^a-z0-9-]+/', '-', $ability_name ) );
		return self::ABILITY_NAMESPACE . '/' . trim( $ability_name, '-' );
	}

	/**
	 * Register one ability with shared defaults.
	 *
	 * @param string   $name Ability ID.
	 * @param string   $description Human description.
	 * @param array    $input_schema Input schema.
	 * @param array    $output_schema Output schema.
	 * @param callable $permission_callback Permission callback.
	 * @param callable $execute_callback Execute callback.
	 * @return void
	 */
	private static function register_ability( string $name, string $description, array $input_schema, array $output_schema, callable $permission_callback, callable $execute_callback ): void {
		wp_register_ability(
			$name,
			array(
				'label'               => $description,
				'category'            => self::CATEGORY_SLUG,
				'description'         => $description,
				'input_schema'        => $input_schema,
				'output_schema'       => $output_schema,
				'permission_callback' => $permission_callback,
				'execute_callback'    => $execute_callback,
				'meta'                => array(
					'mcp' => array(
						'public' => false,
					),
				),
			)
		);
	}

	/**
	 * Return portal controller instance.
	 *
	 * @return HC_REST_Supplier_Portal_V1_Controller
	 */
	private static function portal_controller(): HC_REST_Supplier_Portal_V1_Controller {
		return new HC_REST_Supplier_Portal_V1_Controller();
	}

	/**
	 * Convert REST callback result into MCP payload or WP_Error.
	 *
	 * @param mixed $result REST callback result.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function rest_result_to_payload( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $result instanceof \WP_REST_Response ) {
			$data = $result->get_data();
			return is_array( $data ) ? $data : array( 'result' => $data );
		}

		if ( is_array( $result ) ) {
			return $result;
		}

		return array( 'result' => $result );
	}
}
