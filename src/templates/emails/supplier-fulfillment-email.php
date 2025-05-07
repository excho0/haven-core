<?php
/**
 * Supplier Fulfillment Email Template
 *
 * @package WooCommerce\Templates\Emails
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

// Setup dynamic content.
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

        <p><strong><?php printf( esc_html__( 'Hi %s,'), esc_html( $supplier_name ) ); ?></strong></p>

        <p style="margin-bottom: 20px;">
            You have a new order assigned to you! 🎉
        </p>

        <p style="margin-bottom: 20px;">
            Please find below the list of products you need to fulfill:
        </p>

        <?php echo $email_improvements_enabled ? '</div>' : ''; ?>

        <!-- Products to Fulfill -->
        <p style="font-size: 28px; font-weight: bold; margin: 30px 0 20px;">
            Products to Fulfill
        </p>

        <?php


        // Dynamically filter the items list only to supplier products
        $allowed_product_ids = array_map(function($p) {
            return $p['product_id'];
        }, $products);

        $filtered_items = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (in_array($item->get_product_id(), $allowed_product_ids)) {
                $filtered_items[$item_id] = $item;
            }
        }

        // Now render real WooCommerce-style product table (NO prices)
        if ( ! empty( $filtered_items ) ) : ?>

        <table class="td" cellspacing="0" cellpadding="6" style="width:100%; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; border:1px solid #eee; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="text-align:left; border-bottom:2px solid #eee; padding:10px;">Product</th>
                    <th style="text-align:left; border-bottom:2px solid #eee; padding:10px;">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $filtered_items as $item_id => $item ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;">
                            <?php echo esc_html( $item->get_name() ); ?>
                            <?php
                            $item_meta = wc_display_item_meta( $item, [
                                'before'    => '<br><small style="color:#999;">',
                                'after'     => '</small>',
                                'separator' => '<br>',
                                'echo'      => false,
                            ] );
                            echo wp_kses_post( $item_meta );
                            ?>
                        </td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $item->get_quantity() ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


        <?php endif; ?>

        <!-- Shipping Address -->
        <p style="font-size: 28px; font-weight: bold; margin: 30px 0 20px;">
            Shipping Address
        </p>

        <table class="td" cellspacing="0" cellpadding="6" style="width:100%; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; border:1px solid #eee; margin-bottom: 20px;">
            <tbody>
                <?php if ( $order->get_shipping_first_name() || $order->get_shipping_last_name() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Name:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ( $order->get_shipping_address_1() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Address 1:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_address_1() ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ( $order->get_shipping_address_2() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Address 2:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_address_2() ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ( $order->get_shipping_city() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>City:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_city() ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $order->get_shipping_state() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>State/Province:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_state() ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $order->get_shipping_postcode() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Postcode:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_postcode() ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ( $order->get_shipping_country() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Country:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_shipping_country() ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $order->get_billing_phone() ) : ?>
                    <tr>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><strong>Phone:</strong></td>
                        <td style="text-align:left; border-bottom:1px solid #eee; padding:10px;"><?php echo esc_html( $order->get_billing_phone() ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


        <!-- Fulfillment Link -->
        <p style="font-size: 28px; font-weight: bold; margin: 30px 0 20px;">
            Fulfillment Link
        </p>

        <p style="margin-bottom: 20px;">
            Click the link below to access your fulfillment page and submit tracking details:
        </p>

        <p>
            <a href="<?php echo esc_url( $fulfillment_link ); ?>" class="button">
                <span>
                    Fulfill This Order
                </span>
            </a>
        </p>

    </div>

    <!-- Footer -->
    <div class="email-footer">
        <p>&copy; <a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( $site_name ); ?></a>. All rights reserved.</p>
    </div>

</div>

</body>
</html>
