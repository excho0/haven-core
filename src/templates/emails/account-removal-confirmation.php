<?php
/**
 * Account Removal Confirmation Email Template
 *
 * Available variables:
 * @var string $user_display_name
 * @var string $verification_link
 */
// Get the logo ID from theme mod
$logo_id = get_theme_mod('custom_logo');

// Get full-size logo URL (Customizer logo)
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');
// OR if you upload it to your theme:
// $logo_url = esc_url(get_stylesheet_directory_uri() . '/assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('Account Deletion Confirmation', 'woocommerce'); ?></title>
    <?php include HAVEN_CORE_EMAIL_STYLES_PATH; ?>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <a href="<?php echo esc_url(home_url()); ?>">
                <img src="<?php echo $logo_url; ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        </div>
        <div class="email-content">
            <h2><?php echo esc_html__('Ready to Say Goodbye?', 'woocommerce'); ?> 👋</h2>
            <p><strong><?php echo sprintf(esc_html__('Hi %s,', 'woocommerce'), esc_html($user_display_name)); ?></strong></p>
            <p><?php echo esc_html__('We are sorry to see you go, but we are here to help with whatever you need.', 'woocommerce'); ?></p>
            <p><?php echo sprintf(esc_html__('We have received your request to delete your account on %s. If this is truly your decision, clicking the button below will take you to the final step of this journey.', 'woocommerce'), esc_html(get_bloginfo('name'))); ?></p>
            <p>
                <a href="<?php echo esc_url($verification_link); ?>" class="button">
                    <span>
                        <?php echo esc_html__('Take Me To The Final Step', 'woocommerce'); ?>
                    </span>
                </a>
            </p>
            <p><?php echo esc_html__('Alternatively, you can click on the link below:', 'woocommerce'); ?></p>
            <p><a href="<?php echo esc_url($verification_link); ?>"><?php echo esc_html($verification_link); ?></a></p>
            <p><?php echo esc_html__('If you did not request this, do not worry — just ignore this email.', 'woocommerce'); ?></p>
        </div>
        <div class="email-footer">
            <p>&copy; <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a>. <?php echo esc_html__('All rights reserved.', 'woocommerce'); ?></p>
        </div>
    </div>
</body>
</html>
