<?php

namespace HavenCore\Ajax;

use HavenCore\Services\HC_Supplier_Service;
use HavenCore\Classes\HC_Settings;
use HavenCore\Utils\UserUtils;



/**
 * Class SupplierManagerAjax
 *
 * Handles all AJAX actions related to supplier management:
 * - Save field updates (inline editing)
 * - Add new suppliers
 * - Delete existing suppliers
 *
 * Registered via AjaxManager::registerAll().
 *
 * @package HavenCore\Ajax
 */
class SupplierManagerAjax
{
    /**
     * Register all AJAX endpoints for supplier management.
     *
     * Hooks:
     * - wp_ajax_save_suppliers_ajax
     * - wp_ajax_add_supplier_ajax
     * - wp_ajax_delete_supplier_ajax
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_havencore_save_suppliers_ajax', [self::class, 'save']);
        add_action('wp_ajax_havencore_add_supplier_ajax', [self::class, 'add']);
        add_action('wp_ajax_havencore_delete_supplier_ajax', [self::class, 'delete']);
        add_action('wp_ajax_havencore_get_all_suppliers', [self::class, 'fetchAll']);
    }

    /**
     * Handle saving individual supplier fields via AJAX.
     *
     * Expects a POST payload with keys like:
     *   - name[supplier_id] => value
     *   - email[supplier_id] => value
     *
     * @return void Outputs JSON success/failure
     */
    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // error_log('Supplier save AJAX payload: ' . print_r($_POST, true));

        $fieldMap = [
            'name'         => 'name',
            'email'        => 'email',
            'paypal_email' => 'paypal_email',
            'phone'        => 'phone',
            'country'      => 'addresses.0.country',
            'language'     => 'locale',
            'socials'      => 'socials'
        ];

        $supplierService = new HC_Supplier_Service();

        foreach ($_POST as $fieldKey => $values) {
            if (!isset($fieldMap[$fieldKey]) || !is_array($values)) continue;

            foreach ($values as $id => $value) {
                $id = absint($id);
                if (! $id || $value === '') continue;

                $supplier = $supplierService->get($id);
                if (! $supplier) continue;

                if (UserUtils::is_protected_user($supplier->get_email())) {
                    error_log("⚠️ Blocked update to protected admin supplier ID $id.");
                    continue;
                }

                $updateData = [];

                $mappedKey = $fieldMap[$fieldKey];

                if ($fieldKey === 'socials') {
                    $decoded = json_decode(stripslashes($value), true);
                    if (is_array($decoded)) {
                        $updateData['socials'] = array_map(function ($entry) {
                            return [
                                'type' => sanitize_text_field($entry['type'] ?? ''),
                                'url'  => esc_url_raw($entry['url'] ?? '')
                            ];
                        }, $decoded);
                    }
                } elseif ($fieldKey === 'country') {
                    $existing = $supplier->get_address(); // Existing structured address array
                    $first = $existing[0] ?? [];
                    $first['country'] = sanitize_text_field($value);
                    $updateData['addresses'] = [$first]; // Safe merge

                } else {
                    $updateData[$mappedKey] = in_array($fieldKey, ['email', 'paypal_email'])
                        ? sanitize_email($value)
                        : sanitize_text_field($value);
                }

                $supplierService->update($id, $updateData);
            }
        }

