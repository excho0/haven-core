<?php
/**
 * HavenCore MCP adapter compatibility entrypoint.
 *
 * @package HavenCore\Mcp
 */

namespace HavenCore\Mcp;

use HavenCore\Mcp\Bootstrap\McpBootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Backward-compatible wrapper that delegates to the versioned MCP bootstrap.
 */
class HC_MCP_Adapter {
	/**
	 * Boot MCP adapter integration.
	 *
	 * @return void
	 */
	public static function init(): void {
		McpBootstrap::init();
	}
}
