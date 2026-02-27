<?php
/**
 * Registers HavenCore V1 supplier abilities for MCP adapter.
 *
 * @package HavenCore\Mcp\Abilities\V1\Suppliers
 */

namespace HavenCore\Mcp\Abilities\V1\Suppliers;

use HavenCore\Mcp\Contracts\AbilitiesV1RegistrarContract;
use HavenCore\Mcp\Utils\SupplierPayload;
use HavenCore\Services\HC_Supplier_Service;
use HavenCore\Settings\Notifications;
use HavenCore\Utils\UserUtils;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Supplier ability registration and execution callbacks (V1).
 */
class SupplierAbilitiesV1Registrar implements AbilitiesV1RegistrarContract {
	/**
	 * Ability namespace.
	 */
	private const ABILITY_NAMESPACE = 'havencore';

	/**
	 * Default ability categories.
	 *
	 * @var array<string,array{slug:string,label:string,description:string}>
	 */
	private const CATEGORY_DEFINITIONS = array(
		'suppliers' => array(
			'slug'        => 'havencore-suppliers',
			'label'       => 'HavenCore Suppliers',
			'description' => 'Supplier management abilities exposed by HavenCore.',
		),
	);

	/**
	 * Register supplier ability categories.
	 *
	 * @return void
	 */
	public static function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		foreach ( self::get_category_definitions() as $definition ) {
			$slug = sanitize_key( (string) ( $definition['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			if ( function_exists( 'wp_is_ability_category_registered' ) && wp_is_ability_category_registered( $slug ) ) {
				continue;
			}

			wp_register_ability_category(
				$slug,
				array(
					'label'       => (string) ( $definition['label'] ?? $slug ),
					'description' => (string) ( $definition['description'] ?? '' ),
				)
			);
		}
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		self::register_categories();
	}

	/**
	 * Register all supplier abilities for API version v1.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			self::ability_id( 'suppliers-list' ),
			'List suppliers with optional filters and pagination.',
			array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array( 'type' => 'string' ),
					'status'   => array( 'type' => 'string', 'enum' => array( 'all', 'active', 'inactive' ) ),
					'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			),
			array(
				'type'       => 'object',
				'properties' => array(
					'items' => array( 'type' => 'array' ),
					'meta'  => array( 'type' => 'object' ),
				),
			),
			'suppliers',
			array( self::class, 'execute_suppliers_list' )
		);

		self::register_ability(
			self::ability_id( 'supplier-get' ),
			'Get supplier by ID.',
			array(
				'type'       => 'object',
				'required'   => array( 'supplier_id' ),
				'properties' => array(
					'supplier_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			'suppliers',
			array( self::class, 'execute_supplier_get' )
		);

		self::register_ability(
			self::ability_id( 'supplier-create' ),
			'Create a supplier.',
			array(
				'type'       => 'object',
				'required'   => array( 'name', 'email' ),
				'properties' => array(
					'name'         => array( 'type' => 'string' ),
					'email'        => array( 'type' => 'string' ),
					'paypal_email' => array( 'type' => 'string' ),
					'phone'        => array( 'type' => 'string' ),
					'country'      => array( 'type' => 'string' ),
					'locale'       => array( 'type' => 'string' ),
					'is_active'    => array( 'type' => 'boolean' ),
					'socials'      => array( 'type' => 'array' ),
					'attributes'   => array( 'type' => 'object' ),
				),
			),
			array( 'type' => 'object' ),
			'suppliers',
			array( self::class, 'execute_supplier_create' )
		);

		self::register_ability(
			self::ability_id( 'supplier-update' ),
			'Update supplier by ID.',
			array(
				'type'       => 'object',
				'required'   => array( 'supplier_id' ),
				'properties' => array(
					'supplier_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'name'         => array( 'type' => 'string' ),
					'email'        => array( 'type' => 'string' ),
					'paypal_email' => array( 'type' => 'string' ),
					'phone'        => array( 'type' => 'string' ),
					'country'      => array( 'type' => 'string' ),
					'locale'       => array( 'type' => 'string' ),
					'is_active'    => array( 'type' => 'boolean' ),
					'socials'      => array( 'type' => 'array' ),
					'attributes'   => array( 'type' => 'object' ),
				),
			),
			array( 'type' => 'object' ),
			'suppliers',
			array( self::class, 'execute_supplier_update' )
		);

		self::register_ability(
			self::ability_id( 'supplier-delete' ),
			'Delete supplier by ID with safety checks.',
			array(
				'type'       => 'object',
				'required'   => array( 'supplier_id' ),
				'properties' => array(
					'supplier_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'force_delete' => array( 'type' => 'boolean' ),
					'reassign'     => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			'suppliers',
			array( self::class, 'execute_supplier_delete' )
		);
	}

	/**
	 * Return ability IDs exposed by this registrar.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array {
		return array(
			self::ability_id( 'suppliers-list' ),
			self::ability_id( 'supplier-get' ),
			self::ability_id( 'supplier-create' ),
			self::ability_id( 'supplier-update' ),
			self::ability_id( 'supplier-delete' ),
		);
	}

	/**
	 * Build a compliant ability ID.
	 *
	 * WordPress abilities allow one "/" separator, so we use:
	 * havencore/<ability-name>.
	 *
	 * @param string $ability_name Ability slug.
	 * @return string
	 */
	public static function ability_id( string $ability_name ): string {
		$ability_name = strtolower( preg_replace( '/[^a-z0-9-]+/', '-', $ability_name ) );
		return self::ABILITY_NAMESPACE . '/' . trim( $ability_name, '-' );
	}

	/**
	 * Return category definitions (filterable for future categories).
	 *
	 * @return array<string,array{slug:string,label:string,description:string}>
	 */
	private static function get_category_definitions(): array {
		return apply_filters( 'havencore_mcp_ability_categories_v1', self::CATEGORY_DEFINITIONS );
	}

	/**
	 * Resolve category key to category slug.
	 *
	 * @param string $category_key Category key.
	 * @return string
	 */
	private static function get_category_slug( string $category_key ): string {
		$definitions = self::get_category_definitions();
		if ( isset( $definitions[ $category_key ]['slug'] ) ) {
			return sanitize_key( (string) $definitions[ $category_key ]['slug'] );
		}
		return sanitize_key( $category_key );
	}

	/**
	 * Register one ability with shared defaults.
	 *
	 * @param string   $name Ability ID.
	 * @param string   $description Human description.
	 * @param array    $input_schema Input schema.
	 * @param array    $output_schema Output schema.
	 * @param string   $category_key Ability category key.
	 * @param callable $execute_callback Execute callback.
	 * @return void
	 */
	private static function register_ability( string $name, string $description, array $input_schema, array $output_schema, string $category_key, callable $execute_callback ): void {
		wp_register_ability(
			$name,
			array(
				'label'               => $description,
				'category'            => self::get_category_slug( $category_key ),
				'description'         => $description,
				'input_schema'        => $input_schema,
				'output_schema'       => $output_schema,
				'permission_callback' => array( self::class, 'permissions_check' ),
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
	 * Default permission check for supplier abilities.
	 *
	 * @return bool
	 */
	public static function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List suppliers ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>
	 */
	public static function execute_suppliers_list( array $args ): array {
		$service = new HC_Supplier_Service();
		$suppliers = $service->all();

		$search = strtolower( sanitize_text_field( (string) ( $args['search'] ?? '' ) ) );
		$status = sanitize_text_field( (string) ( $args['status'] ?? 'all' ) );
		$page = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );

		$filtered = array_values(
			array_filter(
				$suppliers,
				static function ( $supplier ) use ( $search, $status ) {
					if ( 'active' === $status && ! $supplier->is_active() ) {
						return false;
					}
					if ( 'inactive' === $status && $supplier->is_active() ) {
						return false;
					}
					if ( '' !== $search ) {
						$haystack = strtolower( (string) $supplier->get_name() . ' ' . (string) $supplier->get_email() . ' ' . (string) $supplier->get_phone() );
						return strpos( $haystack, $search ) !== false;
					}
					return true;
				}
			)
		);

		$total = count( $filtered );
		$offset = ( $page - 1 ) * $per_page;
		$slice = array_slice( $filtered, $offset, $per_page );

		return array(
			'items' => array_map( array( SupplierPayload::class, 'format' ), $slice ),
			'meta'  => array(
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'pages'    => (int) ceil( $total / $per_page ),
			),
		);
	}

	/**
	 * Get supplier ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_supplier_get( array $args ) {
		$supplier_id = absint( $args['supplier_id'] ?? 0 );
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier_id', 'Invalid supplier ID.' );
		}

		$service = new HC_Supplier_Service();
		$supplier = $service->get( $supplier_id );
		if ( ! $supplier ) {
			return new WP_Error( 'supplier_not_found', 'Supplier not found.' );
		}

		return SupplierPayload::format( $supplier );
	}

	/**
	 * Create supplier ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_supplier_create( array $args ) {
		$name = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
		$email = sanitize_email( (string) ( $args['email'] ?? '' ) );
		$phone = sanitize_text_field( (string) ( $args['phone'] ?? '' ) );
		$locale = sanitize_text_field( (string) ( $args['locale'] ?? '' ) );
		$paypal_email = sanitize_email( (string) ( $args['paypal_email'] ?? '' ) );
		$country = sanitize_text_field( (string) ( $args['country'] ?? '' ) );
		$is_active = isset( $args['is_active'] ) ? (bool) $args['is_active'] : true;

		if ( '' === $name || '' === $email ) {
			return new WP_Error( 'invalid_fields', 'Name and email are required.' );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Invalid supplier email.' );
		}
		if ( UserUtils::is_protected_user( $email ) ) {
			return new WP_Error( 'forbidden_email', 'Protected admin email cannot be used.' );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', 'Email already exists.' );
		}

		$socials = SupplierPayload::sanitize_socials( $args['socials'] ?? array() );
		$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();

		$service = new HC_Supplier_Service();
		$supplier = $service->create(
			array(
				'name'         => $name,
				'email'        => $email,
				'phone'        => $phone,
				'locale'       => $locale,
				'paypal_email' => $paypal_email,
				'addresses'    => array( array( 'country' => $country ) ),
				'socials'      => $socials,
				'attributes'   => $attributes,
				'is_active'    => $is_active,
			)
		);

		if ( ! $supplier ) {
			return new WP_Error( 'create_failed', 'Failed to create supplier.' );
		}

		if ( Notifications::supplierWelcomeEmailEnabled() ) {
			HC_Supplier_Service::scheduleWelcomeEmail( $email, $name, (string) $supplier->get_id(), (string) $supplier->get_password() );
		}

		return SupplierPayload::format( $supplier );
	}

	/**
	 * Update supplier ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_supplier_update( array $args ) {
		$supplier_id = absint( $args['supplier_id'] ?? 0 );
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier_id', 'Invalid supplier ID.' );
		}

		$service = new HC_Supplier_Service();
		$current = $service->get( $supplier_id );
		if ( ! $current ) {
			return new WP_Error( 'supplier_not_found', 'Supplier not found.' );
		}

		$update = array();
		if ( array_key_exists( 'name', $args ) ) {
			$update['name'] = sanitize_text_field( (string) $args['name'] );
		}
		if ( array_key_exists( 'email', $args ) ) {
			$email = sanitize_email( (string) $args['email'] );
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', 'Invalid supplier email.' );
			}
			$existing_user = get_user_by( 'email', $email );
			if ( $existing_user && (int) $existing_user->ID !== $supplier_id ) {
				return new WP_Error( 'email_exists', 'Email already exists.' );
			}
			if ( UserUtils::is_protected_user( $email ) ) {
				return new WP_Error( 'forbidden_email', 'Protected admin email cannot be used.' );
			}
			$update['email'] = $email;
		}
		if ( array_key_exists( 'phone', $args ) ) {
			$update['phone'] = sanitize_text_field( (string) $args['phone'] );
		}
		if ( array_key_exists( 'locale', $args ) ) {
			$update['locale'] = sanitize_text_field( (string) $args['locale'] );
		}
		if ( array_key_exists( 'paypal_email', $args ) ) {
			$update['paypal_email'] = sanitize_email( (string) $args['paypal_email'] );
		}
		if ( array_key_exists( 'is_active', $args ) ) {
			$update['is_active'] = (bool) $args['is_active'];
		}
		if ( array_key_exists( 'country', $args ) ) {
			$addresses = $current->get_address();
			$first = is_array( $addresses ) && isset( $addresses[0] ) ? $addresses[0] : array();
			$first['country'] = sanitize_text_field( (string) $args['country'] );
			$update['addresses'] = array( $first );
		}
		if ( array_key_exists( 'socials', $args ) ) {
			$update['socials'] = SupplierPayload::sanitize_socials( $args['socials'] );
		}
		if ( array_key_exists( 'attributes', $args ) && is_array( $args['attributes'] ) ) {
			$update['attributes'] = $args['attributes'];
		}

		if ( empty( $update ) ) {
			return new WP_Error( 'no_fields_to_update', 'No valid fields were provided.' );
		}

		if ( ! $service->update( $supplier_id, $update ) ) {
			return new WP_Error( 'update_failed', 'Failed to update supplier.' );
		}

		$updated = $service->get( $supplier_id );
		return SupplierPayload::format( $updated );
	}

	/**
	 * Delete supplier ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_supplier_delete( array $args ) {
		$supplier_id = absint( $args['supplier_id'] ?? 0 );
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier_id', 'Invalid supplier ID.' );
		}

		$service = new HC_Supplier_Service();
		$result = $service->delete_with_safeguards(
			$supplier_id,
			array(
				'force'    => ! empty( $args['force_delete'] ),
				'reassign' => isset( $args['reassign'] ) ? absint( $args['reassign'] ) : null,
			)
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$pending_fulfillment_order_ids = array();
			if ( is_array( $error_data ) ) {
				$raw_orders = $error_data['pending_fulfillment_order_ids'] ?? array();
				if ( is_array( $raw_orders ) ) {
					$pending_fulfillment_order_ids = array_values( array_filter( array_map( 'absint', $raw_orders ) ) );
				}
			}

			return array(
				'deleted'            => false,
				'supplier_id'        => $supplier_id,
				'reason'             => is_array( $error_data ) ? (string) ( $error_data['reason'] ?? $result->get_error_code() ) : $result->get_error_code(),
				'message'            => $result->get_error_message(),
				'pending_fulfillment_order_ids' => $pending_fulfillment_order_ids,
			);
		}

		return $result;
	}
}
