<?php
/**
 * Password Reset Email Template
 *
 * This template is used for the password reset email sent to a user.
 *
 * Variables available in this template:
 *
 * @var string $user_login  User's username
 * @var string $reset_link  The password reset link
 */

// Get the logo ID from theme mod
$logo_id = get_theme_mod('custom_logo');

// Fallback to default logo if not set
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

$site_name = get_bloginfo('name');

// Get the user object based on the reset request (you need to pass this as a variable)
$user = get_user_by('login', $user_login); // Fetch the user by their login name

// Check if the user is an admin
$is_admin = user_can($user, 'administrator'); // Check if the user requesting the reset is an admin

// Capture the remote IP address
$remote_ip = $_SERVER['REMOTE_ADDR']; // Get the IP address of the user who made the reset request

// Log the IP address if it's an admin user
if ($is_admin) {
    // Log this IP for admin-related activity (for security purposes)
    error_log('Admin password reset request from IP: ' . $remote_ip); // This will log the IP address to the server logs

    // Friendly, short message with a nice card around it for admins (escaped and translatable)
    $admin_ip_message = "
        <div style='border: 2px solid #0073e6; padding: 16px; border-radius: 8px; background-color: #f7faff;'>
            <p style='margin: 0; font-size: 16px; color: #333;'>
                <strong>" . esc_html__('Hey!', HAVEN_CORE_TEXT_DOMAIN) . "</strong> " . esc_html__('Just a quick heads-up: This request was made for your admin account from the following Remote IP: ', HAVEN_CORE_TEXT_DOMAIN) . "<strong>" . esc_html($remote_ip) . "</strong>.
            </p>
            <p style='margin-top: 12px; font-size: 14px; color: #333;'>
                " . esc_html__('If you didn’t request this, please investigate it further as a security precaution.', HAVEN_CORE_TEXT_DOMAIN) . "
            </p>
        </div>";
} else {
    // For regular users (escaped and translatable)
    $admin_ip_message = "
        <p style='margin-top: 12px; font-size: 14px; color: #333;'>
            " . esc_html__('If you did not request this password reset, please ignore this email or contact our support team.', HAVEN_CORE_TEXT_DOMAIN) . "
        </p>
    ";
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html__('Password Reset Request -', HAVEN_CORE_TEXT_DOMAIN) . ' ' . esc_html($site_name); ?></title>
        <?php include HAVEN_CORE_EMAIL_STYLES_PATH; ?>
    </head>
    <body>
        <div class="email-container">

            <!-- Header -->
            <div class="email-header">
                <a href="<?php echo esc_url(home_url()); ?>">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
                </a>
            </div>

            <!-- Body -->
            <div class="email-content">
                <h2 style="color: #111111; font-size: 22px; margin-top: 0;"><?php echo esc_html__('Password Reset Request', HAVEN_CORE_TEXT_DOMAIN); ?></h2>

                <p><strong><?php echo esc_html__('Hello', HAVEN_CORE_TEXT_DOMAIN) . ' ' . esc_html($user_login); ?>,</strong></p>

                <p><?php echo sprintf(
                    esc_html__('We’ve received a request to reset the password for your account at %s.', HAVEN_CORE_TEXT_DOMAIN),
                    esc_html($site_name)
                ); ?></p>

                <!-- Password Reset Button -->
                <p>
                    <a href="<?php echo esc_url($reset_link); ?>" class="button">
                        <span>
                            <?php esc_html_e('Click here to reset your password.', HAVEN_CORE_TEXT_DOMAIN); ?>
                        </span>
                    </a>
                </p>

                <!-- Admin IP Report -->
                <div style="margin-bottom: 22px; margin-top: 12px;">
                    <?php echo $admin_ip_message; ?>
                </div>
   
                <p><?php echo esc_html__('Best regards,', HAVEN_CORE_TEXT_DOMAIN); ?><br>
                <?php echo esc_html__('The', HAVEN_CORE_TEXT_DOMAIN) . ' ' . esc_html($site_name) . ' ' . esc_html__('Team', HAVEN_CORE_TEXT_DOMAIN); ?></p>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p>&copy; <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html($site_name); ?></a>. <?php echo esc_html__('All rights reserved.', HAVEN_CORE_TEXT_DOMAIN); ?></p>
            </div>

        </div>
    </body>
</html>
