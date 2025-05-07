<?php
/**
 * Customer Tracking Update Email Template
 *
 * Variables expected:
 * - $customer_name (string)
 * - $order_id (int)
 */

defined('ABSPATH') || exit;

$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id 
    ? wp_get_attachment_image_url($logo_id, 'full') 
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

// Get specific view order link
$order_view_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

// Load the order
$order = wc_get_order($order_id);
if (!$order) {
    echo '<p>Order not found.</p>';
    return;
}

// Load supplier data from the order
$supplier_data = get_post_meta($order_id, '_supplier_data', true);

// Initialize
$products_tracking = [];
$all_fulfilled = true;

// Loop through suppliers
if (!empty($supplier_data)) {
    foreach ($supplier_data as $supplier_id => $supplier_info) {
        // Grouped Products (fulfilled)
        if (!empty($supplier_info['grouped_products'])) {
            foreach ($supplier_info['grouped_products'] as $tracking_number => $products) {
                foreach ($products as $product_id => $product_data) {
                    foreach ($order->get_items() as $item) {
                        if ($item instanceof WC_Order_Item_Product && $item->get_product_id() == $product_id) {
                            $product_name = $item->get_name();
                            $product_quantity = $item->get_quantity();
                            $product_image = get_the_post_thumbnail_url($product_id, 'thumbnail');
                            $product_link = get_permalink($product_id);

                            $products_tracking[] = [
                                'product_id'      => $product_id,
                                'product_name'    => $product_name,
                                'tracking_number' => $tracking_number,
                                'image_url'       => $product_image ?: '',
                                'product_link'    => $product_link ?: '#',
                                'quantity'        => $product_quantity,
                            ];
                            break;
                        }
                    }
                }
            }
        }


        // Ungrouped Products (pending)
        if (!empty($supplier_info['ungrouped_products'])) {
            foreach ($supplier_info['ungrouped_products'] as $product) {
                $product_id = $product['product_id'];
                foreach ($order->get_items() as $item) {
                    if ($item instanceof WC_Order_Item_Product && $item->get_product_id() == $product_id) {
                        $product_name = $item->get_name();
                        $product_quantity = $item->get_quantity();
                        $product_image = get_the_post_thumbnail_url($product_id, 'thumbnail');
                        $product_link = get_permalink($product_id);

                        $products_tracking[] = [
                            'product_id'      => $product_id,
                            'product_name'    => $product_name,
                            'tracking_number' => '',
                            'image_url'       => $product_image ?: '',
                            'product_link'    => $product_link ?: '#',
                            'quantity'        => $product_quantity,
                        ];

                        $all_fulfilled = false; // Not fully fulfilled if ungrouped exists
                    }
                }
            }
        }
    }
}

// Group products by tracking number for rendering
$grouped_tracking = [];
foreach ($products_tracking as $product) {
    $tracking_number = $product['tracking_number'];
    if (!isset($grouped_tracking[$tracking_number])) {
        $grouped_tracking[$tracking_number] = [];
    }
    $grouped_tracking[$tracking_number][] = $product;
}
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('Your Order Has Shipped!', HAVEN_CORE_TEXT_DOMAIN); ?></title>
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
            <h2>Your Order is on the Way! 🚚</h2>

            <div style="display: flex; flex-direction: column; gap: 15px; align-items: center;">
                <p><strong><?php echo 'Hi' . ' ' . esc_html($customer_name); ?>,</strong></p>

                <?php if (!$all_fulfilled): ?>
                    <!-- Notify customer about partial shipment -->
                    <p class="partially-shipped">
                        Some items are still being processed and will ship soon. We’ll update you again once everything is on its way! 🚚
                    </p>
                <?php else : ?>
                    <p class="fully-shipped">
                        We're excited to let you know that your order #<?php echo intval($order_id); ?> has been <strong>fully</strong> shipped! 🎉
                    </p>
                <?php endif; ?>

                <p style="font-size: 16px; color: #374151; text-align: center;">
                    Below are the details about your order.
                </p>
            </div>


            <!-- Grouped Products -->
            <?php foreach ($grouped_tracking as $tracking_number => $products_group): ?>
                <?php $is_fulfilled = !empty($tracking_number); ?>
                <div class="tracking-card">
                    <!-- Tracking Header -->
                    <div class="tracking-header">
                        <span class="tracking-number">
                            <?php if ($is_fulfilled): ?>
                                Tracking Number: <?php echo esc_html($tracking_number); ?>
                            <?php else: ?>
                                Tracking Not Available
                            <?php endif; ?>
                        </span>
                        <?php if ($is_fulfilled): ?>
                            <a href="#" class="button-track">Track Package</a>
                        <?php endif; ?>
                    </div>

                    <!-- Product List -->
                    <div class="product-list">
                        <?php foreach ($products_group as $product): ?>
                            <div class="product-item">
                                <?php if ($product['image_url'] !== 'No image available'): ?>
                                    <img class="product-image" src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['product_name']); ?>">
                                <?php endif; ?>

                                <div class="product-info">
                                    <a href="<?php echo esc_url($product['product_link']); ?>" class="product-name">
                                        <?php echo esc_html($product['product_name']); ?>
                                    </a>
                                    <div class="product-quantity">
                                        Quantity: <?php echo intval($product['quantity']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>



            <p style="text-align: center;">
                <a href="<?php echo esc_url($order_view_url); ?>" class="button">
                    <span>View Your Order</span>
                </a>
            </p>

            <p>Thanks again for shopping with us — we can’t wait for you to receive your order!</p>

        </div>

        <div class="email-footer">
            <p>&copy; <?php echo date('Y'); ?> 
                <a href="<?php echo esc_url(home_url()); ?>">
                    <?php echo esc_html(get_bloginfo('name')); ?>
                </a>. 
                <span>All rights reserved.</span>
            </p>
        </div>
    </div>
</body>
</html>
