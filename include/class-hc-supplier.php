<?php

namespace HavenCore\Classes;

use HavenCore\Abstracts\HC_Data;

/**
 * The HavenCore Supplier class handles storage of the current supplier's data, such as contact and addresses.
 *
 * @package HavenCore\Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplier class.
 *
 * Handles storage of the supplier's data, such as contact info, address, orders, and active status.
 *
 * @package HavenCore\Classes
 */
class HC_Supplier extends HC_Data {

    /**
     * Stores supplier data.
     *
     * @var array
     */
    protected $data = array(
        'is_active'         => true,
        'name'              => '',
        'email'             => '',
        'phone'             => '',
        'locale'          => '',
        'paypal_email'      => '',
        'addresses'         => array(
            array(
                'street'    => '',
                'city'      => '',
                'postal_code'=> '',
                'country'   => '',
            ),
        ),
        'attributes'        => array(
            'needs_to_change_password' => true, // Default attribute
        ),  
        'assigned_orders'   => array(),  // List of orders assigned to this supplier (IDs or order objects)
    );

    /**
     * Stores a password if this needs to be changed. Write-only and hidden from _data.
     *
     * @var string
     */
    protected $password = '';

    /**
     * This is the name of this object type.
     *
     * @var string
     */
    protected $object_type = 'supplier';

    /**
     * Load supplier data based on the given input.
     *
     * @param HC_Supplier|int $data Supplier object or supplier ID.
     * @throws Exception If supplier cannot be read/found from the data store.
     */
    public function __construct( $data = 0 ) {
        if ( $data instanceof HC_Supplier ) {
            $this->set_id( absint( $data->get_id() ) );
        } elseif ( is_numeric( $data ) ) {
            $this->set_id( $data );
        }

        $this->data_store = HC_Data_Store::load( 'supplier' );

        // If we have an ID, load the supplier from the DB.
        if ( $this->get_id() ) {
            try {
                $this->data_store->read( $this );
            } catch ( \Exception $e ) {
                $this->set_id( 0 );
                $this->set_object_read( true );
            }
        } else {
            $this->set_object_read( true );
        }
    }

    /**
     * Delete a supplier and reassign posts.
     *
     * @param int $reassign Reassign posts and links to new Supplier ID.
     * @return bool
     */
    public function delete_and_reassign( $reassign = null ) {
        if ( $this->data_store ) {
            $this->data_store->delete(
                $this,
                array(
                    'force_delete' => true,
                    'reassign'     => $reassign,
                )
            );
            $this->set_id( 0 );
            return true;
        }
        return false;
    }

    /**
     * Get a supplier's avatar URL.
     *
     * @return string
     */
    public function get_avatar_url() {
        return get_avatar_url( $this->get_email() );
    }

    /**
     * Get the supplier's address.
     *
     * @return array
     */
    public function get_address() {
        return $this->data['addresses'];
    }

    /**
     * Get this supplier's contact phone.
     *
     * @return string
     */
    public function get_phone() {
        return $this->data['phone'];
    }

    /**
     * Get this supplier's contact email.
     *
     * @return string
     */
    public function get_email() {
        return $this->data['email'];
    }

    /**
     * Get the supplier's social media links.
     *
     * @return array
     */
    public function get_socials() {
        return $this->data['socials'];
    }

    /**
     * Get the supplier's preferred locale.
     *
     * @return string
     */
    public function get_locale() {
        return $this->data['locale'];
    }

    /**
     * Get the supplier's PayPal email address.
     *
     * @return string
     */
    public function get_paypal_email() {
        return $this->data['paypal_email'];
    }

    /**
     * Get the supplier's ID.
     *
     * @return int
     */
    public function get_id() {
        return isset( $this->data['id'] ) ? $this->data['id'] : 0;
    }

    /**
     * Set the supplier's ID.
     *
     * @param int $id Supplier ID.
     */
    public function set_id( $id ) {
        $this->data['id'] = $id;
    }

    /**
     * Getter and setter for supplier's name.
     */
    public function get_name() {
        return $this->data['name'];
    }

    public function set_name( $name ) {
        $this->data['name'] = $name;
    }

