<?php

namespace HavenCore\RestApi\Controllers\V1;

use HC_REST_Controller;
use HavenCore\Services\EmailVerificationService;
use HavenCore\Utils\UserUtils;
use HavenCore\WooCommerce\Hooks\Customer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function wc_create_new_customer;
use function wc_get_order;

class HC_REST_Account_V1_Controller extends HC_REST_Controller
{
    protected $namespace = 'hc/v1';

    protected $rest_base = 'account';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/password/validate',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'validate_password_token'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/password/complete',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'complete_password_reset'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/removal/validate',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'validate_account_removal'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/removal/complete',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'complete_account_removal'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function validate_password_token(WP_REST_Request $request)
    {
        $token = sanitize_text_field($request->get_param('token'));

        if (empty($token)) {
            return new WP_Error('invalid_token', __('Missing verification token.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        $record = EmailVerificationService::get_request($token);

        if (!$record) {
            return new WP_Error('invalid_token', __('This link is invalid or has already been used.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        return new WP_REST_Response([
            'username'   => $record['username'] ?? '',
            'user_email' => $record['user_email'] ?? '',
            'context'    => $record['context'] ?? 'registration',
        ]);
    }

    public function complete_password_reset(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $token            = sanitize_text_field($params['token'] ?? '');
        $new_password     = (string)($params['new_password'] ?? '');
        $confirm_password = (string)($params['confirm_password'] ?? '');

        if (!$token) {
            return new WP_Error('invalid_token', __('Missing verification token.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        if ($new_password !== $confirm_password) {
            return new WP_Error('password_mismatch', __('Passwords do not match.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        if (!$this->password_meets_requirements($new_password)) {
            return new WP_Error('weak_password', __('Password must be at least 8 characters and include uppercase, lowercase, and numeric characters.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        $record = EmailVerificationService::get_request($token);
        if (!$record) {
            return new WP_Error('invalid_token', __('This link is invalid or has already been used.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        $context    = $record['context'] ?? 'registration';
        $user_email = $record['user_email'] ?? '';

        switch ($context) {
            case 'registration':
                $response = $this->complete_registration($record, $new_password);
                break;

            case 'reset':
                $response = $this->complete_reset_flow($record, $new_password);
                break;

            default:
                $response = new WP_Error('invalid_context', __('Unsupported password flow.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
                break;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        EmailVerificationService::mark_inactive($token);

        $default_redirect = ($context === 'registration')
            ? add_query_arg('conf_activate_account', '1', home_url('/'))
            : add_query_arg('password_reset', 'success', home_url('/'));

        $redirect_url = apply_filters(
            'haven_core_password_reset_redirect',
            $default_redirect,
            $record
        );

        return new WP_REST_Response([
            'message'       => $response['message'] ?? __('Password updated successfully.', HAVEN_CORE_TEXT_DOMAIN),
            'context'       => $context,
            'redirect_url'  => $redirect_url,
            'user_email'    => $user_email,
            'meta_updates'  => $response['meta_updates'] ?? [],
        ]);
    }

    public function validate_account_removal(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in to access this resource.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 401]);
        }

        $token = sanitize_text_field($request->get_param('token'));
        if (!$token) {
            return new WP_Error('missing_token', __('Missing verification token.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        $user = wp_get_current_user();

        if (!$user || !$user->ID) {
            return new WP_Error('user_not_found', __('User not found.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 404]);
        }

        $prefix = 'account_removal';
        if (!UserUtils::validate_verification_token($prefix, $user->user_email, $token)) {
            return new WP_Error('invalid_token', __('This link is invalid or has already been used.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        return new WP_REST_Response([
            'display_name' => $user->display_name,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email,
        ]);
    }

    public function complete_account_removal(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in to access this resource.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 401]);
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $token             = sanitize_text_field($params['token'] ?? '');
        $confirmation_word = sanitize_text_field($params['confirmation_word'] ?? '');

        if (!$token) {
            return new WP_Error('missing_token', __('Missing verification token.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        if (strtolower($confirmation_word) !== 'confirm') {
            return new WP_Error('invalid_confirmation', __('Confirmation word does not match. Please try again.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        $result = Customer::process_account_removal_request($token);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'message'      => $result['message'] ?? __('Your account has been successfully deleted.', HAVEN_CORE_TEXT_DOMAIN),
            'redirect_url' => $result['redirect_url'] ?? add_query_arg('acc_removal_completed', '1', home_url('/')),
        ]);
    }

    private function complete_registration(array $record, string $password)
    {
        $email    = $record['user_email'] ?? '';
        $username = $record['username'] ?? '';

        if (empty($email)) {
            return new WP_Error('missing_email', __('We could not find an email tied to this request.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        if (email_exists($email)) {
            return new WP_Error('account_exists', __('An account with this email already exists.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 400]);
        }

        if (!$username) {
            $username = sanitize_user(current_time('timestamp') . '_' . wp_generate_password(4, false));
        }

        if (username_exists($username)) {
            $username = sanitize_user($username . '_' . wp_generate_password(4, false));
        }

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, $username, $password);
        } else {
            $user_id = wp_create_user($username, $password, $email);
        }

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($user_id);
        } else {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        $meta_updates = $this->apply_registration_meta($user_id, $record['meta'] ?? []);

        return [
            'message'      => __('Your account has been activated. Welcome aboard!', HAVEN_CORE_TEXT_DOMAIN),
            'user_id'      => $user_id,
            'meta_updates' => $meta_updates,
        ];
    }

    private function complete_reset_flow(array $record, string $password)
    {
        $meta = $record['meta'] ?? [];
        $user_id = isset($meta['user_id']) ? absint($meta['user_id']) : 0;

        if (!$user_id && !empty($record['user_email'])) {
            $existing = get_user_by('email', $record['user_email']);
            $user_id = $existing ? (int) $existing->ID : 0;
        }

        if (!$user_id) {
            return new WP_Error('user_not_found', __('We could not locate an account tied to this link.', HAVEN_CORE_TEXT_DOMAIN), ['status' => 404]);
        }

        wp_set_password($password, $user_id);

        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($user_id);
        } else {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        return [
            'message' => __('Password updated successfully.', HAVEN_CORE_TEXT_DOMAIN),
            'user_id' => $user_id,
        ];
    }

    private function password_meets_requirements(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        return preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/\d/', $password);
    }

    private function apply_registration_meta(int $user_id, array $meta): array
    {
        if (empty($meta)) {
            return [];
        }

        $updates = [
            'billing_fields' => [],
            'orders_assigned' => [],
        ];

        $billing_source = [];

        if (!empty($meta['billing']) && is_array($meta['billing'])) {
            $billing_source = $meta['billing'];
        } elseif (!empty($meta['user_data']) && is_array($meta['user_data'])) {
            $billing_source = $meta['user_data'];
        }

        if ($billing_source) {
            $updates['billing_fields'] = $this->apply_billing_meta($user_id, $billing_source);
        }

        if (!empty($meta['order_ids'])) {
            $updates['orders_assigned'] = $this->assign_orders_to_user($user_id, $meta['order_ids']);
        }

        return array_filter($updates);
    }

    private function apply_billing_meta(int $user_id, array $billing_data): array
    {
        $fields_to_update = [
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone',
            'billing_email',
        ];

        $updated = [];

        foreach ($fields_to_update as $field) {
            if (!isset($billing_data[$field]) || $billing_data[$field] === '') {
                continue;
            }

            update_user_meta($user_id, $field, sanitize_text_field((string) $billing_data[$field]));
            $updated[] = $field;
        }

        if (!empty($billing_data['billing_first_name'])) {
            update_user_meta($user_id, 'first_name', sanitize_text_field((string) $billing_data['billing_first_name']));
            $updated[] = 'first_name';
        }

        if (!empty($billing_data['billing_last_name'])) {
            update_user_meta($user_id, 'last_name', sanitize_text_field((string) $billing_data['billing_last_name']));
            $updated[] = 'last_name';
        }

        return array_unique($updated);
    }

    private function assign_orders_to_user(int $user_id, $order_ids): array
    {
        if (empty($order_ids)) {
            return [];
        }

        if (is_string($order_ids)) {
            $decoded = json_decode($order_ids, true);
            if (is_array($decoded)) {
                $order_ids = $decoded;
            }
        }

        $order_ids = array_filter(array_map('absint', (array) $order_ids));

        if (empty($order_ids)) {
            return [];
        }

        $assigned = [];

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $order->set_customer_id($user_id);
            $order->save();
            $assigned[] = $order_id;
        }

        return $assigned;
    }
}
