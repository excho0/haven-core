<?php
/**
 * Customer Payment Reminder Email Template
 *
 * @package WooCommerce\Templates\Emails
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = function_exists( 'FeaturesUtil::feature_is_enabled' ) && FeaturesUtil::feature_is_enabled( 'email_improvements' );

$logo_id   = get_theme_mod( 'custom_logo' );
$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id , 'full' ) : esc_url( get_stylesheet_directory_uri() . '/assets/img/default-logo.png' );

$site_name = get_bloginfo( 'name' );
$home_url  = home_url();
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html( $subject ); ?></title>
        <?php include HAVEN_CORE_EMAIL_STYLES_PATH; ?>
    </head>

    <body>
        <div class="email-container">

            <!-- Header Logo -->
            <div class="email-header">
                <a href="<?php echo esc_url( $home_url ); ?>">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
                </a>
            </div>

            <!-- Email Content -->
            <div class="email-content">

                <?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>

                <p><strong>Hi <?php echo esc_html( $order->get_billing_first_name() ); ?>,</strong></p>

                <p style="margin-bottom: 20px;">
                    Thank you for placing your order with <?php echo esc_html( $site_name ); ?>!
                </p>

                <p style="margin-bottom: 20px;">
                    When you're ready, you can complete your order using the secure link below.
                </p>

                <?php echo $email_improvements_enabled ? '</div>' : ''; ?>

                <!-- Payment Button -->
                <p style="text-align: center; margin: 20px 0;">
                    <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button">
                        <span>Complete Your Payment</span>
                    </a>
                </p>

                <!-- Order Summary (optional, brief) -->
                <p style="font-size: 28px; font-weight: bold; margin: 30px 0 20px;">
                    Order Summary
                </p>

                <?php 
                    echo wc_get_email_order_items( $order, [
                        'show_sku'      => false,
                        'show_image'    => true,
                        'image_size'    => [ 32, 32 ],
                        'plain_text'    => false,
                        'sent_to_admin' => false,
                        'include_download_links' => false,
                    ] );
                ?>

                <p style="margin-bottom: 20px; margin-top: 20px;">
                    Best regards,<br>
                    The <?php echo esc_html( $site_name ); ?> Team
                </p>

            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p>&copy; <a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( $site_name ); ?></a>. All rights reserved.</p>
            </div>

        </div>
    </body>
</html>
