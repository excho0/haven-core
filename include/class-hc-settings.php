<?php

namespace HavenCore\Classes;
use HavenCore\Utils\ArrayHelpers;


class HC_Settings {

    /**
     * Option name used to store settings in wp_options.
     */
    private $option_name = 'havencore_settings';

    /**
     * Cached settings array.
     *
     * @var array
     */
    private $settings = [];

    /**
     * Constructor — optionally preload settings.
     */
    public function __construct($autoload = true) {
        if ($autoload) {
            $this->load();
        }
    }

    /**
     * Defines the shape, storage keys, and default values for all plugin settings.
     *
     * The array is nested to mirror the UI/grouping structure. Each leaf entry
     * contains:
     *  - key     : the flat database/option name under which the value is stored
     *  - default : the value to use when none is saved yet
     *
     * @var array<string, mixed>
     */
    public static array $settingSchema = [
        'general' => [
            'icon' => 'pi pi-cog',
            'custom_login_page' => [
                'key'     => 'custom_login_ui',
                'default' => true,
                'tooltip' => 'Turn on/off the custom login UI that replaces the default WordPress login page',
                'icon'    => 'pi pi-palette',
            ],
            'password_reset_page' => [
                'key'     => 'password_reset_page',
                'default' => false,
                'tooltip' => 'Reroute WordPress/WooCommerce password reset emails through the Account Security page.',
                'icon'    => 'pi pi-lock',
            ],
        ],
        'suppliers' => [
            'icon' => 'pi pi-users',
            'strict_validation' => [
                'key'     => 'strict_validation',
                'default' => true,
                'tooltip' => 'Enforces strict field validation when adding new suppliers.',
                'icon'    => 'pi pi-shield',
            ],
        ],
        // 'integrations' => [
        //     'icon' => 'pi pi-sitemap',
        //     'paypal' => [
        //         'icon' => 'pi pi-paypal',
        //         'client_id' => [
        //             'key'     => 'client_id',
        //             'default' => '',
        //             'tooltip' => 'Your PayPal client ID for API authentication.',
        //             'icon'    => 'pi pi-key',
        //         ],
        //         'client_secret' => [
        //             'key'     => 'client_secret',
        //             'default' => '',
        //             'tooltip' => 'The secret key associated with your PayPal client.',
        //             'icon'    => 'pi pi-lock',
        //         ],
        //         'sandbox' => [
        //             'key'     => 'sandbox',
        //             'default' => true,
        //             'tooltip' => 'Enable sandbox mode for PayPal (used for testing).',
        //             'icon'    => 'pi pi-box',
        //         ],
        //     ],
        // ],
        'WooCommerce' => [
            'icon' => 'pi pi-shopping-cart',

            // Account Deletion & Management Flow
            'customer_farewell' => [
                'key'     => 'customer_farewell',
                'default' => false,
                'tooltip' => 'Turn on/off the ability for customers to delete their accounts, including buttons, pop-ups, and background processes"',
                'icon'    => 'pi pi-user-minus',
            ],
            
            'order_review_before_payment' => [
                'key'     => 'order_review_before_payment',
                'default' => false,
                'tooltip' => 'Let customers place orders without paying right away. You review the order and send a payment link when ready.',
                'icon'    => 'pi pi-clock', // optional: 'pi pi-lock', 'pi pi-hand-stop'
            ],
            'account_security_flow' => [
                'key'     => 'account_security_flow',
                'default' => false,
                'tooltip' => 'Require customers to verify their email before activating accounts or claiming guest orders. Disables automatic login until verification completes.',
                'icon'    => 'pi pi-shield',
            ],
        ],
        'notifications' => [
            'icon' => 'pi pi-envelope',
            'customer_verification_email' => [
                'key'     => 'notify_customer_verification_email',
                'default' => true,
                'tooltip' => 'Send verification emails for new customer accounts and guest checkout claims.',
                'icon'    => 'pi pi-check-circle',
            ],
            'customer_password_reset_email' => [
                'key'     => 'notify_customer_password_reset_email',
                'default' => true,
                'tooltip' => 'Send ' . PLUGIN_NAME . '’s custom password reset emails.',
                'icon'    => 'pi pi-refresh',
            ],
            'customer_account_removal_email' => [
                'key'     => 'notify_customer_account_removal_email',
                'default' => true,
                'tooltip' => 'Send confirmation emails for account removal/cleanup requests.',
                'icon'    => 'pi pi-user-minus',
            ],
            'customer_tracking_emails' => [
                'key'     => 'notify_customer_tracking_emails',
                'default' => true,
                'tooltip' => 'Email customers when suppliers submit tracking details.',
                'icon'    => 'pi pi-truck',
            ],
            'supplier_assignment_email' => [
                'key'     => 'notify_supplier_assignment_email',
                'default' => true,
                'tooltip' => 'Email suppliers whenever a new order is assigned to them.',
                'icon'    => 'pi pi-briefcase',
            ],
            'supplier_reassignment_email' => [
                'key'     => 'notify_supplier_reassignment_email',
                'default' => true,
                'tooltip' => 'Notify suppliers if an existing order gets reassigned to them.',
                'icon'    => 'pi pi-user-edit',
            ],
            'supplier_welcome_email' => [
                'key'     => 'notify_supplier_welcome_email',
                'default' => true,
                'tooltip' => 'Send welcome/onboarding emails to newly invited suppliers.',
                'icon'    => 'pi pi-send',
            ],
            'customer_payment_reminder_email' => [
                'key'     => 'notify_customer_payment_reminder_email',
                'default' => true,
                'tooltip' => 'Send payment reminder emails when orders are moved back to pending.',
                'icon'    => 'pi pi-credit-card',
            ],
            'notify_admin_supplier_product_updates' => [
                'key'     => 'notify_admin_supplier_product_updates',
                'default' => true,
                'tooltip' => 'Alert the site administrator when suppliers update product inventory or pricing.',
                'icon'    => 'pi pi-eye',
            ],
        ],
    ];

