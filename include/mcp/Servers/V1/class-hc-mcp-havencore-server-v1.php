<?php
/**
 * HavenCore MCP server (V1) for HavenCore abilities.
 *
 * @package HavenCore\Mcp\Servers\V1
 */

namespace HavenCore\Mcp\Servers\V1;

use HavenCore\Mcp\Abilities\V1\McpAbilitiesV1Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the versioned MCP server and exposes V1 abilities.
 */
class HavencoreMcpServerV1 {
	/**
	 * Server ID used by WP-CLI: `wp mcp-adapter serve --server=...`.
	 */
	private const SERVER_ID = 'havencore-mcp-v1';

	/**
	 * Version route segment for HTTP transport.
	 */
	private const SERVER_ROUTE = 'v1';

	/**
	 * Register custom server in MCP adapter.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return;
		}

		$adapter = \WP\MCP\Core\McpAdapter::instance();

		$adapter->create_server(
			self::SERVER_ID,
			self::get_server_namespace(),
			self::get_server_route(),
			'HavenCore MCP Server v1',
			'HavenCore agent tools (v1).',
			defined( 'HAVEN_CORE_VERSION' ) ? HAVEN_CORE_VERSION : '0.0.0',
			array(
				\WP\MCP\Transport\HttpTransport::class,
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			McpAbilitiesV1Registry::get_ability_ids()
		);
	}

	/**
	 * Build a versioned namespace like: /wp-json/<prefix>/havencore/mcp/v1
	 *
	 * @return string
	 */
	private static function get_server_namespace(): string {
		$prefix = sanitize_key( (string) apply_filters( 'havencore_mcp_rest_prefix', 'hc' ) );
		if ( '' === $prefix ) {
			$prefix = 'hc';
		}

		return $prefix . '/havencore/mcp';
	}

	/**
	 * Return the route segment for this server version.
	 *
	 * @return string
	 */
	private static function get_server_route(): string {
		return self::SERVER_ROUTE;
	}
}

// Backward compatibility for previous class name.
if ( ! class_exists( 'HavenCore\\Mcp\\Servers\\V1\\SupplierMcpServerV1', false ) ) {
	class_alias( HavencoreMcpServerV1::class, 'HavenCore\\Mcp\\Servers\\V1\\SupplierMcpServerV1' );
}
