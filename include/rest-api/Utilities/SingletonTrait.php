<?php
/**
 * Singleton class trait.
 *
 * @package HavenCore\RestApi\Utilities
 */

namespace HavenCore\RestApi\Utilities;

use HC_REST_Exception;

/**
 * Singleton trait.
 */
trait SingletonTrait {
    /**
     * The single instance of the class.
     *
     * @var object|null
     */
    protected static $instance = null;

    /**
     * Protected constructor to prevent direct instantiation.
     */
    protected function __construct() {}

    /**
     * Get the singleton instance.
     *
     * @return object Instance of the using class.
     */
    final public static function instance() {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     *
     * @throws HC_REST_Exception
     */
    final public function __wakeup() {
        throw new HC_REST_Exception(
            'havencore_singleton_unserialize_error',
            __('Unserializing instances of this class is forbidden.', 'havencore'),
            500
        );
    }
}
