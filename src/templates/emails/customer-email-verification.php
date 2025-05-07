<?php
/**
 * Customer Email Verification Template.
 *
 * Variables expected:
 * @var string $username
 * @var string $verification_link
 */

$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('Verify Your Email', 'woocommerce'); ?></title>
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
            <h2><?php echo esc_html__('Your Journey Begins Now', 'woocommerce'); ?> 🗻</h2>

            <p><strong><?php echo esc_html__('Hi', 'woocommerce') . ' ' . esc_html($username); ?>,</strong></p>

            <p>
                <?php
                echo sprintf(
                    esc_html__('Welcome to the start of your exciting journey with %s! 👋 We are thrilled to have you on board! 🎉', 'woocommerce'),
                    esc_html(get_bloginfo('name'))
                );
                ?>
            </p>

            <p>
                <?php
                echo wp_kses_post(__(
                    'To get started and unlock all the incredible features awaiting you, simply take the first step by clicking the button below. Then <strong>You will be required to set up your own password. This will verify and activate your account</strong>, ensuring you can dive into a world of exclusive offers, seamless shopping experiences, and more. 🚀',
                    'woocommerce'
                ));
                ?>
            </p>

            <p>
                <a href="<?php echo esc_url($verification_link); ?>" class="button">
                    <span><?php echo esc_html__('Get Started', 'woocommerce'); ?></span>
                </a>
            </p>

            <p><?php echo esc_html__('Alternatively, you can click on the link below:', 'woocommerce'); ?></p>

            <p>
                <a href="<?php echo esc_url($verification_link); ?>">
                    <?php echo esc_html($verification_link); ?>
                </a>
            </p>

            <p>
                <?php
                echo sprintf(
                    esc_html__('We can’t wait for you to explore everything %s has to offer. Get ready for an unforgettable shopping experience! 🛍️', 'woocommerce'),
                    esc_html(get_bloginfo('name'))
                );
                ?>
            </p>

            <p><?php echo esc_html__('Looking forward to having you onboard', 'woocommerce'); ?></p>
        </div>

        <div class="email-footer">
            <p>
                &copy; <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a>.
                <?php echo esc_html__('All rights reserved.', 'woocommerce'); ?>
            </p>
        </div>
    </div>
</body>
</html>
