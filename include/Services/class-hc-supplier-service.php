<?php

namespace HavenCore\Services;

use HavenCore\Classes\HC_Supplier;
use HavenCore\Classes\HC_Data_Store;
use HavenCore\Utils\UserUtils;

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
     * Delete a supplier.
     *
     * @param int      $supplier_id
     * @param int|null $reassign Optional reassignment user ID.
     * @return bool
     */
    public function delete( int $supplier_id, ?int $reassign = null ): bool {
        $supplier = $this->get( $supplier_id );
        if ( ! $supplier ) return false;

        return $supplier->delete_and_reassign( $reassign );
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
