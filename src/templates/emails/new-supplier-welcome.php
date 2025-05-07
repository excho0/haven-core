<?php
/**
 * Supplier Welcome Email Template
 *
 * This template is used for the initial welcome email sent to a newly added supplier.
 *
 * Variables available in this template:
 *
 * @var string $supplier_name Supplier's full name
 * @var string $email         Supplier's email address (login)
 * @var string $password      Supplier's temporary plaintext password
 */

// Get the logo ID from theme mod
$logo_id = get_theme_mod('custom_logo');

// Fallback to default logo if not set
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

$site_name = get_bloginfo('name');

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to <?php echo esc_html($site_name); ?></title>
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
                <h2 style="color: #111111; font-size: 22px; margin-top: 0;">Welcome to the Supplier Portal</h2>

                <p><strong>Hello <?php echo esc_html($supplier_name); ?>,</strong></p>

                <p>Thank you for joining the <?php echo esc_html($site_name); ?> supplier network. We’re excited to have you on board.</p>

                <!-- Login Credentials Card -->
                <div style="max-width: 400px; margin: 32px auto 0; position: relative; font-family: sans-serif; text-align: center;">

                    <!-- Floating Title -->
                    <div style="
                        position: absolute !important;
                        top: -14px !important;
                        left: 50% !important;
                        transform: translateX(-50%) !important;
                        background: #ffffff;
                        padding: 4px 12px;
                        font-size: 14px;
                        font-weight: 600;
                        color: #333;
                        border: 1px solid #ccc;
                        border-radius: 20px;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                    ">
                        Your Login Details
                    </div>

                    <!-- Card -->
                    <div style="
                        background: #f9f9f9;
                        border: 1px solid #ddd;
                        border-radius: 8px;
                        padding: 24px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
                        font-size: 15px;
                        color: #111;
                    ">
                        <p style="margin: 0 0 12px;">
                            <strong>Email:</strong><br>
                            <span style="color: #444;"><?php echo esc_html($email); ?></span>
                        </p>
                        <p style="margin: 0;">
                            <strong>Password:</strong><br>
                            <span style="color: #444;"><?php echo esc_html($password); ?></span>
                        </p>
                    </div>
                </div>

                <p>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="button">
                        <span>Log in Now</span>
                    </a>
                </p>

                <div style="margin-bottom: 22px; margin-top: 12px;">
                    <div style='border: 2px solid #0073e6; padding: 16px; border-radius: 8px; background-color: #f7faff;'>
                            <p style='margin: 0; font-size: 16px; color: #333;'>
                                After logging in for the first time, you will be prompted to set a new password for added security. We encourage you to choose a strong, unique password.
                            </p>
                        <p style='margin-top: 12px; font-size: 14px; color: #333;'>
                            If you have any questions or need assistance, our team is here to help. You can reach out to us at any time.
                        </p>
                    </div>
                </div>

                <p>We look forward to a successful partnership.</p>

                <p style="margin-top: 32px;">Best regards,<br>
                The <?php echo esc_html($site_name); ?> Team</p>
            </div>


            <!-- Footer -->
            <div class="email-footer">
                <p>&copy; <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html($site_name); ?></a>. All rights reserved.</p>
            </div>

        </div>
    </body>
</html>
