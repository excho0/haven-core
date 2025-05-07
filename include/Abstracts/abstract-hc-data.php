<?php

namespace HavenCore\Abstracts;

use HavenCore\Interfaces\HC_Object_Data_Store_Interface;

/**
 * Abstract HC Data Object
 *
 * Provides basic data handling for HavenCore data objects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class HC_Data {
	/**
	 * Object ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Object data.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Changed data.
	 *
	 * @var array
	 */
	protected $changes = array();

	/**
	 * Whether the object has been read from DB.
	 *
	 * @var bool
	 */
	protected $object_read = false;

	/**
	 * Reference to the data store.
	 *
	 *  @var HC_Object_Data_Store_Interface
	 */
	protected $data_store;

	/**
	 * Get the ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set the ID.
	 *
	 * @param int $id
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Get object read status.
	 *
	 * @return bool
	 */
	public function get_object_read() {
		return (bool) $this->object_read;
	}

	/**
	 * Set object read status.
	 *
	 * @param bool $read
	 */
	public function set_object_read( $read = true ) {
		$this->object_read = (bool) $read;
	}

	/**
	 * Get changes.
	 *
	 * @return array
	 */
	public function get_changes() {
		return $this->changes;
	}

	/**
	 * Apply changes to data.
	 */
	public function apply_changes() {
		$this->data    = array_merge( $this->data, $this->changes );
		$this->changes = array();
	}

	/**
	 * Set property value.
	 *
	 * @param string $prop
	 * @param mixed  $value
	 */
	protected function set_prop( $prop, $value ) {
		if ( array_key_exists( $prop, $this->data ) ) {
			if ( $this->object_read ) {
				if ( $value !== $this->data[ $prop ] ) {
					$this->changes[ $prop ] = $value;
				}
			} else {
				$this->data[ $prop ] = $value;
			}
		}
	}

	/**
	 * Get property value.
	 *
	 * @param string $prop
	 * @return mixed|null
	 */
	protected function get_prop( $prop ) {
		if ( array_key_exists( $prop, $this->changes ) ) {
			return $this->changes[ $prop ];
		} elseif ( array_key_exists( $prop, $this->data ) ) {
			return $this->data[ $prop ];
		}
		return null;
	}
}
