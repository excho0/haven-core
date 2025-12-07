<?php
/**
 * Supplier Product Update Admin Alert
 *
 * Variables available:
 * - $supplier (array): supplier details (name, email, id)
 * - $product (array): product context (name, sku, id, permalink, edit_link, attributes, etc.)
 * - $changes (array): list of changed fields with 'field', 'before', 'after'
 * - $actor (array): user who triggered the change
 * - $triggered_at (string): mysql datetime string
 */

defined('ABSPATH') || exit;

$site_name     = get_bloginfo('name');
$supplier_name = $supplier['name'] ?? __('Unknown supplier', HAVEN_CORE_TEXT_DOMAIN);
$supplier_email = $supplier['email'] ?? '';
$product_name  = $product['name'] ?? __('Product', HAVEN_CORE_TEXT_DOMAIN);
$product_sku   = $product['sku'] ?? __('N/A', HAVEN_CORE_TEXT_DOMAIN);
$product_link  = $product['permalink'] ?? ($product['edit_link'] ?? admin_url('edit.php?post_type=product'));
$product_edit  = $product['edit_link'] ?? $product_link;
$product_type  = !empty($product['is_variation']) ? __('Variation', HAVEN_CORE_TEXT_DOMAIN) : __('Product', HAVEN_CORE_TEXT_DOMAIN);
$attributes    = $product['attributes'] ?? '';
$actor_name    = $actor['name'] ?? $supplier_name;
$actor_email   = $actor['email'] ?? '';
$triggered_at  = $triggered_at ? mysql2date('F j, Y g:i a', $triggered_at) : current_time('F j, Y g:i a');

$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id
    ? wp_get_attachment_image_url($logo_id, 'full')
    : esc_url(get_stylesheet_directory_uri() . '/assets/img/default-logo.png');

include HAVEN_CORE_EMAIL_STYLES_PATH;
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Supplier update on %s', HAVEN_CORE_TEXT_DOMAIN), $site_name)); ?></title>
<style>
        .hc-change-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .hc-change-table th,
        .hc-change-table td {
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            text-align: left;
            font-size: 14px;
        }
        .hc-change-table th {
            background: #f3f4f6;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 12px;
            color: #374151;
        }
        .hc-meta-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .hc-meta-list li {
            margin-bottom: 6px;
        }
        .hc-meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef2ff;
            color: #4338ca;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .hc-button {
            display: inline-block;
            padding: 12px 22px;
            background: #111827;
            color: #fff !important;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 18px;
        }
        .hc-info-card {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
        }
        .hc-info-card strong {
            color: #1e3a8a;
        }
        .hc-product-header {
            display: flex;
            align-items: center;
            flex-direction: column;
            justify-content: center;
            gap: 16px;
            margin-top: 20px;
        }
        .hc-product-header img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" />
            </a>
        </div>

        <div class="email-content">
            <p class="hc-meta-badge"><?php echo esc_html($product_type); ?></p>
            <h2><?php echo esc_html__('A supplier updated product data', HAVEN_CORE_TEXT_DOMAIN); ?></h2>
            <p><?php echo esc_html__('Here are the details for your review:', HAVEN_CORE_TEXT_DOMAIN); ?></p>

            <div class="hc-product-header">
                <?php if (!empty($product['thumbnail'])) : ?>
                    <img src="<?php echo esc_url($product['thumbnail']); ?>" alt="<?php echo esc_attr($product_name); ?>" />
                <?php endif; ?>
                <div>
                    <h3><?php echo esc_html($product_name); ?></h3>
                    <?php
                    $sku_changed = false;
                    if ( ! empty( $changes ) ) {
                        foreach ( $changes as $change_row ) {
                            if ( isset( $change_row['field'] ) && strtolower( $change_row['field'] ) === 'sku' ) {
                                $sku_changed = true;
                                break;
                            }
                        }
                    }
                    if ( ! $sku_changed && $product_sku ) :
                    ?>
                        <p style="margin:4px 0 0;font-size:14px;color:#6b7280;">SKU: <?php echo esc_html($product_sku); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hc-info-card">
                <ul class="hc-meta-list">
                    <?php if (!empty($attributes)) : ?>
                        <li><strong><?php esc_html_e('Attributes:', HAVEN_CORE_TEXT_DOMAIN); ?></strong> <?php echo esc_html($attributes); ?></li>
                    <?php endif; ?>
                    <li><strong><?php esc_html_e('Supplier:', HAVEN_CORE_TEXT_DOMAIN); ?></strong> <?php echo esc_html($supplier_name); ?> (<?php echo esc_html($supplier_email); ?>)</li>
                    <li><strong><?php esc_html_e('Initiated by:', HAVEN_CORE_TEXT_DOMAIN); ?></strong> <?php echo esc_html($actor_name); ?> <?php echo $actor_email ? '(' . esc_html($actor_email) . ')' : ''; ?></li>
                    <li><strong><?php esc_html_e('Timestamp:', HAVEN_CORE_TEXT_DOMAIN); ?></strong> <?php echo esc_html($triggered_at); ?></li>
                </ul>
            </div>

            <?php if (!empty($changes)) : ?>
                <table class="hc-change-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Field', HAVEN_CORE_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Previous', HAVEN_CORE_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Updated', HAVEN_CORE_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($changes as $change) : ?>
                            <tr>
                                <td><?php echo esc_html($change['field']); ?></td>
                                <td><?php echo esc_html($change['before']); ?></td>
                                <td><?php echo esc_html($change['after']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <a class="hc-button" href="<?php echo esc_url($product_edit); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Review in Dashboard', HAVEN_CORE_TEXT_DOMAIN); ?>
            </a>

            <?php if ($product_link && $product_link !== $product_edit) : ?>
                <p style="margin-top:12px; font-size: 13px;">
                    <a href="<?php echo esc_url($product_link); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('View product on site', HAVEN_CORE_TEXT_DOMAIN); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <div class="email-footer">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s plugin name */
                        __('This message was generated by %s to keep you informed about supplier activity.', HAVEN_CORE_TEXT_DOMAIN),
                        PLUGIN_NAME
                    )
                );
            ?>
            </p>
        </div>
    </div>
</body>
</html>
