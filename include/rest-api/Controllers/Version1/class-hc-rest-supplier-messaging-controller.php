<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use HavenCore\Services\HC_Messaging_Service;
use HavenCore\Services\HC_Supplier_Service;

defined('ABSPATH') || exit;

/**
 * Supplier messaging controller.
 */
class HC_REST_Supplier_Messaging_V1_Controller extends HC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'hc/v1/communications';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'messaging';

	/**
	 * Cached messaging service.
	 *
	 * @var HC_Messaging_Service|null
	 */
	private $messaging_service = null;

	/**
	 * Cached supplier service.
	 *
	 * @var HC_Supplier_Service|null
	 */
	private $supplier_service = null;

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_conversations' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'status'      => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'search'      => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'supplier_id' => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
					'order_id'    => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
					'per_page'    => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
					'page'        => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_conversation' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<conversation_id>\d+)/messages',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_conversation_messages' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'per_page' => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
					'page'     => [ 'validate_callback' => [ $this, 'validate_numeric' ] ],
					'order'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<conversation_id>\d+)/messages',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_conversation_message' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<conversation_id>\d+)/mark-read',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'mark_conversation_read' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unread-summary',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_unread_summary' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<conversation_id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_conversation' ],
				'permission_callback' => function () {
					return $this->is_admin_user();
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/suppliers',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_suppliers' ],
				'permission_callback' => function () {
					return $this->is_admin_user();
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/suppliers/(?P<supplier_id>\d+)/orders',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_supplier_orders' ],
				'permission_callback' => function () {
					return $this->current_user_is_supplier_or_admin();
				},
			]
		);
	}

	public function get_conversations( WP_REST_Request $request ) {
		$supplier_id = $this->resolve_supplier_id( $request, true );
		$per_page    = $request->get_param( 'per_page' ) ? max( 1, (int) $request->get_param( 'per_page' ) ) : 20;
		$page        = $request->get_param( 'page' ) ? max( 1, (int) $request->get_param( 'page' ) ) : 1;

		$args = [
			'status'   => $request->get_param( 'status' ),
			'search'   => $request->get_param( 'search' ),
			'order_id' => $request->get_param( 'order_id' ),
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		];

		if ( $supplier_id ) {
			$args['supplier_id'] = $supplier_id;
		}

		$conversations = $this->messaging_service()->fetchConversations( $args );

		if ( $this->is_admin_user() && ! empty( $conversations ) ) {
			$conversations = $this->append_order_data_to_conversations( $conversations );
		}

		return new WP_REST_Response(
			[
				'conversations' => $conversations,
				'count'         => count( $conversations ),
				'page'          => $page,
				'per_page'      => $per_page,
			]
		);
	}

	public function create_conversation( WP_REST_Request $request ) {
		$supplier_id = $this->resolve_supplier_id( $request, true );
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier', 'A supplier_id is required.', [ 'status' => 400 ] );
		}

		$initial_message = $request->get_param( 'message' );
		if ( empty( $initial_message ) ) {
			$initial_message = $request->get_param( 'initial_message' );
		}
		if ( is_string( $initial_message ) ) {
			$initial_message = trim( wp_unslash( $initial_message ) );
		} else {
			$initial_message = '';
		}

		$payload = [
			'supplier_id'   => $supplier_id,
			'admin_user_id' => $this->is_admin_user() ? get_current_user_id() : null,
			'order_id'      => $request->get_param( 'order_id' ) ? absint( $request->get_param( 'order_id' ) ) : null,
			'subject'       => $request->get_param( 'subject' ) ?: 'General Conversation',
			'status'        => $request->get_param( 'status' ) ?: 'open',
			'meta'          => $request->get_param( 'meta' ),
		];

		$conversation = $this->messaging_service()->createConversation( $payload );

		if ( ! $conversation ) {
			return new WP_Error( 'unable_to_create_conversation', 'Unable to create conversation.', [ 'status' => 500 ] );
		}

		if ( $initial_message ) {
			$message_payload = [
				'sender_type' => $this->is_admin_user() ? 'admin' : 'supplier',
				'sender_id'   => get_current_user_id(),
				'message'     => $initial_message,
			];

			$message = $this->messaging_service()->createMessage( (int) $conversation['id'], $message_payload );

			if ( ! $message ) {
				$this->messaging_service()->deleteConversation( (int) $conversation['id'] );

				return new WP_Error( 'unable_to_create_message', 'Unable to create initial message.', [ 'status' => 500 ] );
			}

			$conversation = $this->messaging_service()->getConversation( (int) $conversation['id'] );
		}

		return new WP_REST_Response( $conversation, 201 );
	}

	public function get_conversation_messages( WP_REST_Request $request ) {
		$conversation_id = (int) $request['conversation_id'];
		$conversation    = $this->messaging_service()->getConversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
		}

		if ( ! $this->ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot access this conversation.', [ 'status' => 403 ] );
		}

		$per_page = $request->get_param( 'per_page' ) ? max( 1, (int) $request->get_param( 'per_page' ) ) : 50;
		$page     = $request->get_param( 'page' ) ? max( 1, (int) $request->get_param( 'page' ) ) : 1;
		$order    = $request->get_param( 'order' ) ?: 'ASC';

		$messages = $this->messaging_service()->fetchMessages(
			$conversation_id,
			[
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
				'order'  => $order,
			]
		);

		$order = null;
		if ( $this->is_admin_user() && ! empty( $conversation['order_id'] ) ) {
			$order = $this->prepare_order_payload( (int) $conversation['order_id'] );
		}

		return new WP_REST_Response(
			[
				'messages' => $messages,
				'count'    => count( $messages ),
				'page'     => $page,
				'per_page' => $per_page,
				'order'    => $order,
			]
		);
	}

	public function create_conversation_message( WP_REST_Request $request ) {
		$conversation_id = (int) $request['conversation_id'];
		$conversation    = $this->messaging_service()->getConversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
		}

		if ( ! $this->ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot post to this conversation.', [ 'status' => 403 ] );
		}

		$message = $request->get_param( 'message' );
		if ( empty( $message ) ) {
			return new WP_Error( 'invalid_message', 'Message body is required.', [ 'status' => 400 ] );
		}

		$payload = [
			'sender_type' => $this->is_admin_user() ? 'admin' : 'supplier',
			'sender_id'   => get_current_user_id(),
			'message'     => $message,
			'attachments' => $request->get_param( 'attachments' ),
			'files'       => $request->get_param( 'files' ),
		];

		$message_entry = $this->messaging_service()->createMessage( $conversation_id, $payload );

		if ( ! $message_entry ) {
			return new WP_Error( 'unable_to_create_message', 'Unable to create message.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $message_entry, 201 );
	}

	public function mark_conversation_read( WP_REST_Request $request ) {
		$conversation_id = (int) $request['conversation_id'];
		$conversation    = $this->messaging_service()->getConversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
		}

		if ( ! $this->ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot update this conversation.', [ 'status' => 403 ] );
		}

		$audience = $this->is_admin_user() ? 'admin' : 'supplier';
		$this->messaging_service()->markThreadAsRead( $conversation_id, $audience );

		return new WP_REST_Response( [ 'status' => 'ok' ] );
	}

	public function permissions_check( $request ) {
		return $this->current_user_is_supplier_or_admin();
	}

	public function validate_numeric( $value, $request, $param ) {
		return is_numeric( $value );
	}

	protected function current_user_is_supplier_or_admin(): bool {
		return is_user_logged_in() && ( wc_current_user_has_role( 'supplier' ) || wc_current_user_has_role( 'administrator' ) );
	}

	private function resolve_supplier_id( WP_REST_Request $request, bool $allow_admin_override = false ): ?int {
		$current = wp_get_current_user();
		if ( wc_current_user_has_role( 'supplier' ) ) {
			return (int) $current->ID;
		}

		if ( $allow_admin_override && $this->is_admin_user() ) {
			$param = absint( $request->get_param( 'supplier_id' ) );
			return $param ?: null;
		}

		return null;
	}

	private function ensure_can_access_conversation( array $conversation ): bool {
		if ( $this->is_admin_user() ) {
			return true;
		}

		$current = wp_get_current_user();
		return (int) $conversation['supplier_id'] === (int) $current->ID;
	}

	/**
	 * Append WooCommerce order data to each conversation when available.
	 *
	 * @param array<int, array> $conversations Conversations list.
	 *
	 * @return array<int, array>
	 */
	private function append_order_data_to_conversations( array $conversations ): array {
		$order_cache = [];

		foreach ( $conversations as &$conversation ) {
			$order_id = isset( $conversation['order_id'] ) ? (int) $conversation['order_id'] : 0;
			if ( $order_id <= 0 ) {
				$conversation['order'] = null;
				continue;
			}

			if ( ! array_key_exists( $order_id, $order_cache ) ) {
				$order_cache[ $order_id ] = $this->prepare_order_payload( $order_id );
			}

			$conversation['order'] = $order_cache[ $order_id ];
		}

		unset( $conversation );

		return $conversations;
	}

	/**
	 * Prepare an order payload for API responses.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function prepare_order_payload( int $order_id ): ?array {
		if ( $order_id <= 0 ) {
			return null;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$edit_url = $order->get_edit_order_url();

		return [
			'id'               => $order->get_id(),
			'number'           => $order->get_order_number(),
			'status'           => $order->get_status(),
			'total'            => $order->get_total(),
			'currency'         => $order->get_currency(),
			'formatted_total'  => $order->get_formatted_order_total(),
			'date_created'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( DATE_ATOM ) : null,
			'customer_id'      => $order->get_customer_id(),
			'payment_method'   => $order->get_payment_method(),
			'shipping_method'  => $order->get_shipping_method(),
			'edit_url'         => $edit_url ?: admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
		];
	}

	private function messaging_service(): HC_Messaging_Service {
		if ( ! $this->messaging_service ) {
			$this->messaging_service = new HC_Messaging_Service();
		}

		return $this->messaging_service;
	}

	private function supplier_service(): HC_Supplier_Service {
		if ( ! $this->supplier_service ) {
			$this->supplier_service = new HC_Supplier_Service();
		}

		return $this->supplier_service;
	}

	private function is_admin_user(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	public function get_unread_summary( WP_REST_Request $request ) {
		$supplier_id = $this->resolve_supplier_id( $request, false );

		if ( $supplier_id ) {
			$count = $this->messaging_service()->getUnreadCountForSupplier( $supplier_id );

			return new WP_REST_Response(
				[
					'audience'     => 'supplier',
					'supplier_id'  => $supplier_id,
					'unread_count' => $count,
					'has_unread'   => $count > 0,
				]
			);
		}

		if ( $this->is_admin_user() ) {
			$count = $this->messaging_service()->getUnreadCountForAdmin();

			return new WP_REST_Response(
				[
					'audience'     => 'admin',
					'unread_count' => $count,
					'has_unread'   => $count > 0,
				]
			);
		}

		return new WP_Error( 'forbidden', 'You cannot access this resource.', [ 'status' => 403 ] );
	}

	public function delete_conversation( WP_REST_Request $request ) {
		$conversation_id = (int) $request['conversation_id'];
		$conversation    = $this->messaging_service()->getConversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
		}

		$this->messaging_service()->deleteConversation( $conversation_id );

		return new WP_REST_Response( null, 204 );
	}

	public function get_suppliers( WP_REST_Request $request ) {
		$users = get_users(
			[
				'role'   => 'supplier',
				'number' => 200,
				'fields' => [ 'ID', 'display_name', 'user_email' ],
			]
		);

		$data = array_map(
			function ( $user ) {
				return [
					'id'    => (int) $user->ID,
					'name'  => $user->display_name ?: $user->user_email ?: 'Supplier #' . $user->ID,
					'email' => $user->user_email,
				];
			},
			$users
		);

		return new WP_REST_Response( [ 'suppliers' => $data ] );
	}

	public function get_supplier_orders( WP_REST_Request $request ) {
		$supplier_id = absint( $request['supplier_id'] );
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier', 'Supplier ID is required.', [ 'status' => 400 ] );
		}

		if ( ! $this->is_admin_user() ) {
			$current = wp_get_current_user();
			if ( (int) $current->ID !== $supplier_id ) {
				return new WP_Error( 'forbidden', 'You cannot access these orders.', [ 'status' => 403 ] );
			}
		}

		$supplier = $this->supplier_service()->get( $supplier_id );
		if ( ! $supplier ) {
			return new WP_Error( 'not_found', 'Supplier not found.', [ 'status' => 404 ] );
		}

		$order_ids = $supplier->get_assigned_orders() ?? [];
		$orders    = [];

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$orders[] = [
				'id'           => (int) $order->get_id(),
				'number'       => $order->get_order_number(),
				'status'       => $order->get_status(),
				'created_at'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
				'customer'     => trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email(),
				'total'        => $order->get_total(),
				'currency'     => $order->get_currency(),
			];
		}

		return new WP_REST_Response( [ 'orders' => $orders ] );
	}
}
