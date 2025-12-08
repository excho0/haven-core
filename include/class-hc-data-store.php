<?php

namespace HavenCore\Classes;
use HavenCore\Interfaces\HC_Object_Data_Store_Interface;

/**
 * HC Data Store.
 *
 * @package HavenCore\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * HC Data Store class.
 */
class HC_Data_Store {

    /**
     * Contains an instance of the data store class that we are working with.
     *
     * @var HC_Data_Store
     */
    private $instance = null;

    /**
     * Contains an array of default HC supported data stores.
     * Format of object name => class name.
     *
     * @var array
     */
    private $stores = array(
        'supplier'     => \HavenCore\DataStores\HC_Supplier_Data_Store::class,
        'conversation' => \HavenCore\DataStores\HC_Conversation_Data_Store::class,
        'message'      => \HavenCore\DataStores\HC_Message_Data_Store::class,
    );

    /**
     * Contains the name of the current data store's class name.
     *
     * @var string
     */
    private $current_class_name = '';

    /**
     * The object type this store works with.
     *
     * @var string
     */
    private $object_type = '';

    /**
     * Tells HC_Data_Store which object (supplier) store we want to work with.
     *
     * @param string $object_type Name of object.
     */
    public function __construct( $object_type ) {
        $this->object_type = $object_type;
        $this->stores = apply_filters( 'havencore_data_stores', $this->stores );

        if ( ! array_key_exists( $object_type, $this->stores ) ) {
            throw new \Exception( __( 'Invalid data store.', HAVEN_CORE_TEXT_DOMAIN ) );
        }

        $store = apply_filters( 'havencore_' . $object_type . '_data_store', $this->stores[ $object_type ] );

        if ( is_object( $store ) ) {
            if ( ! $store instanceof HC_Object_Data_Store_Interface ) {
                throw new \Exception( __( 'Invalid data store.', HAVEN_CORE_TEXT_DOMAIN ) );
            }
            $this->current_class_name = get_class( $store );
            $this->instance           = $store;
        } else {
            if ( ! class_exists( $store ) ) {
                throw new \Exception( __( 'Invalid data store.', HAVEN_CORE_TEXT_DOMAIN ) );
            }
            $this->current_class_name = $store;
            $this->instance           = new $store();
        }
    }

    /**
     * Loads a data store.
     *
     * @param string $object_type Name of object.
     * @return HC_Data_Store
     */
    public static function load( $object_type ) {
        return new HC_Data_Store( $object_type );
    }

    /**
     * Returns the class name of the current data store.
     *
     * @return string
     */
    public function get_current_class_name() {
        return $this->current_class_name;
    }

    /**
     * Reads an object from the data store.
     *
     * @param HC_Supplier $data Supplier object instance.
     */
    public function read( &$data ) {
        $this->instance->read( $data );
    }

    /**
     * Creates a supplier in the data store.
     *
     * @param HC_Supplier $data Supplier object instance.
     */
    public function create( &$data ) {
        $this->instance->create( $data );
    }

    /**
     * Updates a supplier in the data store.
     *
     * @param HC_Supplier $data Supplier object instance.
     */
    public function update( &$data ) {
        $this->instance->update( $data );
    }

    /**
     * Deletes a supplier from the data store.
     *
     * @param HC_Supplier $data Supplier object instance.
     * @param array       $args Array of args to pass to the delete method.
     */
    public function delete( &$data, $args = array() ) {
        $this->instance->delete( $data, $args );
    }

    /**
     * Data stores can define additional functions (for example, suppliers can have special methods).
     * This passes through to the instance if that function exists.
     *
     * @param string $method     Method.
     * @param mixed  $parameters Parameters.
     * @return mixed
     */
    public function __call( $method, $parameters ) {
        if ( is_callable( array( $this->instance, $method ) ) ) {
            $object     = array_shift( $parameters );
            $parameters = array_merge( array( &$object ), $parameters );
            return $this->instance->$method( ...$parameters );
        }
    }

    /**
     * Check if the data store we are working with has a callable method.
     *
     * @param string $method Method name.
     * @return bool Whether the passed method is callable.
     */
    public function has_callable( string $method ) : bool {
        return is_callable( array( $this->instance, $method ) );
    }
}