        wp_send_json_success();
    }

    /**
     * Handle AJAX request to add a new supplier.
     *
     * Expects POST fields:
     *   - name, email, phone, country, language, paypal_email
     *
     * @return void Outputs JSON with new supplier ID or error message
     */
    public static function add(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $name         = sanitize_text_field($_POST['name'] ?? '');
        $email        = sanitize_email($_POST['email'] ?? '');
        $paypal_email = sanitize_email($_POST['paypal_email'] ?? '');
        $phone        = sanitize_text_field($_POST['phone'] ?? '');
        $country      = sanitize_text_field($_POST['country'] ?? '');
        $language     = sanitize_text_field($_POST['language'] ?? '');
        $socialsRaw   = $_POST['socials'] ?? '[]';

        if (UserUtils::is_protected_user($email)) {

            $errors = [
                'email' => 'Email'
            ];

            wp_send_json_error([
                'message' => 'This email belongs to a protected admin account and cannot be assigned as a supplier.',
                'fields'  => $errors
            ]);
        }

        $errors = [];
        if (!$name)  $errors['name'] = 'Name is required.';
        if (!$email) $errors['email'] = 'Email is required.';

        if (!empty($errors)) {
            $fieldNames = [
                'name' => 'Name',
                'email' => 'Email',
                'paypal_email' => 'PayPal Email',
                'phone' => 'Phone',
                'country' => 'Country',
                'language' => 'Language'
            ];
            $readableList = array_map(fn($f) => $fieldNames[$f] ?? ucfirst($f), array_keys($errors));
            wp_send_json_error([
                'message' => 'Missing required field' . (count($readableList) > 1 ? 's' : '') . ': ' . implode(', ', $readableList) . '.',
                'fields'  => $errors
            ]);
        }

        if (email_exists($email)) {
            $errors = [
                'email' => 'Email'
            ];
            wp_send_json_error([
                'message' => 'A supplier with this email already exists. Please use a different email.',
                'fields'  => $errors
            ]);
        }

        $decoded = json_decode(stripslashes($socialsRaw), true);
        $sanitizedSocials = [];

        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                $type = sanitize_text_field($entry['type'] ?? '');
                $url  = esc_url_raw($entry['url'] ?? '');
                if ($type && $url) {
                    $sanitizedSocials[] = ['type' => $type, 'url' => $url];
                }
            }
        }

        // ✅ Create supplier (and linked user) using supplier service
        $supplierService = new HC_Supplier_Service();
        $supplier = $supplierService->create([
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'locale'       => $language,
            'paypal_email' => $paypal_email,
            'addresses'    => [[ 'country' => $country ]],
            'socials'      => $sanitizedSocials,
            'is_active'    => true,
        ]);

        if (! $supplier) {
            wp_send_json_error(['message' => 'Failed to create supplier.']);
        }

        $user_id = $supplier->get_id();
        $password = $supplier->get_password();

        // Optional email hook for later
        $settings = new HC_Settings();
        
        if ($settings->get('suppliers.notify_on_add', true)) {
            self::scheduleWelcomeEmail($email, $name, $user_id, $password);
        }

        wp_send_json_success(['id' => $supplier->get_id()]);
    }


    /**
     * Handle AJAX request to delete a supplier.
     *
     * Expects POST:
     *   - id => supplier ID
     *
     * Deletes any product meta assignments to the supplier.
     *
     * @return void Outputs JSON success/failure
     */
    public static function delete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = absint($_POST['supplier_id'] ?? 0);
        if (! $id) {
            wp_send_json_error(['message' => 'Invalid supplier ID.']);
        }

        $supplierService = new HC_Supplier_Service();
        $supplier = $supplierService->get($id);

        if (! $supplier) {
            wp_send_json_error(['message' => 'Supplier not found.']);
        }

        // Optional: detach supplier from products (business logic stays here unless moved into service)
        $products = get_posts([
            'post_type'   => 'product',
            'numberposts' => -1,
            'meta_query'  => [[
                'key'     => '_supplier_id',
                'value'   => $id,
                'compare' => '=',
            ]],
        ]);

        foreach ($products as $product) {
            delete_post_meta($product->ID, '_supplier_id');
        }

        $deleted = $supplierService->delete($id);

        if (! $deleted) {
            wp_send_json_error(['message' => 'Failed to delete supplier.']);
        }

        wp_send_json_success();
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

    /**
     * Handles AJAX request to fetch all suppliers.
     *
     * Returns a simplified array of supplier data for frontend usage.
     * This endpoint is only accessible to users with 'manage_options' capability.
     *
     * Expected usage: admin panel supplier management screen.
     *
     * @action wp_ajax_havencore_get_all_suppliers
     * @return void Outputs JSON: { success: true, data: [...] } or { success: false, message: ... }
     */
    public static function fetchAll(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $service  = new HC_Supplier_Service();
        $suppliers = $service->all();

        $formatted = array_map(function($s) {
            $addresses = $s->get_address();
            $country = is_array($addresses) && isset($addresses[0]['country']) ? $addresses[0]['country'] : '';

            return [
                'id'           => $s->get_id(),
                'is_active'    => $s->is_active(),
                'name'         => $s->get_name(),
                'email'        => $s->get_email(),
                'paypal_email' => $s->get_paypal_email(),
                'phone'        => $s->get_phone(),
                'country'      => $country,
                'language'     => $s->get_locale(),
                'socials'      => $s->get_socials(),
                'attributes'   => $s->get_attributes(),
            ];
        }, $suppliers);

        wp_send_json_success($formatted);
    }
}
