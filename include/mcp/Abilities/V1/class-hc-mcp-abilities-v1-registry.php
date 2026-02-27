<?php
/**
 * HavenCore MCP abilities registry for API V1.
 *
 * @package HavenCore\Mcp\Abilities\V1
 */

namespace HavenCore\Mcp\Abilities\V1;

use HavenCore\Mcp\Abilities\V1\Messaging\MessagingAbilitiesV1Registrar;
use HavenCore\Mcp\Abilities\V1\Orders\OrdersAbilitiesV1Registrar;
use HavenCore\Mcp\Abilities\V1\Settings\SettingsAbilitiesV1Registrar;
use HavenCore\Mcp\Abilities\V1\Suppliers\SupplierAbilitiesV1Registrar;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates all MCP domain registrars for API V1.
 */
class McpAbilitiesV1Registry {
	/**
	 * Built-in registrar classes.
	 *
	 * @var string[]
	 */
	private const REGISTRARS = array(
		SupplierAbilitiesV1Registrar::class,
		MessagingAbilitiesV1Registrar::class,
		OrdersAbilitiesV1Registrar::class,
		SettingsAbilitiesV1Registrar::class,
	);

	/**
	 * Register categories for all active registrars.
	 *
	 * @return void
	 */
	public static function register_categories(): void {
		foreach ( self::get_registrars() as $registrar_class ) {
			if ( is_callable( array( $registrar_class, 'register_categories' ) ) ) {
				$registrar_class::register_categories();
			}
		}
	}

	/**
	 * Register abilities for all active registrars.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::get_registrars() as $registrar_class ) {
			if ( is_callable( array( $registrar_class, 'register' ) ) ) {
				$registrar_class::register();
			}
		}
	}

	/**
	 * Collect and deduplicate ability IDs across registrars.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array {
		$ability_ids = array();

		foreach ( self::get_registrars() as $registrar_class ) {
			if ( is_callable( array( $registrar_class, 'get_ability_ids' ) ) ) {
				$ids = $registrar_class::get_ability_ids();
				if ( is_array( $ids ) ) {
					$ability_ids = array_merge( $ability_ids, $ids );
				}
			}
		}

		$ability_ids = array_map( 'strval', $ability_ids );
		return array_values( array_unique( array_filter( $ability_ids ) ) );
	}

	/**
	 * Return registrar classes (filterable).
	 *
	 * @return string[]
	 */
	private static function get_registrars(): array {
		$registrars = apply_filters( 'havencore_mcp_ability_registrars_v1', self::REGISTRARS );
		if ( ! is_array( $registrars ) ) {
			return self::REGISTRARS;
		}

		return array_values( array_filter( $registrars, 'is_string' ) );
	}
}
