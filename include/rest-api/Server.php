<?php
/**
 * REST API Server Bootstrapper
 *
 * @package HavenCore\RestApi
 */

namespace HavenCore\RestApi;

defined( 'ABSPATH' ) || exit;

use HavenCore\RestApi\Utilities\SingletonTrait;

/**
 * Class responsible for registering REST API endpoints.
 */
class Server {
	use SingletonTrait;

	/**
	 * Boot hook.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
	}

	/**
	 * Registers all REST routes across namespaces.
	 */
	public function register_rest_routes() {
		foreach ( $this->get_rest_namespaces() as $namespace => $controllers ) {
			foreach ( $controllers as $controller_class ) {
				if ( class_exists( $controller_class ) ) {
					( new $controller_class() )->register_routes();
				}
			}
		}
	}

	/**
	 * Define API namespaces and controller class map.
	 *
	 * @return array
	 */
	protected function get_rest_namespaces() {
		return apply_filters(
			'havencore_rest_api_get_rest_namespaces',
			array(
				'hc/v1' => array(
					\HavenCore\RestApi\Controllers\V1\HC_REST_Settings_V1_Controller::class,
					\HavenCore\RestApi\Controllers\V1\HC_REST_Supplier_Portal_V1_Controller::class,
					\HavenCore\RestApi\Controllers\V1\HC_REST_CustomerCheckout_V1_Controller::class,
					\HavenCore\RestApi\Controllers\V1\HC_REST_Manage_Suppliers_V1_Controller::class,
					\HavenCore\RestApi\Controllers\V1\HC_REST_Account_V1_Controller::class,
					// Add other controller classes here...
				),
				'hc/v1/communications' => array(
					\HavenCore\RestApi\Controllers\V1\HC_REST_Supplier_Messaging_V1_Controller::class,
				),
			)
		);
	}
}
