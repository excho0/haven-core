<?php
/**
 * Registers HavenCore V1 messaging abilities for MCP adapter.
 *
 * @package HavenCore\Mcp\Abilities\V1\Messaging
 */

namespace HavenCore\Mcp\Abilities\V1\Messaging;

use HavenCore\Mcp\Contracts\AbilitiesV1RegistrarContract;
use HavenCore\Services\HC_Messaging_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Messaging ability registration and execution callbacks (V1).
 */
class MessagingAbilitiesV1Registrar implements AbilitiesV1RegistrarContract {
	/**
	 * Ability namespace.
	 */
	private const ABILITY_NAMESPACE = 'havencore';

	/**
	 * Ability category slug.
	 */
	private const CATEGORY_SLUG = 'havencore-messaging';

	/**
	 * Register messaging ability categories.
	 *
	 * @return void
	 */
	public static function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_is_ability_category_registered' ) && wp_is_ability_category_registered( self::CATEGORY_SLUG ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => 'HavenCore Messaging',
				'description' => 'Messaging and conversation abilities exposed by HavenCore.',
			)
		);
	}

	/**
	 * Register messaging abilities.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			self::ability_id( 'conversations-list' ),
			'List conversations with optional filters and pagination.',
			array(
				'type'       => 'object',
				'properties' => array(
					'status'      => array( 'type' => 'string' ),
					'search'      => array( 'type' => 'string' ),
					'supplier_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'order_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page'    => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
					'page'        => array( 'type' => 'integer', 'minimum' => 1 ),
					'order'       => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_conversations_list' )
		);

		self::register_ability(
			self::ability_id( 'conversation-create' ),
			'Create a conversation, optionally with an initial message.',
			array(
				'type'       => 'object',
				'properties' => array(
					'supplier_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
					'order_id'         => array( 'type' => 'integer', 'minimum' => 1 ),
					'subject'          => array( 'type' => 'string' ),
					'status'           => array( 'type' => 'string' ),
					'message'          => array( 'type' => 'string' ),
					'initial_message'  => array( 'type' => 'string' ),
					'meta'             => array( 'type' => 'object' ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_conversation_create' )
		);

		self::register_ability(
			self::ability_id( 'conversation-messages-list' ),
			'List messages for a conversation.',
			array(
				'type'       => 'object',
				'required'   => array( 'conversation_id' ),
				'properties' => array(
					'conversation_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page'        => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
					'page'            => array( 'type' => 'integer', 'minimum' => 1 ),
					'order'           => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_conversation_messages_list' )
		);

		self::register_ability(
			self::ability_id( 'conversation-message-create' ),
			'Post a message to a conversation.',
			array(
				'type'       => 'object',
				'required'   => array( 'conversation_id', 'message' ),
				'properties' => array(
					'conversation_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'message'         => array( 'type' => 'string' ),
					'attachments'     => array( 'type' => 'array' ),
					'files'           => array( 'type' => 'array' ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_conversation_message_create' )
		);

		self::register_ability(
			self::ability_id( 'conversation-mark-read' ),
			'Mark a conversation as read for the current audience.',
			array(
				'type'       => 'object',
				'required'   => array( 'conversation_id' ),
				'properties' => array(
					'conversation_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_conversation_mark_read' )
		);

		self::register_ability(
			self::ability_id( 'unread-summary-get' ),
			'Get unread summary for admin or current supplier.',
			array( 'type' => 'object' ),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check' ),
			array( self::class, 'execute_unread_summary_get' )
		);

		self::register_ability(
			self::ability_id( 'conversation-delete' ),
			'Delete a conversation (admin only).',
			array(
				'type'       => 'object',
				'required'   => array( 'conversation_id' ),
				'properties' => array(
					'conversation_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
			),
			array( 'type' => 'object' ),
			array( self::class, 'permissions_check_admin' ),
			array( self::class, 'execute_conversation_delete' )
		);
	}

	/**
	 * Return ability IDs exposed by this registrar.
	 *
	 * @return string[]
	 */
	public static function get_ability_ids(): array {
		return array(
			self::ability_id( 'conversations-list' ),
			self::ability_id( 'conversation-create' ),
			self::ability_id( 'conversation-messages-list' ),
			self::ability_id( 'conversation-message-create' ),
			self::ability_id( 'conversation-mark-read' ),
			self::ability_id( 'unread-summary-get' ),
			self::ability_id( 'conversation-delete' ),
		);
	}

	/**
	 * Default permission check for messaging abilities.
	 *
	 * @return bool
	 */
	public static function permissions_check(): bool {
		return self::is_supplier_or_admin_user();
	}

	/**
	 * Admin-only permission check.
	 *
	 * @return bool
	 */
	public static function permissions_check_admin(): bool {
		return self::is_admin_user();
	}

	/**
	 * List conversations ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversations_list( array $args ) {
		$supplier_id = self::resolve_supplier_id( $args, true );
		if ( is_wp_error( $supplier_id ) ) {
			return $supplier_id;
		}

		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$page = max( 1, absint( $args['page'] ?? 1 ) );
		$order = strtoupper( sanitize_text_field( (string) ( $args['order'] ?? 'DESC' ) ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$query = array(
			'status'   => sanitize_text_field( (string) ( $args['status'] ?? '' ) ),
			'search'   => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
			'order_id' => absint( $args['order_id'] ?? 0 ),
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'order'    => $order,
		);

		if ( $supplier_id ) {
			$query['supplier_id'] = $supplier_id;
		}

		$items = self::service()->fetchConversations( $query );

		return array(
			'items' => $items,
			'meta'  => array(
				'count'    => count( $items ),
				'page'     => $page,
				'per_page' => $per_page,
			),
		);
	}

	/**
	 * Create conversation ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversation_create( array $args ) {
		$supplier_id = self::resolve_supplier_id( $args, true );
		if ( is_wp_error( $supplier_id ) ) {
			return $supplier_id;
		}
		if ( ! $supplier_id ) {
			return new WP_Error( 'invalid_supplier', 'A supplier_id is required.' );
		}

		$subject = sanitize_text_field( (string) ( $args['subject'] ?? 'General Conversation' ) );
		$status = sanitize_text_field( (string) ( $args['status'] ?? 'open' ) );
		$order_id = absint( $args['order_id'] ?? 0 );
		$initial_message = trim( (string) ( $args['message'] ?? $args['initial_message'] ?? '' ) );

		$payload = array(
			'supplier_id'   => $supplier_id,
			'admin_user_id' => self::is_admin_user() ? get_current_user_id() : null,
			'order_id'      => $order_id ?: null,
			'subject'       => '' !== $subject ? $subject : 'General Conversation',
			'status'        => '' !== $status ? $status : 'open',
			'meta'          => isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : null,
		);

		$conversation = self::service()->createConversation( $payload );
		if ( ! $conversation ) {
			return new WP_Error( 'unable_to_create_conversation', 'Unable to create conversation.' );
		}

		if ( '' !== $initial_message ) {
			$message = self::service()->createMessage(
				(int) $conversation['id'],
				array(
					'sender_type' => self::is_admin_user() ? 'admin' : 'supplier',
					'sender_id'   => get_current_user_id(),
					'message'     => $initial_message,
				)
			);

			if ( ! $message ) {
				self::service()->deleteConversation( (int) $conversation['id'] );
				return new WP_Error( 'unable_to_create_message', 'Unable to create initial message.' );
			}

			$conversation = self::service()->getConversation( (int) $conversation['id'] );
		}

		return $conversation;
	}

	/**
	 * List conversation messages ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversation_messages_list( array $args ) {
		$conversation_id = absint( $args['conversation_id'] ?? 0 );
		if ( ! $conversation_id ) {
			return new WP_Error( 'invalid_conversation', 'conversation_id is required.' );
		}

		$conversation = self::service()->getConversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.' );
		}
		if ( ! self::ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot access this conversation.' );
		}

		$per_page = max( 1, min( 200, absint( $args['per_page'] ?? 50 ) ) );
		$page = max( 1, absint( $args['page'] ?? 1 ) );
		$order = strtoupper( sanitize_text_field( (string) ( $args['order'] ?? 'ASC' ) ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$messages = self::service()->fetchMessages(
			$conversation_id,
			array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
				'order'  => $order,
			)
		);

		return array(
			'messages' => $messages,
			'count'    => count( $messages ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Create conversation message ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversation_message_create( array $args ) {
		$conversation_id = absint( $args['conversation_id'] ?? 0 );
		if ( ! $conversation_id ) {
			return new WP_Error( 'invalid_conversation', 'conversation_id is required.' );
		}

		$conversation = self::service()->getConversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.' );
		}
		if ( ! self::ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot post to this conversation.' );
		}

		$message = trim( (string) ( $args['message'] ?? '' ) );
		if ( '' === $message ) {
			return new WP_Error( 'invalid_message', 'Message body is required.' );
		}

		$payload = array(
			'sender_type' => self::is_admin_user() ? 'admin' : 'supplier',
			'sender_id'   => get_current_user_id(),
			'message'     => $message,
			'attachments' => $args['attachments'] ?? null,
			'files'       => is_array( $args['files'] ?? null ) ? $args['files'] : null,
		);

		$message_entry = self::service()->createMessage( $conversation_id, $payload );
		if ( ! $message_entry ) {
			return new WP_Error( 'unable_to_create_message', 'Unable to create message.' );
		}

		return $message_entry;
	}

	/**
	 * Mark conversation read ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversation_mark_read( array $args ) {
		$conversation_id = absint( $args['conversation_id'] ?? 0 );
		if ( ! $conversation_id ) {
			return new WP_Error( 'invalid_conversation', 'conversation_id is required.' );
		}

		$conversation = self::service()->getConversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.' );
		}
		if ( ! self::ensure_can_access_conversation( $conversation ) ) {
			return new WP_Error( 'forbidden', 'You cannot update this conversation.' );
		}

		$audience = self::is_admin_user() ? 'admin' : 'supplier';
		self::service()->markThreadAsRead( $conversation_id, $audience );

		return array( 'status' => 'ok' );
	}

	/**
	 * Get unread summary ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_unread_summary_get( array $args ) {
		unset( $args );

		if ( self::is_supplier_user() ) {
			$supplier_id = (int) get_current_user_id();
			$count = self::service()->getUnreadCountForSupplier( $supplier_id );
			return array(
				'audience'     => 'supplier',
				'supplier_id'  => $supplier_id,
				'unread_count' => $count,
				'has_unread'   => $count > 0,
			);
		}

		if ( self::is_admin_user() ) {
			$count = self::service()->getUnreadCountForAdmin();
			return array(
				'audience'     => 'admin',
				'unread_count' => $count,
				'has_unread'   => $count > 0,
			);
		}

		return new WP_Error( 'forbidden', 'You cannot access this resource.' );
	}

	/**
	 * Delete conversation ability callback.
	 *
	 * @param array $args Ability args.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function execute_conversation_delete( array $args ) {
		$conversation_id = absint( $args['conversation_id'] ?? 0 );
		if ( ! $conversation_id ) {
			return new WP_Error( 'invalid_conversation', 'conversation_id is required.' );
		}

		$conversation = self::service()->getConversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', 'Conversation not found.' );
		}

		$deleted = self::service()->deleteConversation( $conversation_id );
		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', 'Unable to delete conversation.' );
		}

		return array(
			'deleted'         => true,
			'conversation_id' => $conversation_id,
		);
	}

	/**
	 * Build a compliant ability ID.
	 *
	 * @param string $ability_name Ability slug.
	 * @return string
	 */
	private static function ability_id( string $ability_name ): string {
		$ability_name = strtolower( preg_replace( '/[^a-z0-9-]+/', '-', $ability_name ) );
		return self::ABILITY_NAMESPACE . '/' . trim( $ability_name, '-' );
	}

	/**
	 * Register one ability with shared defaults.
	 *
	 * @param string   $name Ability ID.
	 * @param string   $description Human description.
	 * @param array    $input_schema Input schema.
	 * @param array    $output_schema Output schema.
	 * @param callable $permission_callback Permission callback.
	 * @param callable $execute_callback Execute callback.
	 * @return void
	 */
	private static function register_ability( string $name, string $description, array $input_schema, array $output_schema, callable $permission_callback, callable $execute_callback ): void {
		wp_register_ability(
			$name,
			array(
				'label'               => $description,
				'category'            => self::CATEGORY_SLUG,
				'description'         => $description,
				'input_schema'        => $input_schema,
				'output_schema'       => $output_schema,
				'permission_callback' => $permission_callback,
				'execute_callback'    => $execute_callback,
				'meta'                => array(
					'mcp' => array(
						'public' => false,
					),
				),
			)
		);
	}

	/**
	 * Return messaging service instance.
	 *
	 * @return HC_Messaging_Service
	 */
	private static function service(): HC_Messaging_Service {
		return new HC_Messaging_Service();
	}

	/**
	 * Check whether current user is admin.
	 *
	 * @return bool
	 */
	private static function is_admin_user(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check whether current user has supplier role.
	 *
	 * @return bool
	 */
	private static function is_supplier_user(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( function_exists( 'wc_current_user_has_role' ) ) {
			return wc_current_user_has_role( 'supplier' );
		}

		$user = wp_get_current_user();
		return in_array( 'supplier', (array) $user->roles, true );
	}

	/**
	 * Check whether current user is supplier or admin.
	 *
	 * @return bool
	 */
	private static function is_supplier_or_admin_user(): bool {
		return is_user_logged_in() && ( self::is_supplier_user() || self::is_admin_user() );
	}

	/**
	 * Resolve supplier ID based on current audience.
	 *
	 * @param array $args Ability args.
	 * @param bool  $allow_admin_override Allow supplier_id arg for admins.
	 * @return int|WP_Error
	 */
	private static function resolve_supplier_id( array $args, bool $allow_admin_override ) {
		if ( self::is_supplier_user() ) {
			return (int) get_current_user_id();
		}

		if ( $allow_admin_override && self::is_admin_user() ) {
			$param = absint( $args['supplier_id'] ?? 0 );
			return $param ?: 0;
		}

		if ( self::is_admin_user() ) {
			return 0;
		}

		return new WP_Error( 'forbidden', 'You cannot access this resource.' );
	}

	/**
	 * Validate conversation access by role.
	 *
	 * @param array<string,mixed> $conversation Conversation payload.
	 * @return bool
	 */
	private static function ensure_can_access_conversation( array $conversation ): bool {
		if ( self::is_admin_user() ) {
			return true;
		}

		if ( ! self::is_supplier_user() ) {
			return false;
		}

		return (int) ( $conversation['supplier_id'] ?? 0 ) === (int) get_current_user_id();
	}
}
