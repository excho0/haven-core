<?php
/**
 * HavenCore MCP server (V1) for supplier abilities.
 *
 * @package HavenCore\Mcp\Servers\V1
 */

namespace HavenCore\Mcp\Servers\V1;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the versioned MCP server and exposes V1 supplier abilities.
 */
class SupplierMcpServerV1 {
	/**
	 * Server ID used by WP-CLI: `wp mcp-adapter serve --server=...`.
	 */
	private const SERVER_ID = 'havencore-mcp-v1';

	/**
	 * REST namespace for HTTP transport.
	 */
	private const SERVER_NAMESPACE = 'havencore-mcp-v1';

	/**
	 * REST route for MCP endpoint.
	 */
	private const SERVER_ROUTE = 'mcp';

	/**
	 * Ability map exposed as MCP tools for this server.
	 *
	 * @var string[]
	 */
	private const ABILITIES = array(
		'havencore/v1/suppliers-list',
		'havencore/v1/supplier-get',
		'havencore/v1/supplier-create',
		'havencore/v1/supplier-update',
	);

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
			self::SERVER_NAMESPACE,
			self::SERVER_ROUTE,
			'HavenCore MCP Server v1',
			'HavenCore supplier management tools (v1).',
			defined( 'HAVEN_CORE_VERSION' ) ? HAVEN_CORE_VERSION : '0.0.0',
			array(
				\WP\MCP\Transport\HttpTransport::class,
			),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			self::ABILITIES
		);
	}
}

