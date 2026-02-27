<?php
/**
 * Contract for HavenCore MCP abilities V1 registrars.
 *
 * @package HavenCore\Mcp\Contracts
 */

namespace HavenCore\Mcp\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the static API expected from domain ability registrars.
 */
interface AbilitiesV1RegistrarContract {
	/**
	 * Register ability categories.
	 *
	 * @return void
	 */
	public static function register_categories(): void;

	/**
	 * Register abilities.
	 *
	 * @return void
	 */
	public static function register(): void;

	/**
	 * Return ability IDs.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array;
}