    /**
     * Encrypt an array of settings using the WordPress AUTH_KEY.
     *
     * @param array $data
     * @return string Encrypted base64 string
     */
    private function encryptSettings(array $data): string {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'havencore-super-secret-default-key-1234-*'; // Use WordPress AUTH_KEY
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a base64 string into settings array using the WordPress AUTH_KEY.
     *
     * @param string $data
     * @return array|null
     */
    private function decryptSettings(string $data): ?array {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'havencore-super-secret-default-key-1234-*'; // Use WordPress AUTH_KEY
        $raw = base64_decode($data, true);

        if ($raw === false || strlen($raw) < openssl_cipher_iv_length('aes-256-cbc')) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($raw, 0, $ivLength);
        $cipherText = substr($raw, $ivLength);

        $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return json_decode($decrypted, true);
    }

    /**
     * Load settings from wp_options into memory.
     *
     * @return bool True if settings loaded, false otherwise.
     */
    public function load(): bool
    {
        $stored = get_option($this->option_name);
        $defaults = ArrayHelpers::buildDefaults(self::$settingSchema);
    
        if (is_string($stored)) {
            $decrypted = $this->decryptSettings($stored);
    
            if (is_array($decrypted)) {
                $filtered = ArrayHelpers::filterBySchema($decrypted, self::$settingSchema);
                $this->settings = array_replace_recursive($defaults, $filtered);
                return true;
            }
        }
    
        $this->settings = $defaults;
        return false;
    }    

    /**
     * Get all settings as full array.
     *
     * @return array
     */
    public function getAll(): array {
        return $this->settings;
    }

    /**
     * Get a nested setting using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        $segments = explode('.', $key);
        $value = $this->settings;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Get UI metadata (icon and tooltip) for each setting.
     *
     * @return array
     */
    public function getMeta(): array
    {
        return ArrayHelpers::extractUiMeta(self::$settingSchema);
    }


    /**
     * Set a nested setting using dot notation.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void {
        $segments = explode('.', $key);
        $ref = &$this->settings;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /**
     * Delete a nested setting using dot notation.
     *
     * @param string $key
     */
    public function delete(string $key): void {
        $segments = explode('.', $key);
        $ref = &$this->settings;

        foreach ($segments as $i => $segment) {
            if (!isset($ref[$segment])) {
                return; // Key path doesn't exist
            }

            if ($i === count($segments) - 1) {
                unset($ref[$segment]);
                return;
            }

            $ref = &$ref[$segment];
        }
    }

    /**
     * Save current settings to wp_options.
     *
     * Ensures only valid keys (defined in the schema) are persisted,
     * stripping out any unrecognized or dynamic data before writing.
     *
     * @return bool True on success, false on failure.
     */
    public function save(): bool
    {
        $cleaned = ArrayHelpers::filterBySchema($this->settings, self::$settingSchema);
        $encrypted = $this->encryptSettings($cleaned);
        return update_option($this->option_name, $encrypted);
    }

    /**
     * Completely delete all stored settings from wp_options.
     *
     * @return bool True if deleted, false otherwise.
     */
    public function deleteAll(): bool {
        return delete_option($this->option_name);
    }

    /**
     * Flatten nested settings (for debug/UI).
     *
     * @param array $array The array to flatten. Default is an empty array.
     * @param string $prefix Prefix used in dot notation (for recursive calls).
     * @return array Flattened array with dot notation keys.
     */
    public function flatten(array $array = [], string $prefix = ''): array {
        $result = [];

        foreach ($array as $key => $value) {
            $compositeKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $compositeKey);
            } else {
                $result[$compositeKey] = $value;
            }
        }

        return $result;
    }

}
