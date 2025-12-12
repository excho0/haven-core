<?php

namespace HavenCore\WooCommerce\Hooks;

use HavenCore\Classes\HC_Settings;
use HavenCore\Services\EmailVerificationService;
use HavenCore\Utils\UserUtils;
use HavenCore\Settings\Notifications;
use HavenCore\Settings\WooCommerce as WCSettings;
use HavenCore\Settings\General;
use WP_Error;

defined('ABSPATH') || exit;

class Customer {

    /**
     * Cached settings instance.
     *
     * @var \HavenCore\Classes\HC_Settings|null
     */
    private static $settings_instance = null;

    /**
     * Retrieve a shared HC_Settings instance.
     *
     * @return HC_Settings
     */
    private static function settings(): HC_Settings
    {
        if (null === self::$settings_instance) {
            self::$settings_instance = new HC_Settings();
        }

        return self::$settings_instance;
    }

    /**
     * Register all customer-related hooks.
     *
     * @return void
     */
    public static function registerHooks(): void {
        $settings = self::settings();
        $verificationEmailsEnabled = Notifications::customerVerificationEmailEnabled();
        $accountRemovalEmailsEnabled = Notifications::customerAccountRemovalEmailEnabled();
        $passwordResetEmailsEnabled = Notifications::customerPasswordResetEmailEnabled();

        if (WCSettings::accountSecurityFlowEnabled() && $verificationEmailsEnabled) {
            add_filter('woocommerce_registration_errors', [self::class, 'handle_email_verification_flow'], 0, 3);
            add_filter('woocommerce_registration_auth_new_customer', '__return_false');

            // Disable WooCommerce's native "new account" emails so only our flow runs.
            add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');
            add_filter('woocommerce_email_enabled_customer_new_account_admin', '__return_false');
        }

        if (WCSettings::customerFarewellEnabled() && $accountRemovalEmailsEnabled) {

            // Registering the hook for displaying the delete account button and modal
            add_action('woocommerce_after_my_account', [self::class, 'woo_delete_account_button_with_modal']);
            
            // Registering the AJAX handler for account deletion
            add_action('wp_ajax_delete_account_request', [self::class, 'woo_handle_delete_account_request']);

            // Registering the AJAX handler for the final account removal.
            add_action('wp_ajax_confirm_account_removal', [self::class, 'woo_handle_account_removal']);

            // Hook the dialogs to the wp_footer 
            add_action('wp_footer', [self::class, 'display_conf_auth_req_for_acc_rm']);
            add_action('wp_footer', [self::class, 'display_conf_acc_removal_message']);

        }

        if ($verificationEmailsEnabled) {
            add_action('wp_footer', [self::class, 'display_conf_reg_email_sent']);
            add_action('wp_footer', [self::class, 'display_conf_activate_account']);
        }

        if ($passwordResetEmailsEnabled && General::passwordResetPageEnabled()) {
            add_action('wp_footer', [self::class, 'display_conf_passwd_reset_email_sent']);
            add_action('wp_footer', [self::class, 'display_conf_password_reset_completed']);
        }

        // Redirect to a custom page during checkout (before payment)
        if (WCSettings::orderReviewBeforePaymentEnabled()) {
            add_action('template_redirect', [self::class, 'redirectToPlaceOrderPage']);
        }
    }

