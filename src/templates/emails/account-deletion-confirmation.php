<?php
/**
 * Account Deletion Confirmation Email Template
 *
 * @var string $user_display_name
 */

// Get logo ID from the Customizer
$logo_id = get_theme_mod('custom_logo');

// Get logo URL with fallback
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

// Support email (optional: move to theme settings or options page)
$support_email = 'support@' . parse_url(home_url(), PHP_URL_HOST);
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
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        </div>
        <div class="email-content">
            <h2><?php echo esc_html__('We’re Really Going to Miss You', 'woocommerce'); ?> 💔</h2>
            <img src="https://i.gifer.com/3b5Z.gif" style="max-width: 100%; height: auto; display: block; margin: 0 auto 20px;" frameBorder="0" allowFullScreen>
            
            <p><strong><?php echo sprintf(esc_html__('Dear %s,', 'woocommerce'), esc_html($user_display_name)); ?></strong></p>

            <p><?php echo esc_html__('As you take this final step away from the', 'woocommerce') . ' ' . esc_html(get_bloginfo('name')) . ', ' . esc_html__('we want to express our deepest gratitude for the time you’ve spent with us.', 'woocommerce'); ?></p>

            <p><strong><?php echo esc_html__('Your Decision is Complete:', 'woocommerce'); ?></strong><br>
            <?php echo esc_html__('We’ve processed your request, and your account has been permanently and securely removed from our systems.', 'woocommerce'); ?></p>

            <p><strong><?php echo esc_html__('Reflecting on Our Time Together:', 'woocommerce'); ?></strong><br>
            <?php echo esc_html__('Whether you were with us for a short time or a long while, your participation in our community meant a lot to us.', 'woocommerce'); ?></p>

            <p><strong><?php echo esc_html__('Our Doors Are Always Open:', 'woocommerce'); ?></strong><br>
            <?php echo esc_html__('Even though we’re parting ways for now, please remember that you’re always welcome to return.', 'woocommerce'); ?></p>

            <p><strong><?php echo esc_html__('Thank You for Everything:', 'woocommerce'); ?></strong><br>
            <?php echo esc_html__('We wish you all the best in your future endeavors.', 'woocommerce'); ?></p>

            <p><strong><?php echo esc_html__('We’re Here for You:', 'woocommerce'); ?></strong><br>
            <?php echo esc_html__('Should you have any questions or need assistance in the future, our support team is always available at', 'woocommerce'); ?>
            <a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a>.</p>

            <p><?php echo esc_html__('Farewell for now, and take care!', 'woocommerce'); ?></p>
        </div>
        <div class="email-footer">
            <p>&copy; <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a>. <?php echo esc_html__('All rights reserved.', 'woocommerce'); ?></p>
        </div>
    </div>
</body>
</html>
