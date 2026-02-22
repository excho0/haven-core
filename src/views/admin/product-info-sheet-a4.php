<?php
if (!defined('ABSPATH')) {
    exit;
}

$store_location_bits = array_filter([
    trim(($store_address_1 ?? '') . ' ' . ($store_address_2 ?? '')),
    trim(($store_city ?? '') . ' ' . ($store_postcode ?? '')),
    trim(($store_state_label ?? '') . ' ' . ($store_country_label ?? '')),
]);

$hero_image = '';
if (!empty($image_urls) && !empty($image_urls[0])) {
    $hero_image = (string) $image_urls[0];
}

$category_text = wp_strip_all_tags((string) wc_get_product_category_list($product->get_id(), ', '));
$short_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $short_description)));
$description_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $description)));

$tab_summaries = [];
foreach ((array) $product_tabs as $tab) {
    $title = trim((string) ($tab['title'] ?? ''));
    $content_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($tab['content'] ?? ''))));
    if ($title === '' || $content_plain === '') {
        continue;
    }
    $tab_summaries[] = [
        'title' => $title,
        'summary' => $content_plain,
    ];
}

$attribute_lines = [];
foreach ((array) $attributes as $attr) {
    $label = trim((string) ($attr['label'] ?? ''));
    $value = trim((string) ($attr['value'] ?? ''));
    if ($label === '' || $value === '') {
        continue;
    }
    $attribute_lines[] = $label . ': ' . $value;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($product->get_name()); ?> - <?php echo esc_html($store_name); ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        :root {
            --ink: #111827;
            --muted: #475569;
            --line: #d1d5db;
            --line-strong: #9ca3af;
            --soft: #f8fafc;
            --brand: #0f172a;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .sheet {
            width: 100%;
            height: calc(297mm - 20mm - 3mm);
            border: 1px solid var(--line);
            padding: 10px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 14px;
            overflow: hidden;
        }
        .store-head {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            border-bottom: 2px solid var(--brand);
            padding-bottom: 10px;
        }
        .store-logo {
            width: 140px;
            height: 96px;
            object-fit: contain;
            border: none;
            background: transparent;
            padding: 0;
        }
        .store-title {
            margin: 0;
            font-size: 1.2rem;
            line-height: 1.1;
            letter-spacing: .01em;
        }
        .sheet-subtitle {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: .7rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .product-layout {
            min-height: 0;
            display: grid;
            grid-template-columns: 54% 46%;
            gap: 10px;
            padding-top: 4px;
            padding-bottom: 6px;
            overflow: hidden;
        }
        .media {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: var(--soft);
            min-height: 0;
        }
        .media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .media-empty {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: var(--muted);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .details {
            min-height: 0;
            display: grid;
            grid-template-rows: auto auto auto auto auto 1fr;
            gap: 7px;
            overflow: hidden;
        }
        .crumb {
            margin: 0;
            color: var(--muted);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }
        .product-name {
            margin: 0;
            font-size: 1.55rem;
            line-height: 1.08;
            max-height: 3.35rem;
            overflow: hidden;
        }
        .meta-line {
            margin: 0;
            font-size: .78rem;
            color: var(--muted);
        }
        .price {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
        }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .chip {
            border: 1px solid var(--line-strong);
            background: var(--soft);
            border-radius: 999px;
            padding: 2px 8px;
            font-size: .68rem;
            color: #1f2937;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sections {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            min-height: 0;
            display: grid;
            grid-auto-rows: min-content;
            align-content: start;
        }
        .section-row {
            padding: 7px 8px;
            border-top: 1px solid var(--line);
        }
        .section-row:first-child { border-top: 0; }
        .section-title {
            margin: 0 0 4px;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #334155;
            font-weight: 800;
        }
        .section-body {
            margin: 0;
            font-size: .76rem;
            line-height: 1.35;
            color: #1f2937;
            overflow: visible;
        }
        .footer {
            border-top: 2px solid var(--brand);
            padding-top: 9px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--muted);
            font-size: .7rem;
        }
        @media print {
            html, body {
                height: auto;
                overflow: hidden;
            }
            .sheet {
                border: none;
                padding: 0;
                height: calc(297mm - 20mm - 3mm);
                max-height: calc(297mm - 20mm - 3mm);
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
<article class="sheet">
    <header class="store-head">
        <div>
            <h1 class="store-title"><?php echo esc_html($store_name); ?></h1>
            <p class="sheet-subtitle"><?php echo esc_html__('Customer Product Information Sheet', 'woocommerce'); ?></p>
        </div>
        <div>
            <?php if (!empty($logo_url)) : ?>
                <img class="store-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($store_name); ?>">
            <?php endif; ?>
        </div>
    </header>

    <section class="product-layout">
        <div class="media">
            <?php if (!empty($hero_image)) : ?>
                <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
            <?php else : ?>
                <div class="media-empty"><?php esc_html_e('No Product Image', 'woocommerce'); ?></div>
            <?php endif; ?>
        </div>

        <div class="details">
            <p class="crumb"><?php echo esc_html($category_text ?: __('Product', 'woocommerce')); ?></p>
            <h2 class="product-name"><?php echo esc_html($product->get_name()); ?></h2>

            <?php if (!empty($attribute_lines)) : ?>
                <div class="chips">
                    <?php foreach (array_slice($attribute_lines, 0, 5) as $line) : ?>
                        <span class="chip"><?php echo esc_html($line); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="sections">
                <?php if ($short_plain !== '') : ?>
                    <div class="section-row">
                        <h3 class="section-title"><?php esc_html_e('Overview', 'woocommerce'); ?></h3>
                        <p class="section-body"><?php echo esc_html($short_plain); ?></p>
                    </div>
                <?php elseif ($description_plain !== '') : ?>
                    <div class="section-row">
                        <h3 class="section-title"><?php esc_html_e('Overview', 'woocommerce'); ?></h3>
                        <p class="section-body"><?php echo esc_html($description_plain); ?></p>
                    </div>
                <?php endif; ?>

                <?php foreach (array_slice($tab_summaries, 0, 6) as $tab) : ?>
                    <div class="section-row">
                        <h3 class="section-title"><?php echo esc_html($tab['title']); ?></h3>
                        <p class="section-body"><?php echo esc_html($tab['summary']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <span><?php echo esc_html($store_name); ?></span>
        <span><?php echo esc_html__('Customer Product Information Sheet', 'woocommerce'); ?></span>
    </footer>
</article>
<?php if (!empty($auto_print)) : ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 180);
    });
</script>
<?php endif; ?>
</body>
</html>
