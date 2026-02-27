<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HavenCore\Services\HC_Supplier_Service;
use HavenCore\Utils\UserUtils;
use HavenCore\Classes\HC_Settings;
use HavenCore\Settings\Notifications;

/**
 * Class HC_REST_Manage_Suppliers_V1_Controller
 *
 * Handles REST API endpoints for managing suppliers within the system.
 * This includes listing, updating, adding, and removing supplier records.
 * Intended for admin use in the supplier management interface.
 *
 * @package HavenCore\RestApi\Controllers\V1
 */
class HC_REST_Manage_Suppliers_V1_Controller extends HC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'hc/v1/suppliers';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'manage';

	/**
	 * Registers the REST routes for supplier management.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}",
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_suppliers' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'save_suppliers' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'add_supplier' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods' 			  => 'DELETE',
					'callback'            => [ $this, 'delete_supplier' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				]
			]
		);
	}

	/**
	 * Checks permissions for all endpoints.
	 *
	 * @return bool True if user has 'manage_options'.
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retrieves all suppliers and returns them in a simplified format.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_suppliers( WP_REST_Request $request ) {
		$service = new HC_Supplier_Service();
		$suppliers = $service->all();

		$formatted = array_map(function($s) {
			$addresses = $s->get_address();
			$country = is_array($addresses) && isset($addresses[0]['country']) ? $addresses[0]['country'] : '';

			return [
				'id'           => $s->get_id(),
				'is_active'    => $s->is_active(),
				'name'         => $s->get_name(),
				'email'        => $s->get_email(),
				'paypal_email' => $s->get_paypal_email(),
				'phone'        => $s->get_phone(),
				'country'      => $country,
				'language'     => $s->get_locale(),
				'socials'      => $s->get_socials(),
				'attributes'   => $s->get_attributes(),
			];
		}, $suppliers);

		return new WP_REST_Response($formatted);
	}

	/**
	 * Saves inline field edits for one or more suppliers.
	 *
	 * @param WP_REST_Request $request The request with supplier field data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_suppliers( WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		$fieldMap = [
			'name'         => 'name',
			'email'        => 'email',
			'paypal_email' => 'paypal_email',
			'phone'        => 'phone',
			'country'      => 'addresses.0.country',
			'language'     => 'locale',
			'socials'      => 'socials'
		];

		$supplierService = new HC_Supplier_Service();

		foreach ($payload as $fieldKey => $values) {
			if (!isset($fieldMap[$fieldKey]) || !is_array($values)) continue;

			foreach ($values as $id => $value) {
				$id = absint($id);
				if (!$id || $value === '') continue;

				$supplier = $supplierService->get($id);
				if (!$supplier || UserUtils::is_protected_user($supplier->get_email())) continue;

				$updateData = [];
				$mappedKey = $fieldMap[$fieldKey];

				if ($fieldKey === 'socials') {
					$decoded = is_string($value) ? json_decode(stripslashes($value), true) : $value;
					if (is_array($decoded)) {
						$updateData['socials'] = array_map(function ($entry) {
							return [
								'type' => sanitize_text_field($entry['type'] ?? ''),
								'url'  => esc_url_raw($entry['url'] ?? '')
							];
						}, $decoded);
					}
				} elseif ($fieldKey === 'country') {
					$existing = $supplier->get_address();
					$first = $existing[0] ?? [];
					$first['country'] = sanitize_text_field($value);
					$updateData['addresses'] = [$first];
				} else {
					$updateData[$mappedKey] = in_array($fieldKey, ['email', 'paypal_email'])
						? sanitize_email($value)
						: sanitize_text_field($value);
				}

				$supplierService->update($id, $updateData);
			}
		}

		return new WP_REST_Response(['message' => 'Suppliers updated.'], 200);
	}

	/**
	 * Adds a new supplier with basic validation.
	 *
	 * @param WP_REST_Request $request The request with supplier data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_supplier( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$name         = sanitize_text_field($data['name'] ?? '');
		$email        = sanitize_email($data['email'] ?? '');
		$paypal_email = sanitize_email($data['paypal_email'] ?? '');
		$phone        = sanitize_text_field($data['phone'] ?? '');
		$country      = sanitize_text_field($data['country'] ?? '');
		$language     = sanitize_text_field($data['language'] ?? '');
		$socialsRaw   = $data['socials'] ?? [];

		if (UserUtils::is_protected_user($email)) {
			return new WP_Error('forbidden', 'This email belongs to a protected admin account.', ['status' => 403]);
		}

		if (!$name || !$email) {
			return new WP_Error('invalid_fields', 'Name and Email are required.', ['status' => 400]);
		}

		if (email_exists($email)) {
			return new WP_Error('email_exists', 'Email already exists.', ['status' => 400]);
		}

		$sanitizedSocials = array_filter(array_map(function($entry) {
			$type = sanitize_text_field($entry['type'] ?? '');
			$url  = esc_url_raw($entry['url'] ?? '');
			return $type && $url ? ['type' => $type, 'url' => $url] : null;
		}, $socialsRaw));

		$supplierService = new HC_Supplier_Service();
		$supplier = $supplierService->create([
			'name'         => $name,
			'email'        => $email,
			'phone'        => $phone,
			'locale'       => $language,
			'paypal_email' => $paypal_email,
			'addresses'    => [[ 'country' => $country ]],
			'socials'      => $sanitizedSocials,
			'is_active'    => true
		]);

		if (! $supplier) {
			return new WP_Error('create_failed', 'Failed to create supplier.', ['status' => 500]);
		}

		$settings = new HC_Settings();
		if (Notifications::supplierWelcomeEmailEnabled()) {
			HC_Supplier_Service::scheduleWelcomeEmail($email, $name, $supplier->get_id(), $supplier->get_password());
		}

		return new WP_REST_Response(['id' => $supplier->get_id()], 200);
	}

	/**
	 * Deletes a supplier with safety checks handled by service layer.
	 *
	 * @param WP_REST_Request $request The request containing supplier_id.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_supplier( WP_REST_Request $request ) {
		$id = absint($request->get_param('supplier_id'));
		$force_delete = rest_sanitize_boolean( $request->get_param( 'force_delete' ) );
		$reassign = absint( $request->get_param( 'reassign' ) );

		if (! $id) {
			return new WP_Error('invalid_id', 'Invalid supplier ID.', ['status' => 400]);
		}

		$service = new HC_Supplier_Service();

		$result = $service->delete_with_safeguards(
			$id,
			array(
				'force'    => (bool) $force_delete,
				'reassign' => $reassign ?: null,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'message' => 'Supplier deleted.',
				'result'  => $result,
			),
			200
		);
	}
}
