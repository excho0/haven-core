<?php
/**
 * Supplier payload helpers for MCP ability responses.
 *
 * @package HavenCore\Mcp\Utils
 */

namespace HavenCore\Mcp\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Shared mapper/sanitizer for supplier MCP ability handlers.
 */
class SupplierPayload {
	/**
	 * Normalize supplier model into an MCP response payload.
	 *
	 * @param object $supplier Supplier model instance.
	 * @return array<string,mixed>
	 */
	public static function format( $supplier ): array {
		$addresses = $supplier->get_address();
		$primary = is_array( $addresses ) && isset( $addresses[0] ) ? $addresses[0] : array();

		return array(
			'id'              => (int) $supplier->get_id(),
			'is_active'       => (bool) $supplier->is_active(),
			'name'            => (string) $supplier->get_name(),
			'email'           => (string) $supplier->get_email(),
			'paypal_email'    => (string) $supplier->get_paypal_email(),
			'phone'           => (string) $supplier->get_phone(),
			'locale'          => (string) $supplier->get_locale(),
			'country'         => (string) ( $primary['country'] ?? '' ),
			'addresses'       => is_array( $addresses ) ? $addresses : array(),
			'socials'         => $supplier->get_socials() ?: array(),
			'attributes'      => $supplier->get_attributes() ?: array(),
			'assigned_orders' => $supplier->get_assigned_orders() ?: array(),
		);
	}

	/**
	 * Sanitize socials payload.
	 *
	 * @param mixed $socials Raw socials payload.
	 * @return array<int,array{type:string,url:string}>
	 */
	public static function sanitize_socials( $socials ): array {
		if ( ! is_array( $socials ) ) {
			return array();
		}

		$items = array_map(
			static function ( $entry ) {
				$type = sanitize_text_field( (string) ( $entry['type'] ?? '' ) );
				$url = esc_url_raw( (string) ( $entry['url'] ?? '' ) );
				if ( '' === $type || '' === $url ) {
					return null;
				}
				return array(
					'type' => $type,
					'url'  => $url,
				);
			},
			$socials
		);

		return array_values( array_filter( $items ) );
	}
}

// Backward compatibility for previous namespace.
if ( ! class_exists( 'HavenCore\\Mcp\\Support\\SupplierPayload', false ) ) {
	class_alias( SupplierPayload::class, 'HavenCore\\Mcp\\Support\\SupplierPayload' );
}
