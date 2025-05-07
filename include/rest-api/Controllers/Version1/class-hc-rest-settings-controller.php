<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HavenCore\Classes\HC_Settings;
use HavenCore\Utils\ArrayHelpers;

class HC_REST_Settings_V1_Controller extends HC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'save_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( 'PUT' ),
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_settings' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function save_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$rawInput = $body['settings'] ?? [];

		if ( ! is_array( $rawInput ) ) {
			return new WP_Error( 'invalid_input', 'Invalid input format. Expected a settings object.', [ 'status' => 400 ] );
		}

		$settings = new HC_Settings( false );
		$current  = json_decode( json_encode( $settings->getAll() ), true );

		$nestedRef = ArrayHelpers::buildReferenceMap(
			HC_Settings::$settingSchema,
			$current
		);

		$sanitized = ArrayHelpers::sanitizeRecursive( $rawInput, '', $nestedRef );
		$flat = ArrayHelpers::flatten( $sanitized );

		foreach ( $flat as $dotKey => $value ) {
			$settings->set( $dotKey, $value );
		}

		if ( $settings->save() ) {
			return new WP_REST_Response( [ 'message' => 'Settings saved.' ], 200 );
		}

		return new WP_Error( 'settings_save_failed', 'Failed to save settings.', [ 'status' => 500 ] );
	}

	public function get_settings( WP_REST_Request $request ) {
		$settings = new HC_Settings();
		$data     = $settings->getAll();

		return new WP_REST_Response( [ 'settings' => $data ], 200 );
	}

	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => [
				'settings' => [
					'description' => 'Settings key-value pairs.',
					'type'        => 'object',
					'context'     => [ 'edit' ],
				],
			],
		];
	}
}