    /**
     * Intercepts the WooCommerce registration flow to send a verification email and block native account creation.
     *
     * @param \WP_Error $errors
     * @param string    $username
     * @param string    $email
     * @return \WP_Error
     */
    public static function handle_email_verification_flow($errors, $username, $email)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) {
            return $errors;
        }

        if (empty($_POST) || !isset($_POST['register'])) {
            return $errors;
        }

        if ($errors instanceof WP_Error && $errors->get_error_code()) {
            return $errors;
        }

        if (!is_email($email) || email_exists($email) || username_exists($username)) {
            return $errors;
        }

        $service = new EmailVerificationService();
        $payload = [
            'username'   => sanitize_text_field($username),
            'user_email' => sanitize_email($email),
            'context'    => 'registration',
            'meta'       => self::collect_registration_meta($_POST ?? []),
        ];

        /**
         * Allow customization of the email verification payload before persistence.
         *
         * @param array $payload Base payload (username, user_email, context, meta).
         * @param array $raw_post Original $_POST data when available.
         */
        $payload = apply_filters('haven_core_email_verification_payload', $payload, $_POST ?? []);

        $request = $service->create_request($payload, DAY_IN_SECONDS);

        if (!$request || empty($request['token'])) {
            if ($errors instanceof WP_Error) {
                $errors->add('haven-core-email-verification', __('Unable to generate verification token. Please try again later.', HAVEN_CORE_TEXT_DOMAIN));
            }
            return $errors;
        }

        $verification_link = add_query_arg('token', $request['token'], home_url('/' . HAVEN_CORE_PASSWORD_RESET_SLUG . '/'));

        $email_sent = self::send_verification_email($email, $username, $verification_link);

        if (!$email_sent) {
            if ($errors instanceof WP_Error) {
                $errors->add('haven-core-email-verification', __('We could not send the verification email. Please try again later.', HAVEN_CORE_TEXT_DOMAIN));
            }
            return $errors;
        }

        /**
         * Allow custom redirect URL after the verification email has been issued.
         *
         * @param string $redirect_url
         */
        $redirect_url = apply_filters(
            'haven_core_email_verification_redirect_url',
            home_url('/?conf_reg_email_sent=1')
        );

        wp_safe_redirect($redirect_url);
        exit;

        return $errors;
    }

    /**
     * Collect billing metadata provided during registration to attach to the verification payload.
     *
     * @param array $post_data
     * @return array
     */
    private static function collect_registration_meta(array $post_data): array
    {
        if (empty($post_data)) {
            return [];
        }

        $billing_fields = [
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

        $billing_meta = [];
        foreach ($billing_fields as $field) {
            if (isset($post_data[$field]) && $post_data[$field] !== '') {
                $billing_meta[$field] = sanitize_text_field(wp_unslash($post_data[$field]));
            }
        }

        $meta = [];
        if (!empty($billing_meta)) {
            $meta['billing'] = $billing_meta;
        }

        return $meta;
    }

    /**
     * Renders and sends the verification email.
     *
     * @param string $recipient
     * @param string $username
     * @param string $verification_link
     * @return bool
     */
    public static function send_verification_email(string $recipient, string $username, string $verification_link): bool
    {
        if (!Notifications::customerVerificationEmailEnabled()) {
            return false;
        }

        $template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'customer-email-verification.php';

        if (!is_readable($template_path)) {
            error_log(PLUGIN_NAME . ": Email template missing at {$template_path}");
            return false;
        }

        $username = sanitize_text_field($username);
        $verification_link = esc_url_raw($verification_link);

        ob_start();
        include $template_path;
        $message = ob_get_clean();

        if (empty($message)) {
            return false;
        }

        $subject = esc_html__('Your Journey Begins Now', 'woocommerce');
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($recipient, $subject, $message, $headers);
    }

    /**
     * Generates a modal with customizable content, buttons, and behavior.
     *
     * This function generates a modal with customizable HTML content, buttons, and behavior. It supports the option to display 
     * either a single "OK" button or both "Confirm" and "Cancel" buttons. Additionally, the modal can perform AJAX requests, 
     * during which buttons are disabled and a loader is shown. You can also provide inline styling for the modal content 
     * and control whether the modal is shown immediately when the page loads.
     *
     * @param string $modal_id The unique ID for the modal element.
     * @param string $modal_title The title of the modal, displayed as a heading.
     * @param string $modal_html_content The HTML content inside the modal body, which can include custom HTML tags and inline styles.
     * @param string $button_text The text for the button inside the modal (e.g., "OK", "Confirm").
     * @param string $button_id The unique ID for the button inside the modal.
     * @param bool $has_cancel (optional) Whether to display the "Cancel" button. Default is true. If false, only the "Confirm" button will be shown.
     * @param bool $is_ajax (optional) Whether the modal performs an AJAX request. Default is false. If true, the modal will disable the buttons 
     *                       and show a loader during the AJAX request.
     * @param bool $visible (optional) Whether to show the modal immediately when the page loads. Default is false.
     * 
     * @return void
     */
    public static function generate_modal(
        string $modal_id, 
        string $modal_title, 
        string $modal_html_content, 
        string $button_text, 
        string $button_id, 
        bool $has_cancel = true,  // Option to have a Cancel button
        bool $is_ajax = false,   // Option to make AJAX request or just informational
        bool $visible = false   // Option to make the modal visible immediately
    ): void {
        ?>
        <!-- Modal Structure -->
        <div id="<?= esc_attr($modal_id); ?>" class="modal" style="display: none;">
            <div class="modal-content">
                <h2><?= esc_html($modal_title); ?></h2>

                <?= wp_kses_post($modal_html_content); ?>

                <div style="justify-content: center; display: flex; gap: 10px;">
                    <button id="<?= esc_attr($button_id); ?>" class="modal-btn" 
                        <?= $is_ajax ? 'disabled' : ''; ?> >
                        <span id="button-text"><?= esc_html($button_text); ?></span>
                        <l-dot-pulse id="loading-loader" size="30" speed="1.3" color="white" style="display: none;"></l-dot-pulse>
                    </button>
                    <?php if ($has_cancel): ?>
                        <button id="cancel-<?= esc_attr($modal_id); ?>" class="modal-btn-sec" disabled>
                            <?php esc_html_e('Cancel', HAVEN_CORE_TEXT_DOMAIN); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script type="module" src="https://cdn.jsdelivr.net/npm/ldrs/dist/auto/dotPulse.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('<?= esc_attr($modal_id); ?>');
                var confirmButton = document.getElementById('<?= esc_attr($button_id); ?>');
                var cancelButton = document.getElementById('cancel-<?= esc_attr($modal_id); ?>');
                var loader = document.getElementById('loading-loader'); // Spinner element
                var buttonText = document.getElementById('button-text');

                // Ensure PHP variables are passed into JavaScript properly
                var isAjax = <?= $is_ajax ? 'true' : 'false'; ?>; // Convert PHP boolean to JS boolean
                var visible = <?= $visible ? 'true' : 'false'; ?>; // Convert PHP boolean to JS boolean

                // Function to show modal with fade-in effect
                function showModal() {
                    modal.style.display = 'block'; // Make sure modal is visible immediately
                    modal.classList.add('show'); // Trigger fade-in effect
                    confirmButton.disabled = false; // Enable button when modal is shown
                    confirmButton.classList.remove('disabled'); // Remove disabled class
                    if (cancelButton) {
                        cancelButton.disabled = false; // Enable cancel button when modal is shown
                        cancelButton.classList.remove('disabled'); // Remove disabled class from cancel button
                    }
                }

                // Function to hide modal with fade-out effect
                function hideModal() {
                    modal.classList.add('hide');
                    modal.classList.remove('show');
                    setTimeout(function() {
                        modal.style.display = 'none'; // Ensure modal is hidden after transition
                        modal.classList.remove('hide');
                    }, 300); // Match this timeout to the duration of the transition
                }

                // Show modal immediately if $visible is true
                if (visible) {
                    showModal();
                }

                // Ensure the confirmButton exists before adding event listener
                if (confirmButton) {
                    confirmButton.addEventListener('click', function() {
                        if (isAjax) {
                            // Disable the confirm and cancel buttons and show loader/spinner
                            confirmButton.disabled = true;
                            confirmButton.classList.add('disabled');
                            if (cancelButton) {
                                cancelButton.disabled = true; // Disable cancel button as well
                                cancelButton.classList.add('disabled'); // Add disabled class for cancel button
                            }
                            loader.style.display = 'flex';  // Show the loader
                            buttonText.style.display = 'none'; // Hide button text

                            // Your fetch request
                            fetch('<?= admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'action=delete_account_request'
                            })
                            .then(response => response.json())  // Parse the JSON response
                            .then(data => {
                                if (data.success) {
                                    // Redirect after successful response
                                    setTimeout(function() {
                                        window.location.href = '<?= home_url(); ?>/?conf-auth-req-for-acc-rm=1';
                                    }, 0);
                                } else {
                                    alert('Failed to initiate account deletion. Please try again later.');
                                    hideModal(); // Hide modal after alert
                                }
                            })
                            .catch(error => {
                                alert('Error: ' + error.message);
                                hideModal(); // Hide modal after alert
                            });
                        } else {
                            // Clear the URL by replacing current state without 'acc_removal_completed' parameter
                            window.history.replaceState({}, document.title, window.location.pathname);

                            hideModal();  // Just close the modal if no AJAX request
                        }
                    });
                }

                // Event listener for clicking cancel button
                if (cancelButton) {
                    cancelButton.addEventListener('click', function() {
                        hideModal();
                    });
                }

                // Event listener for clicking the delete account button
                var deleteAccountButton = document.getElementById('delete-account-button');
                if (deleteAccountButton) {
                    deleteAccountButton.addEventListener('click', function(event) {
                        event.preventDefault();  // Prevent default button behavior
                        modal.style.display = 'block';
                        setTimeout(showModal, 10); // Delay to ensure display:block takes effect
                    });
                }
            });


        </script>
        <?php
    }



    /**
     * Display the delete account button and modal on the WooCommerce account page.
     *
     * @return void
     */
    public static function woo_delete_account_button_with_modal(): void {
        ?>
        <!-- Delete Account Button -->
        <button href="#" id="delete-account-button" class="button"><?php esc_html_e('Delete Account', HAVEN_CORE_TEXT_DOMAIN); ?></button>
        <?php
        // Call to generate the modal with the desired parameters
        self::generate_modal(
            'delete-account-modal',                                    // Modal ID
            esc_html__('Authentication Required', HAVEN_CORE_TEXT_DOMAIN),         // Modal Title
            '<p style="font-size: 16px; color: #333;">' . 
                esc_html__('In order to proceed, please click on the button below and we’ll send you an email with all the details you need to confirm your request.', HAVEN_CORE_TEXT_DOMAIN) . 
            '</p>',  // Modal content
            esc_html__('Confirm', HAVEN_CORE_TEXT_DOMAIN),                         // Button Text
            'confirm-delete-account',                                    // Button ID
            true,                                                        // has_cancel (Show Cancel Button)
            true,                                                        // is_ajax (Perform AJAX Request)
            false                                                       // Don't show the modal immediately
        );

    }
    /**
     * Handles the AJAX request for deleting the account.
     *
     * @return void
     */
    public static function woo_handle_delete_account_request() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in.');
            return;
        }

        if (!Notifications::customerAccountRemovalEmailEnabled()) {
            wp_send_json_error(__('Account removal emails are disabled.', HAVEN_CORE_TEXT_DOMAIN));
            return;
        }

        $user = wp_get_current_user();
        $user_email = $user->user_email;
        $user_display_name = $user->display_name;

        if (empty($user_email)) {
            wp_send_json_error('User email not found.');
            return;
        }

        // Use UserUtils class to handle the token storage and generation
        $prefix = 'account_removal'; // A prefix to identify this type of token
        $expiration = 86400; // 24 hours expiration time (in seconds)

        // Store the token and get the generated token
        $token = UserUtils::store_verification_token($prefix, $user_email, $expiration);

        if (!$token) {
            wp_send_json_error('Failed to store the token.');
            return;
        }

        // Send verification email with the generated token
        $verification_link = home_url("/goodbye/?token=$token");

        $subject = esc_html__('Ready to Say Goodbye?', HAVEN_CORE_TEXT_DOMAIN);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Prepare the email template path
        $template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH . '/account-removal-confirmation.php';

        if (!file_exists($template_path)) {
            wp_send_json_error('Email template not found.');
            return;
        }

        // ✅ Explicitly pass and sanitize variables for the template
        $user_display_name = sanitize_text_field($user_display_name);
        $verification_link = esc_url($verification_link);

        // ✅ Output buffering to capture the email template output
        ob_start();
        include $template_path; // Template uses $user_display_name and $verification_link
        $message = ob_get_clean();

        // ✅ Send the email
        $mail_sent = wp_mail($user_email, $subject, $message, $headers);

        if ($mail_sent) {
            wp_send_json_success(array(
                'message' => 'Token has been created and email sent.',
                'token' => $token,
                'expire_date' => date('Y-m-d H:i:s', strtotime('+24 hours'))
            ));
        } else {
            wp_send_json_error('Failed to send confirmation email.');
        }
    }


    /**
     * Handles the AJAX request for the final customer removal action.
     *
     * @return void
     */
    public static function woo_handle_account_removal() {
        // Check for nonce validity
        if (!isset($_POST['account_removal_nonce']) || !wp_verify_nonce($_POST['account_removal_nonce'], 'confirm_account_removal')) {
            wp_send_json_error(array('message' => __('Invalid nonce.', HAVEN_CORE_TEXT_DOMAIN)));
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $confirmation_word = isset($_POST['confirmation_word']) ? sanitize_text_field(wp_unslash($_POST['confirmation_word'])) : '';

        if (strtolower($confirmation_word) !== 'confirm') {
            wp_send_json_error(array('message' => __('Confirmation word does not match. Please try again.', HAVEN_CORE_TEXT_DOMAIN)));
        }

        if (!$token) {
            wp_send_json_error(array('message' => __('Missing token.', HAVEN_CORE_TEXT_DOMAIN)));
        }

        $result = self::process_account_removal_request($token);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Shared account removal flow for AJAX and REST.
     *
     * @param string $token
     * @return array|\WP_Error
     */
    public static function process_account_removal_request(string $token)
    {
        $user = wp_get_current_user();

        if (!$user || !$user->ID) {
            return new WP_Error('user_not_found', __('User not found.', HAVEN_CORE_TEXT_DOMAIN));
        }

        $prefix = 'account_removal';

        if (!UserUtils::validate_verification_token($prefix, $user->user_email, $token)) {
            return new WP_Error('invalid_token', __('Invalid token.', HAVEN_CORE_TEXT_DOMAIN));
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return new WP_Error('not_allowed', __('Administrators cannot remove their accounts.', HAVEN_CORE_TEXT_DOMAIN));
        }

        if (class_exists('WC_Order')) {
            $orders = wc_get_orders(array(
                'customer' => $user->ID,
                'status'   => array('pending', 'processing', 'on-hold', 'failed', 'cancelled'),
                'limit'    => -1,
            ));

            if (!empty($orders)) {
                return new WP_Error('pending_orders', __('Please complete or cancel any pending orders before deleting your account.', HAVEN_CORE_TEXT_DOMAIN));
            }
        }

        $user_meta_keys = array_keys(get_user_meta($user->ID));
        $all_user_meta_deleted = true;

        foreach ($user_meta_keys as $meta_key) {
            if (!delete_user_meta($user->ID, $meta_key)) {
                $all_user_meta_deleted = false;
            }
        }

        $user_email        = $user->user_email;
        $user_display_name = $user->display_name;

        if (!function_exists('wp_delete_user') || !wp_delete_user($user->ID)) {
            return new WP_Error('deletion_failed', __('Unable to delete user account.', HAVEN_CORE_TEXT_DOMAIN));
        }

        wp_logout();

        UserUtils::delete_verification_token($prefix, $user_email);

        $mail_sent = true;

        if (Notifications::customerAccountRemovalEmailEnabled()) {
            $subject = __('We Are Really Going To Miss You', HAVEN_CORE_TEXT_DOMAIN);
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $template_path = HAVEN_CORE_EMAIL_TEMPLATES_PATH . '/account-deletion-confirmation.php';

            if (!is_readable($template_path)) {
                return new WP_Error('template_missing', __('Email template not found.', HAVEN_CORE_TEXT_DOMAIN));
            }

            $user_display_name = sanitize_text_field($user_display_name);

            ob_start();
            include $template_path;
            $message = ob_get_clean();

            $mail_sent = wp_mail($user_email, $subject, $message, $headers);
        }

        if (!$mail_sent || !$all_user_meta_deleted) {
            return new WP_Error('account_cleanup_failed', __('There was an issue processing your request.', HAVEN_CORE_TEXT_DOMAIN));
        }

        return array(
            'message'       => __('Your account has been successfully deleted.', HAVEN_CORE_TEXT_DOMAIN),
            'redirect_url'  => add_query_arg('acc_removal_completed', '1', home_url('/')),
        );
    }

    /**
     * Display confirmation modal after registration email has been sent.
     *
     * @return void
     */
    public static function display_conf_reg_email_sent(): void
    {
        if (!Notifications::customerVerificationEmailEnabled()) {
            return;
        }

        if (!isset($_GET['conf_reg_email_sent']) || '1' !== wp_unslash($_GET['conf_reg_email_sent'])) {
            return;
        }

        $modal_content  = '<p>' . esc_html__('An email has been sent to you to confirm your account.', HAVEN_CORE_TEXT_DOMAIN) . '</p>';
        $modal_content .= '<p><strong style="color: red;">' . esc_html__('Make sure to check your inbox and spam folders to ensure you don\'t miss it.', HAVEN_CORE_TEXT_DOMAIN) . '</strong></p>';

        self::generate_modal(
            'conf-reg-email-sent-modal',
            esc_html__('Attention', HAVEN_CORE_TEXT_DOMAIN),
            $modal_content,
            esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),
            'confirm-conf-reg-email-sent',
            false,
            false,
            true
        );
    }

    /**
     * Display confirmation modal after password reset email has been sent.
     *
     * @return void
     */
    public static function display_conf_passwd_reset_email_sent(): void
    {
        if (!Notifications::customerPasswordResetEmailEnabled()) {
            return;
        }

        if (!isset($_GET['conf_passwd_reset_email_sent']) || '1' !== wp_unslash($_GET['conf_passwd_reset_email_sent'])) {
            return;
        }

        $email = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';
        $user  = isset($_GET['user']) ? sanitize_text_field(wp_unslash($_GET['user'])) : '';

        if ($user && !$email) {
            $user_data = get_user_by('login', $user);
            if ($user_data && !empty($user_data->user_email)) {
                $email = sanitize_email($user_data->user_email);
            }
        }

        if ($email) {
            $intro_message = sprintf(
                '%s <strong style="color:#0009ff;">%s</strong> %s',
                esc_html__('An email has been sent to', HAVEN_CORE_TEXT_DOMAIN),
                esc_html($email),
                esc_html__('with instructions to reset your password.', HAVEN_CORE_TEXT_DOMAIN)
            );
        } else {
            $intro_message = esc_html__('An email has been sent to you with instructions to reset your password.', HAVEN_CORE_TEXT_DOMAIN);
        }

        $modal_content  = '<p>' . $intro_message . '</p>';
        $modal_content .= '<p><strong style="color: red;">' . esc_html__('Make sure to check your inbox and spam folders to ensure you don\'t miss it.', HAVEN_CORE_TEXT_DOMAIN) . '</strong></p>';

        self::generate_modal(
            'conf-passwd-reset-email-sent-modal',
            esc_html__('Attention', HAVEN_CORE_TEXT_DOMAIN),
            $modal_content,
            esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),
            'confirm-conf-passwd-reset-email-sent',
            false,
            false,
            true
        );
    }

    /**
     * Display welcome modal once the account activation completes.
     *
     * @return void
     */
    public static function display_conf_activate_account(): void
    {
        if (!Notifications::customerVerificationEmailEnabled()) {
            return;
        }

        if (!isset($_GET['conf_activate_account']) || '1' !== wp_unslash($_GET['conf_activate_account'])) {
            return;
        }

        $modal_content  = '<p>' . esc_html__('Congratulations! Your account has been successfully created.', HAVEN_CORE_TEXT_DOMAIN) . '</p>';

        self::generate_modal(
            'conf-activate-account-modal',
            esc_html__('Welcome', HAVEN_CORE_TEXT_DOMAIN),
            $modal_content,
            esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),
            'conf-activate-account-ok',
            false,
            false,
            true
        );
    }

    /**
     * Display confirmation modal after password reset completion.
     *
     * @return void
     */
    public static function display_conf_password_reset_completed(): void
    {
        if (!Notifications::customerPasswordResetEmailEnabled()) {
            return;
        }

        if (!isset($_GET['password_reset']) || 'success' !== wp_unslash($_GET['password_reset'])) {
            return;
        }

        $modal_content  = '<p>' . esc_html__('Your password has been updated successfully.', HAVEN_CORE_TEXT_DOMAIN) . '</p>';
        $modal_content .= '<p><strong style="color: #5200FF;">' . esc_html__('You can now continue browsing securely.', HAVEN_CORE_TEXT_DOMAIN) . '</strong></p>';

        self::generate_modal(
            'conf-password-reset-modal',
            esc_html__('Password Updated', HAVEN_CORE_TEXT_DOMAIN),
            $modal_content,
            esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),
            'conf-password-reset-ok',
            false,
            false,
            true
        );
    }

    /**
     * Displays the confirmation message for authentication required for account removal.
     *
     * This function generates the content and structure for a modal or message that 
     * prompts the user to authenticate in order to proceed with the account removal 
     * process. It is generally used after a user has requested to delete their account, 
     * and ensures that they confirm their action through authentication.
     *
     * @return void
     */
    public static function display_conf_auth_req_for_acc_rm() {
        // Check if the URL parameter 'conf-auth-req-for-acc-rm' is set and equals '1'
        if (isset($_GET['conf-auth-req-for-acc-rm']) && $_GET['conf-auth-req-for-acc-rm'] == '1') {
            // Generate the modal using the generate_modal function
            self::generate_modal(
                'auth-req-modal',                                   // Modal ID
                esc_html__('Attention', HAVEN_CORE_TEXT_DOMAIN),              // Modal Title
                '<p style="font-size: 16px; color: #333;">' . 
                    esc_html__('An email has been sent to you to confirm your request.', HAVEN_CORE_TEXT_DOMAIN) . 
                '</p>' . 
                '<p style="font-size: 16px; color: #333;">' . 
                    '<strong style="color: red;">' . 
                        esc_html__('Make sure to check your inbox and spam folders to ensure you don\'t miss it.', HAVEN_CORE_TEXT_DOMAIN) . 
                    '</strong>' . 
                '</p>', // Modal content
                esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),                     // Button Text
                'auth-req-ok',                                      // Button ID
                false,                                              // No Cancel button
                false,                                               // No AJAX request (informational modal)
                'OK',                                              // Close button text
                true                                               // Automatically show modal
            );
        }
    }


    /**
     * Displays a confirmation message that the account has been successfully removed.
     *
     * This function generates a message informing the user that their account removal 
     * request has been successfully processed and that their account has been deleted.
     *
     * @return void
     */
    public static function display_conf_acc_removal_message() {
        // Check if the query parameter is set and equals '1'
        if (isset($_GET['acc_removal_completed']) && $_GET['acc_removal_completed'] == '1') {
            // Generate the modal using the generate_modal function
            self::generate_modal(
                'acc-removal-modal',                                      // Modal ID
                esc_html__('We’re Really Going to Miss You!', HAVEN_CORE_TEXT_DOMAIN), // Modal Title
                '<p>' . 
                    esc_html__('Your account has been successfully removed.', HAVEN_CORE_TEXT_DOMAIN) . 
                '</p>' . 
                '<p>' . 
                    '<strong style="color: #5200FF;">' . 
                        esc_html__('We’re so sorry to see you go. If you ever need help or just want to share your thoughts, our support team is here for you.', HAVEN_CORE_TEXT_DOMAIN) . 
                    '</strong>' . 
                '</p>', // Modal content
                esc_html__('OK', HAVEN_CORE_TEXT_DOMAIN),                          // Button Text
                'close-acc-removal-modal',                               // Button ID
                false,                                                   // No Cancel button
                false,                                                   // No AJAX request (informational modal)
                'OK',                                                    // Close button text
                true                                                     // Automatically show modal
            );
        }
    }

    /**
     * Redirect users to a custom place order page.
     * This can be triggered conditionally, for example, based on settings or other criteria.
     *
     * @return void
     */
    public static function redirectToPlaceOrderPage(): void
    {
        // Prevent redirect loop
        if (is_page('place-order') || is_wc_endpoint_url()) {
            return;
        }

        // Redirect only when reaching the main checkout page (not AJAX or endpoints like 'order-pay')
        if (is_checkout() && !is_ajax()) {
            wp_safe_redirect(home_url('/place-order'), 301); // or 302 if temporary
            exit;
        }
    }
}
