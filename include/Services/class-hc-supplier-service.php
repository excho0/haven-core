<?php

namespace HavenCore\Services;

use HavenCore\Classes\HC_Supplier;
use HavenCore\Classes\HC_Data_Store;
use HavenCore\Utils\UserUtils;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * HC Supplier Service.
 *
 * Central service for managing suppliers.
 *
 * @package HavenCore\Services
 */
class HC_Supplier_Service {

    /**
     * Get all suppliers.
     *
     * @return HC_Supplier[] Array of supplier objects.
     */
    public function all(): array {
        /** @var \HavenCore\DataStores\HC_Supplier_Data_Store $store */
        $store = HC_Data_Store::load('supplier');
        return $store->read_all();
    }

    /**
     * Get a single supplier by ID.
     *
     * @param int $supplier_id Supplier user ID.
     * @return HC_Supplier|null
     */
    public function get( int $supplier_id ): ?HC_Supplier {
        try {
            return new HC_Supplier( $supplier_id );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Get a supplier by email.
     *
     * @param string $email
     * @return HC_Supplier|null
     */
    public function get_by_email( string $email ): ?HC_Supplier {
        $user = get_user_by('email', $email);

        if (! $user || ! in_array('supplier', (array) $user->roles, true)) {
            return null;
        }

        try {
            return new HC_Supplier($user->ID);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new supplier.
     *
     * @param array $data Associative array of supplier fields.
     * @return HC_Supplier|null
     */
    public function create( array $data ): ?HC_Supplier {
        try {
            if ( ! empty( $data['email'] ) && UserUtils::is_protected_user( $data['email'] ) ) {
                error_log("❌ Blocked supplier creation using protected admin email: {$data['email']}");
                return null;
            }

            $supplier = new HC_Supplier();
            $supplier->set_name( $data['name'] ?? '' );
            $supplier->set_email( $data['email'] ?? '' );
            $supplier->set_phone( $data['phone'] ?? '' );
            $supplier->set_locale( $data['locale'] ?? '' );
            $supplier->set_paypal_email( $data['paypal_email'] ?? '' );
            $supplier->set_addresses( $data['addresses'] ?? [] );
            $supplier->set_socials( $data['socials'] ?? [] );
            $supplier->set_is_active( $data['is_active'] ?? true );

            // Handle provided attributes if provided
            if ( isset( $data['attributes'] ) ) {
                $supplier->set_attributes( $data['attributes'] );
            }

            // Handle assigned orders if provided
            if ( isset( $data['assigned_orders'] ) ) {
                $supplier->set_assigned_orders( $data['assigned_orders'] );
            }

            $supplier->save();
            return $supplier;
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Update a supplier.
     *
     * @param int   $supplier_id
     * @param array $data
     * @return bool Success status.
     */
    public function update( int $supplier_id, array $data ): bool {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) return false;

        if ( isset( $data['email'] ) && UserUtils::is_protected_user( $data['email'] ) ) {
            error_log("❌ Blocked supplier update with protected admin email: {$data['email']}");
            return false;
        }
        
        if ( isset( $data['is_active'] ) ) {
            $supplier->set_is_active( (bool) $data['is_active'] );
        }
        if ( isset( $data['name'] ) ) {
            $supplier->set_name( $data['name'] );
        }
        if ( isset( $data['email'] ) ) {
            $supplier->set_email( $data['email'] );
        }
        if ( isset( $data['phone'] ) ) {
            $supplier->set_phone( $data['phone'] );
        }
        if ( isset( $data['locale'] ) ) {
            $supplier->set_locale( $data['locale'] );
        }
        if ( isset( $data['paypal_email'] ) ) {
            $supplier->set_paypal_email( $data['paypal_email'] );
        }
        if ( isset( $data['addresses'] ) ) {
            $supplier->set_addresses( $data['addresses'] );
        }
        if ( isset( $data['socials'] ) ) {
            $supplier->set_socials( $data['socials'] );
        }
        if ( isset( $data['assigned_orders'] ) ) {
            $supplier->set_assigned_orders( $data['assigned_orders'] );
        }
        if ( isset( $data['attributes'] ) ) {
            $supplier->set_attributes( $data['attributes'] );
        }


        $supplier->save();
        return true;
    }

    /**
     * Deactivate or activate a supplier.
     *
     * @param int  $supplier_id
     * @param bool $status
     * @return bool
     */
    public function set_active( int $supplier_id, bool $status ): bool {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) return false;

        $supplier->set_is_active( $status );
        $supplier->save();
        return true;
    }

    /**
     * Assign orders to a supplier.
     *
     * @param int   $supplier_id Supplier user ID.
     * @param array $order_ids   List of order IDs to assign.
     * @param bool  $overwrite   Whether to replace existing orders (true) or append to them (false).
     * @return bool Success status.
     */
    public function assign_orders( int $supplier_id, array $order_ids, bool $overwrite = false ): bool {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) return false;

        $order_ids = array_map( 'absint', $order_ids );  // Ensure all order IDs are integers

        // Use the set_assigned_orders method, which already handles merging/overwriting
        $supplier->set_assigned_orders( $order_ids, $overwrite );

        $supplier->save();
        return true;
    }

    /**
     * Remove a single order assignment from a supplier.
     *
     * @param int $supplier_id
     * @param int $order_id
     * @return bool
     */
    public function unassign_order( int $supplier_id, int $order_id ): bool {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) {
            return false;
        }

        $supplier->remove_assigned_order( $order_id );
        $supplier->save();

        return true;
    }

    /**
     * Delete a supplier with safety checks.
     *
     * @param int      $supplier_id Supplier ID.
     * @param int|null $reassign Optional reassignment user ID.
     * @param bool     $force Whether to bypass unfinished-order safeguards.
     * @return bool
     */
    public function delete( int $supplier_id, ?int $reassign = null, bool $force = false ): bool {
        $result = $this->delete_with_safeguards(
            $supplier_id,
            array(
                'reassign' => $reassign,
                'force'    => $force,
            )
        );

        return ! is_wp_error( $result ) && ! empty( $result['deleted'] );
    }

    /**
     * Delete a supplier and return structured outcome or explicit error.
     *
     * @param int   $supplier_id Supplier ID.
     * @param array $args Deletion args.
     * @return array<string,mixed>|WP_Error
     */
    public function delete_with_safeguards( int $supplier_id, array $args = array() ) {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) {
            return new WP_Error( 'supplier_not_found', 'Supplier not found.', array( 'status' => 404 ) );
        }

        $args = wp_parse_args(
            $args,
            array(
                'reassign' => null,
                'force'    => false,
            )
        );

        $force = ! empty( $args['force'] );
        $reassign = isset( $args['reassign'] ) && is_numeric( $args['reassign'] ) ? absint( $args['reassign'] ) : null;

        if ( ! $force ) {
            $pending_fulfillment_order_ids = $this->get_unfinished_order_ids_for_supplier( $supplier );
            if ( ! empty( $pending_fulfillment_order_ids ) ) {
                return new WP_Error(
                    'supplier_has_unfinished_orders',
                    'Supplier has unfinished fulfillment actions. Resolve or force delete.',
                    array(
                        'status'                        => 409,
                        'reason'                        => 'supplier_orders_not_fulfilled',
                        'pending_fulfillment_order_ids' => $pending_fulfillment_order_ids,
                    )
                );
            }
        }

        $products_unassigned = $this->unassign_supplier_from_products( $supplier_id );
        $deleted = $supplier->delete_and_reassign( $reassign );
        if ( ! $deleted ) {
            return new WP_Error( 'delete_failed', 'Failed to delete supplier.', array( 'status' => 500 ) );
        }

        return array(
            'deleted'            => true,
            'supplier_id'        => $supplier_id,
            'products_unassigned'=> $products_unassigned,
            'forced'             => (bool) $force,
        );
    }

    /**
     * Return order IDs where supplier fulfillment is still not complete.
     *
     * @param HC_Supplier $supplier Supplier model.
     * @return int[]
     */
    private function get_unfinished_order_ids_for_supplier( HC_Supplier $supplier ): array {
        $supplier_id = (int) $supplier->get_id();
        if ( ! $supplier_id ) {
            return array();
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            return array();
        }

        $order_ids = array_filter( array_map( 'absint', (array) $supplier->get_assigned_orders() ) );
        if ( empty( $order_ids ) ) {
            return array();
        }

        $terminal_order_statuses = array( 'completed', 'cancelled', 'refunded', 'failed', 'trash' );
        $unfinished = array();

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            if ( in_array( $order->get_status(), $terminal_order_statuses, true ) ) {
                continue;
            }

            $supplier_data = $order->get_meta( '_supplier_data', true );
            $entry = is_array( $supplier_data ) && isset( $supplier_data[ $supplier_id ] ) ? $supplier_data[ $supplier_id ] : null;
            $status = is_array( $entry ) ? sanitize_text_field( (string) ( $entry['fulfillment_status'] ?? 'pending' ) ) : 'pending';

            if ( 'fulfilled' !== $status ) {
                $unfinished[] = (int) $order_id;
            }
        }

        return array_values( array_unique( $unfinished ) );
    }