    /**
     * Getter and setter for email.
     */
    public function set_email( $email ) {
        if ( ! is_email( $email ) ) {
            throw new \Exception( 'Invalid email address' );
        }
        $this->data['email'] = sanitize_email( $email );
    }

    /**
     * Getter and setter for phone.
     */
    public function set_phone( $phone ) {
        $this->data['phone'] = $phone;
    }

    /**
     * Getter and setter for locale.
     */
    public function set_locale( $locale ) {
        $this->data['locale'] = $locale;
    }

    /**
     * Getter and setter for PayPal email.
     */
    public function set_paypal_email( $paypal_email ) {
        $this->data['paypal_email'] = $paypal_email;
    }

    /**
     * Getter and setter for social media links.
     */
    public function set_socials( $socials ) {
        $this->data['socials'] = $socials;
    }

    /**
     * Getter and setter for addresses.
     */
    public function set_addresses( $addresses ) {
        $this->data['addresses'] = $addresses;
    }
    
    /**
     * Set custom attributes, with the option to merge or overwrite existing attributes.
     *
     * @param array  $attributes List of custom attributes.
     * @param bool   $overwrite Whether to overwrite the existing attributes (default is false, meaning merge).
     */
    public function set_attributes( $attributes, $overwrite = false ) {
        if ( ! is_array( $attributes ) ) {
            $attributes = [];
        }

        if ( $overwrite ) {
            // Overwrite the entire attributes array
            $this->data['attributes'] = $attributes;
        } else {
            // Merge with existing attributes
            $this->data['attributes'] = array_merge( $this->data['attributes'], $attributes );
        }
    }


    /**
     * Set assigned orders, with the option to merge or overwrite existing orders.
     *
     * @param array $orders List of order IDs or order objects.
     * @param bool  $overwrite Whether to overwrite the existing orders (default is false, meaning merge).
     */
    public function set_assigned_orders( $orders, $overwrite = false ) {
        if ( ! is_array( $orders ) ) {
            $orders = []; // If orders are not an array, convert it to an empty array
        }

        if ( $overwrite ) {
            // Overwrite the existing assigned orders
            $this->data['assigned_orders'] = $orders;
        } else {
            // Merge the new orders with the existing ones
            $this->data['assigned_orders'] = array_unique( array_merge( $this->data['assigned_orders'], $orders ) );
        }
    }

    /**
     * Remove a specific order ID from the assigned orders list.
     *
     * @param int $order_id
     * @return void
     */
    public function remove_assigned_order( int $order_id ): void {
        $order_id = (int) $order_id;
        if ( ! $order_id ) {
            return;
        }

        $existing = array_map( 'intval', $this->data['assigned_orders'] );
        $filtered = array_filter(
            $existing,
            static function ( $value ) use ( $order_id ) {
                return (int) $value !== $order_id;
            }
        );

        $this->data['assigned_orders'] = array_values( $filtered );
    }


    /**
     * Getter for custom attributes.
     *
     * @return array List of custom attributes to this supplier.
     */
    public function get_attributes() {
        return $this->data['attributes'];
    }


    /**
     * Getter for assigned orders.
     *
     * @return array List of orders assigned to this supplier.
     */
    public function get_assigned_orders() {
        return $this->data['assigned_orders'];
    }

    /**
     * Getter for password (write-only).
     */
    public function set_password( $password ) {
        $this->password = $password;
    }

    /**
     * Getter and setter for is_active status.
     */
    public function set_is_active( $status ) {
        $this->data['is_active'] = (bool) $status;
    }


    /**
     * Check if the supplier is active.
     *
     * @return bool
     */
    public function is_active() {
        return $this->data['is_active'];
    }

    /**
     * Get supplier password (write-only).
     */
    public function get_password() {
        return $this->password;
    }

    /**
     * Persist the supplier data to the underlying data store.
     *
     * If the supplier object has an ID, it is considered existing and will be updated.
     * Otherwise, a new entry will be created in the data store.
     *
     * @return void
     */
    public function save() {
        if ( $this->get_id() ) {
            $this->data_store->update( $this );
        } else {
            $this->data_store->create( $this );
        }
    }
}
