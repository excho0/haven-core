<?php
/**
 * MCP bootstrap.
 *
 * @package HavenCore\Mcp\Core
 */

namespace HavenCore\Mcp\Core;

use HavenCore\Mcp\Abilities\V1\Suppliers\SupplierAbilitiesV1Registrar;
use HavenCore\Mcp\Servers\V1\SupplierMcpServerV1;

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

		add_action( 'init', array( SupplierAbilitiesV1Registrar::class, 'register' ), 20 );
		add_action( 'mcp_adapter_init', array( SupplierMcpServerV1::class, 'register' ) );
	}
}

