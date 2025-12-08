<?php

namespace HavenCore\Utils;

defined('ABSPATH') || exit;

/**
 * Provides carrier definitions for supplier tracking (re-using PayPal data when available).
 */
class Tracking_Carriers {

	/**
	 * Returns carrier groups keyed by region, matching PayPal's structure when available.
	 *
	 * @return array
	 */
	public static function get_carrier_groups(): array {
		$paypal_groups = self::load_paypal_carriers();
		if ( ! empty( $paypal_groups ) ) {
			return self::filter_paypal_groups( $paypal_groups );
		}

		return self::fallback_carriers();
	}

	/**
	 * Attempts to load the WooCommerce PayPal Payments carrier list.
	 *
	 * @return array|null
	 */
	private static function load_paypal_carriers(): ?array {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return null;
		}

		$path = WP_PLUGIN_DIR . '/woocommerce-paypal-payments/modules/ppcp-order-tracking/carriers.php';
		if ( ! file_exists( $path ) ) {
			return null;
		}

		try {
			$carriers = require $path;
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_array( $carriers ) ? $carriers : null;
	}

	/**
	 * Mimics PayPal Payments behaviour by returning only base-country, global and other.
	 *
	 * @param array $groups Raw carrier definition from PayPal plugin.
	 * @return array
	 */
	private static function filter_paypal_groups( array $groups ): array {
		$output       = [];
		$base_country = '';

		if ( function_exists( 'wc_get_base_location' ) ) {
			$location     = wc_get_base_location();
			$base_country = strtoupper( $location['country'] ?? '' );
		}

		if ( $base_country && isset( $groups[ $base_country ] ) ) {
			$output[ $base_country ] = $groups[ $base_country ];
		}

		if ( isset( $groups['global'] ) ) {
			$output['global'] = $groups['global'];
		}

		$output['other'] = [
			'name'  => __( 'Other', HAVEN_CORE_TEXT_DOMAIN ),
			'items' => [
				'OTHER' => __( 'Other / Custom', HAVEN_CORE_TEXT_DOMAIN ),
			],
		];

		return $output;
	}

	/**
	 * Safe fallback list when PayPal Payments is not installed.
	 *
	 * @return array
	 */
	private static function fallback_carriers(): array {
		return [
			'global' => [
				'name'  => 'Global',
				'items' => [
					'DHL'           => 'DHL Express',
					'DHL_ECOM'      => 'DHL eCommerce',
					'FEDEX'         => 'FedEx',
					'UPS'           => 'UPS',
					'USPS'          => 'USPS',
					'CANADA_POST'   => 'Canada Post',
					'AUSTRALIAPOST' => 'Australia Post',
					'ROYAL_MAIL'    => 'Royal Mail',
					'HERMES'        => 'Evri (Hermes)',
					'GLS'           => 'GLS',
					'TNT'           => 'TNT Express',
					'ARAMEX'        => 'Aramex',
					'DPD'           => 'DPD',
					'POSTNORD'      => 'PostNord',
					'YODEL'         => 'Yodel',
					'COLISSIMO'     => 'Colissimo',
				],
			],
			'other'  => [
				'name'  => 'Other',
				'items' => [
					'OTHER' => __( 'Other / Custom', HAVEN_CORE_TEXT_DOMAIN ),
				],
			],
		];
	}
}