    /**
     * Remove supplier assignment from product and variation meta.
     *
     * @param int $supplier_id Supplier ID.
     * @return int Number of posts updated.
     */
    private function unassign_supplier_from_products( int $supplier_id ): int {
        if ( $supplier_id <= 0 ) {
            return 0;
        }

        $products = get_posts(
            array(
                'post_type'   => array( 'product', 'product_variation' ),
                'numberposts' => -1,
                'fields'      => 'ids',
                'meta_query'  => array(
                    array(
                        'key'     => '_supplier_id',
                        'value'   => $supplier_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        $count = 0;
        foreach ( (array) $products as $post_id ) {
            if ( delete_post_meta( (int) $post_id, '_supplier_id' ) ) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Schedules the welcome email via Action Scheduler.
     *
     * @param string $email
     * @param string $supplier_name
     * @param string $user_id
     * @return void
     */
    public static function scheduleWelcomeEmail(string $email, string $supplier_name, string $user_id, string $password): void
    {
        if (function_exists('as_enqueue_async_action')) {
            $action_id = as_enqueue_async_action(
                'havencore_send_supplier_welcome_email',
                [
                    'email'         => $email,
                    'supplier_name' => $supplier_name,
                    'user_id'       => $user_id,
                ],
                'hc-supplier-emails'
            );

                
            // ✅ Store the action ID for logging later in the job
            update_option("_supplier_email_action_{$email}", $action_id);
    
            // error_log("✅ Scheduled with Action Scheduler for $email ($supplier_name), action ID: $action_id");
    
            if ($user_id && is_numeric($user_id)) {
                update_user_meta($user_id, '_temporary_supplier_password', $password);
            }

        } else {
            wp_schedule_single_event(
                time() + 10,
                'havencore_send_supplier_welcome_email',
                [
                    'email'         => $email,
                    'supplier_name' => $supplier_name,
                    'user_id'       => $user_id,
                ]
            );
        }
    }

}
