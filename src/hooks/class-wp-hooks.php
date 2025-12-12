<?php

namespace HavenCore\Hooks;

use HavenCore\Services\EmailVerificationService;
use HavenCore\Settings\General;
use HavenCore\Settings\Notifications;

/**
 * Class WP_Hooks
 *
 * Registers hooks related to WordPress functionality.
 *
 * @package HavenCore\Hooks
 */
class WP_Hooks
{
    /**
     * Register all hooks related to password reset email and other WordPress functionality.
     *
     * @return void
     */
    public static function register(): void
    {
        if (
            !General::passwordResetPageEnabled() ||
            !Notifications::customerPasswordResetEmailEnabled()
        ) {
            return;
        }

        // Some plugins (e.g., WP Mail SMTP) short-circuit the core reset email by returning false.
        // Remove that override so we can inject our own HTML template + link.
        remove_filter('retrieve_password_message', '__return_false', PHP_INT_MAX);

        // Hook for customizing the password reset email content type to HTML
        add_filter('wp_mail_content_type', [self::class, 'setHtmlContentType']);

        // Hook for customizing the password reset email message
        add_filter('retrieve_password_message', [self::class, 'customPasswordResetEmailTemplate'], 10, 4);

        // Disable WooCommerce's default reset email and send our own with the custom link.
        add_filter('woocommerce_email_enabled_customer_reset_password', '__return_false');
        add_action('woocommerce_reset_password_notification', [self::class, 'sendWooPasswordResetEmail'], 0, 2);
    }

    /**
     * Set the content type for emails to HTML
     *
     * @param string $content_type
     * @return string
     */
    public static function setHtmlContentType($content_type)
    {
        return 'text/html'; // Set the content type to HTML
    }


    /**
     * Custom Password Reset Email Template
     *
     * Customizes the email content for password reset requests.
     *
     * @param string $message   The original email message.
     * @param string $key       The password reset key.
     * @param string $user_login The user's login (username).
     * @param object $user_data The user data object.
     *
     * @return string The modified email message.
     */
    public static function customPasswordResetEmailTemplate($message, $key, $user_login, $user_data)
    {
        $user_email = $user_data->user_email ?? '';
        $user_id    = isset($user_data->ID) ? (int) $user_data->ID : 0;

        $reset_link = self::build_reset_link($user_login, $user_email, $user_id, $key);

        return self::render_password_reset_email($user_login, $reset_link);
    }

    /**
     * Send our custom password reset email when WooCommerce triggers its notification.
     *
     * @param string $user_login
     * @param string $reset_key
     * @return void
     */
    public static function sendWooPasswordResetEmail($user_login, $reset_key): void
    {
        if (!Notifications::customerPasswordResetEmailEnabled()) {
            return;
        }

        $user = get_user_by('login', $user_login);
        if (!$user) {
            return;
        }

        $reset_link = self::build_reset_link(
            $user_login,
            $user->user_email ?? '',
            (int) $user->ID,
            $reset_key
        );

        $message = self::render_password_reset_email($user_login, $reset_link);

        if (empty($message)) {
            return;
        }

        $subject = esc_html__('Password Reset Request', HAVEN_CORE_TEXT_DOMAIN);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Generate the password reset link, falling back to the default WP link if needed.
     *
     * @param string $user_login
     * @param string $user_email
     * @param int    $user_id
     * @param string $fallback_key
     * @return string
     */
    private static function build_reset_link(string $user_login, string $user_email, int $user_id, string $fallback_key): string
    {
        $fallback = site_url("wp-login.php?action=rp&key={$fallback_key}&login=" . rawurlencode($user_login));

        if (empty($user_email)) {
            return $fallback;
        }

        $service = new EmailVerificationService();
        $request = $service->create_request([
            'username'    => sanitize_text_field($user_login),
            'user_email'  => sanitize_email($user_email),
            'context'     => 'reset',
            'meta'        => [
                'user_id'   => $user_id,
                'reset_key' => $fallback_key,
            ],
        ], HOUR_IN_SECONDS * 6);

        if (!$request || empty($request['token'])) {
            return $fallback;
        }

        return add_query_arg('token', rawurlencode($request['token']), home_url('/' . HAVEN_CORE_PASSWORD_RESET_SLUG . '/'));
    }

    /**
     * Render the password reset email HTML using our template.
     *
     * @param string $user_login
     * @param string $reset_link
     * @return string
     */
    private static function render_password_reset_email(string $user_login, string $reset_link): string
    {
        $user_login = sanitize_text_field($user_login);
        $reset_link = esc_url($reset_link);

        ob_start();
        include HAVEN_CORE_EMAIL_TEMPLATES_PATH . 'wp-forgot-passwd-email.php';
        return (string) ob_get_clean();
    }
}
