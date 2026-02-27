<?php
/**
 * MCP bootstrap.
 *
 * @package HavenCore\Mcp\Bootstrap
 */

namespace HavenCore\Mcp\Bootstrap;

use HavenCore\Mcp\Abilities\V1\McpAbilitiesV1Registry;
use HavenCore\Mcp\Servers\V1\HavencoreMcpServerV1;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps MCP adapter integration and V1 modules.
 */
class McpBootstrap {
	/**
	 * Initialize MCP integration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return;
		}

		\WP\MCP\Core\McpAdapter::instance();

		// Register custom ability categories first.
		add_action( 'wp_abilities_api_categories_init', array( McpAbilitiesV1Registry::class, 'register_categories' ), 20 );

		// Safety fallback in case categories hook already fired.
		if ( did_action( 'wp_abilities_api_categories_init' ) ) {
			McpAbilitiesV1Registry::register_categories();
		}

		// WordPress 6.9+ requires abilities to be registered on this hook.
		add_action( 'wp_abilities_api_init', array( McpAbilitiesV1Registry::class, 'register' ), 20 );

		// Safety fallback in case this hook has already fired before plugin bootstrap.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			McpAbilitiesV1Registry::register();
		}

		add_action( 'mcp_adapter_init', array( HavencoreMcpServerV1::class, 'register' ) );
	}
}

// Backward compatibility for previous namespace.
if ( ! class_exists( 'HavenCore\\Mcp\\Core\\McpBootstrap', false ) ) {
	class_alias( McpBootstrap::class, 'HavenCore\\Mcp\\Core\\McpBootstrap' );
}
