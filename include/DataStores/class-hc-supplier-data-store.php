<?php

namespace HavenCore\DataStores;

use HavenCore\Interfaces\HC_Object_Data_Store_Interface;
use HavenCore\Classes\HC_Supplier;

/**
 * Supplier Data Store.
 *
 * @package HavenCore\DataStores
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplier Data Store class.
 */
class HC_Supplier_Data_Store implements HC_Object_Data_Store_Interface {

    /**
     * Create a supplier in the user system and store extra meta data.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @throws Exception If creation fails.
     */
    public function create( &$supplier ) {
        // Generate a random 8-character password
        $password = wp_generate_password( 8, false );

        // Create a new WordPress user for the supplier
        $user_id = wp_insert_user(
            array(
                'user_login'   => $supplier->get_name(),  // Customize based on your needs
                'user_email'   => $supplier->get_email(), // Use core email
                'display_name' => $supplier->get_name(),
                'role'         => 'supplier',
                'user_pass'    => $password, // 👈 Set the password
            )
        );

        if ( is_wp_error( $user_id ) ) {
            throw new \Exception( 'Could not create supplier: ' . $user_id->get_error_message() );
        }

        // Set the user ID on the supplier object
        $supplier->set_id( $user_id );

        // Store the password in the object (write-only)
        $supplier->set_password( $password );

        // Save any additional user meta
        $this->update_meta( $supplier );
    }

    /**
     * Read all supplier objects from the system.
     *
     * @return HC_Supplier[] Array of suppliers.
     */
    public function read_all(): array {
        $args = array(
            'role'    => 'supplier',
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array('ID'),
        );

        $query     = new \WP_User_Query($args);
        $suppliers = [];

        foreach ($query->get_results() as $user) {
            try {
                $supplier = new HC_Supplier($user->ID);
                $suppliers[] = $supplier;
            } catch (\Exception $e) {
                // Optionally log or ignore
            }
        }

        return $suppliers;
    }

    /**
     * Read supplier data from the user system and user meta.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @throws Exception If supplier is invalid.
     */
    public function read( &$supplier ) {
        $user_id = $supplier->get_id();
        $user    = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            throw new \Exception( 'Invalid supplier.' );
        }

        // Set the supplier data from core user fields
        $supplier->set_is_active( (bool) get_user_meta( $user_id, 'is_active', true ) );
        $supplier->set_name( $user->display_name );
        $supplier->set_email( $user->user_email );  // Core user data
        $supplier->set_phone( get_user_meta( $user_id, 'phone', true ) ); // Custom meta field
        $supplier->set_locale( get_user_meta( $user_id, 'locale', true ) ); // Custom meta field
        $supplier->set_paypal_email( get_user_meta( $user_id, 'paypal_email', true ) );
        $supplier->set_addresses( get_user_meta( $user_id, 'addresses', true ) );
        $supplier->set_socials( get_user_meta( $user_id, 'socials', true ) );
        $supplier->set_attributes( get_user_meta( $user_id, 'attributes', true ) );
        $supplier->set_assigned_orders( get_user_meta( $user_id, 'assigned_orders', true ) ); // Fetch assigned orders


        // Mark as read
        $supplier->set_object_read( true );
    }

    /**
     * Update supplier data in the user system and user meta.
     *
     * @param HC_Supplier $supplier Supplier object.
     */
    public function update( &$supplier ) {
        // Update WordPress user data
        wp_update_user(
            array(
                'ID'           => $supplier->get_id(),
                'display_name' => $supplier->get_name(),
            )
        );

        // Update user meta for additional fields
        $this->update_meta( $supplier );
    }

    /**
     * Delete a supplier from the user system.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @param array       $args Deletion args (force delete, reassign, etc).
     */
    public function delete( &$supplier, $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'force_delete' => false,
                'reassign'     => null,
            )
        );

        // Delete the user (supplier)
        wp_delete_user( $supplier->get_id(), $args['reassign'] );
    }

    /**
     * Update supplier meta.
     * This method matches the signature from the interface.
     *
     * @param HC_Supplier $data Data object.
     * @param object  $meta Meta object (containing ->id, ->key, and ->value).
     */
    public function update_meta( &$data, $meta = null ) {
        $user_id = $data->get_id();

        // Update the relevant meta fields for the supplier (non-core fields)
        update_user_meta( $user_id, 'phone', $data->get_phone() );
        update_user_meta( $user_id, 'locale', $data->get_locale() ); // Replacing 'language' with 'locale'
        update_user_meta( $user_id, 'paypal_email', $data->get_paypal_email() );
        update_user_meta( $user_id, 'addresses', $data->get_address() );
        update_user_meta( $user_id, 'is_active', $data->is_active() );
        update_user_meta( $user_id, 'socials', $data->get_socials() );
        update_user_meta( $user_id, 'attributes', $data->get_attributes() );



        // Update assigned orders
        update_user_meta( $user_id, 'assigned_orders', $data->get_assigned_orders() );

        // If you have any additional meta fields, you can handle them here
        if ( $meta ) {
            update_user_meta( $user_id, $meta->key, $meta->value );
        }
    }

    /**
     * Read meta data for the supplier.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @return array
     */
    public function read_meta( &$supplier ) {
        $user_id = $supplier->get_id();
        return array(
            'is_active'          => get_user_meta( $user_id, 'is_active', true ),
            'phone'              => get_user_meta( $user_id, 'phone', true ),
            'locale'             => get_user_meta( $user_id, 'locale', true ),
            'paypal_email'       => get_user_meta( $user_id, 'paypal_email', true ),
            'addresses'          => get_user_meta( $user_id, 'addresses', true ),
            'socials'            => get_user_meta( $user_id, 'socials', true ),
            'attributes'         => get_user_meta( $user_id, 'attributes', true ),
            'assigned_orders'    => get_user_meta( $user_id, 'assigned_orders', true ),
        );
    }

    /**
     * Delete meta data for the supplier.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @param object $meta Meta object.
     * @return void
     */
    public function delete_meta( &$supplier, $meta ) {
        $user_id = $supplier->get_id();
        delete_user_meta( $user_id, $meta->key );
    }

    /**
     * Add meta data for the supplier.
     *
     * @param HC_Supplier $supplier Supplier object.
     * @param object $meta Meta object.
     * @return int Meta ID.
     */
    public function add_meta( &$supplier, $meta ) {
        $user_id = $supplier->get_id();
        return add_user_meta( $user_id, $meta->key, $meta->value );
    }
}
