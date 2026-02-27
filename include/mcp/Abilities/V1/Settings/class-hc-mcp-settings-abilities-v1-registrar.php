<?php
/**
 * Registers HavenCore V1 settings abilities for MCP adapter.
 *
 * @package HavenCore\Mcp\Abilities\V1\Settings
 */

namespace HavenCore\Mcp\Abilities\V1\Settings;

use HavenCore\Classes\HC_Settings;
use HavenCore\Mcp\Contracts\AbilitiesV1RegistrarContract;
use HavenCore\RestApi\Controllers\V1\HC_REST_Settings_V1_Controller;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Settings ability registration and execution callbacks (V1).
 */
class SettingsAbilitiesV1Registrar implements AbilitiesV1RegistrarContract {
	/**
	 * Ability namespace.
	 */
	private const ABILITY_NAMESPACE = 'havencore';

	/**
	 * Ability category slug.
	 */
	private const CATEGORY_SLUG = 'havencore-settings';

	/**
	 * Register settings ability categories.
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
				'label'       => 'HavenCore Settings',
				'description' => 'Configuration and admin settings abilities exposed by HavenCore.',
			)
		);
	}

	/**
	 * Register settings abilities.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			self::ability_id( 'settings-get' ),
			'Get HavenCore settings payload.',
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_settings_get' )
		);

		self::register_ability(
			self::ability_id( 'settings-update' ),
			'Update HavenCore settings with partial payload.',
			array(
				'type'       => 'object',
				'required'   => array( 'settings' ),
				'properties' => array(
					'settings' => array( 'type' => 'object' ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_settings_update' )
		);

		self::register_ability(
			self::ability_id( 'settings-schema-get' ),
			'Get HavenCore settings schema with metadata for each setting field.',
			array(
				'type'       => 'object',
				'properties' => array(
					'include_values' => array( 'type' => 'boolean' ),
					'include_hidden' => array( 'type' => 'boolean' ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_settings_schema_get' )
		);
	}

	/**
	 * Return ability IDs exposed by this registrar.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array {
		return array(
			self::ability_id( 'settings-get' ),
			self::ability_id( 'settings-update' ),
			self::ability_id( 'settings-schema-get' ),
		);
	}

	/**
	 * Admin permission check.
	 *
	 * @return bool
	 */
	public static function permissions_check_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_settings_get( array $args ) {
		unset( $args );

		$request = new WP_REST_Request( 'GET' );
		return self::rest_result_to_payload( self::settings_controller()->get_settings( $request ) );
	}

	/**
	 * Update settings callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_settings_update( array $args ) {
		if ( ! isset( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
			return new WP_Error( 'invalid_input', 'Expected settings object.' );
		}

		$request = new WP_REST_Request( 'PUT' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'settings' => $args['settings'],
				)
			)
		);

		$result = self::settings_controller()->save_settings( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$get_request = new WP_REST_Request( 'GET' );
		$current = self::settings_controller()->get_settings( $get_request );
		$current_payload = self::rest_result_to_payload( $current );
		if ( is_wp_error( $current_payload ) ) {
			return $current_payload;
		}

		return array(
			'message'  => 'Settings saved.',
			'settings' => $current_payload['settings'] ?? $current_payload,
		);
	}

	/**
	 * Get settings schema callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>
	 */
	public static function execute_settings_schema_get( array $args ): array {
		$include_values = array_key_exists( 'include_values', $args ) ? (bool) $args['include_values'] : true;
		$include_hidden = array_key_exists( 'include_hidden', $args ) ? (bool) $args['include_hidden'] : false;

		$settings = new HC_Settings( true );
		$schema = HC_Settings::$settingSchema;
		$fields = array();

		self::collect_schema_fields( $schema, '', $fields, $settings, $include_values, $include_hidden );

		return array(
			'schema' => array(
				'fields' => $fields,
			),
			'meta'   => array(
				'total_fields'   => count( $fields ),
				'include_values' => $include_values,
				'include_hidden' => $include_hidden,
			),
		);
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
	 * Return settings REST controller instance.
	 *
	 * @return HC_REST_Settings_V1_Controller
	 */
	private static function settings_controller(): HC_REST_Settings_V1_Controller {
		return new HC_REST_Settings_V1_Controller();
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

	/**
	 * Flatten schema into field descriptors for agents.
	 *
	 * @param array       $node Schema node.
	 * @param string      $path Current dot path.
	 * @param array       $fields Accumulator.
	 * @param HC_Settings $settings Settings object (for current values).
	 * @param bool        $include_values Whether to include current values.
	 * @param bool        $include_hidden Whether to include hidden fields.
	 * @return void
	 */
	private static function collect_schema_fields( array $node, string $path, array &$fields, HC_Settings $settings, bool $include_values, bool $include_hidden ): void {
		foreach ( $node as $name => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$current_path = '' === $path ? (string) $name : $path . '.' . $name;

			if ( isset( $definition['key'] ) && array_key_exists( 'default', $definition ) ) {
				$is_hidden = ! empty( $definition['hidden'] );
				if ( $is_hidden && ! $include_hidden ) {
					continue;
				}

				$default_value = $definition['default'];
				$field = array(
					'path'          => $current_path,
					'storage_key'   => (string) $definition['key'],
					'label'         => self::humanize_label( (string) $name ),
					'type'          => self::infer_schema_type( $default_value ),
					'default'       => $default_value,
					'tooltip'       => (string) ( $definition['tooltip'] ?? '' ),
					'icon'          => (string) ( $definition['icon'] ?? '' ),
					'hidden'        => $is_hidden,
					'disabled_when' => isset( $definition['disabled_when'] ) && is_array( $definition['disabled_when'] ) ? $definition['disabled_when'] : null,
				);

				if ( array_key_exists( 'enum', $definition ) && is_array( $definition['enum'] ) ) {
					$field['enum'] = array_values( $definition['enum'] );
				}
				if ( array_key_exists( 'options', $definition ) && is_array( $definition['options'] ) ) {
					$field['options'] = $definition['options'];
				}

				if ( $include_values ) {
					$field['value'] = $settings->get( $current_path );
				}

				$fields[] = $field;
				continue;
			}

			self::collect_schema_fields( $definition, $current_path, $fields, $settings, $include_values, $include_hidden );
		}
	}

	/**
	 * Infer JSON-like type from PHP value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function infer_schema_type( $value ): string {
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		if ( is_int( $value ) ) {
			return 'integer';
		}
		if ( is_float( $value ) ) {
			return 'number';
		}
		if ( is_array( $value ) ) {
			return self::is_assoc_array( $value ) ? 'object' : 'array';
		}
		if ( is_null( $value ) ) {
			return 'null';
		}

		return 'string';
	}

	/**
	 * Determine whether array is associative.
	 *
	 * @param array $value Value.
	 * @return bool
	 */
	private static function is_assoc_array( array $value ): bool {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	/**
	 * Humanize schema key for display.
	 *
	 * @param string $segment Dot path segment.
	 * @return string
	 */
	private static function humanize_label( string $segment ): string {
		return ucwords( str_replace( '_', ' ', $segment ) );
	}
}
