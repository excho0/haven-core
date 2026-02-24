<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Product Info Sheets Export (Grid)', 'woocommerce'); ?></title>
    <style>
        @page { size: A4; margin: 6mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            color: #0f172a;
            background: #fff;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .grid-wrap {
            width: 100%;
            margin: 0 auto;
        }
        .grid-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }
        .grid-title {
            margin: 0;
            font-size: 14px;
            line-height: 1.15;
            font-weight: 800;
        }
        .grid-subtitle {
            margin: 2px 0 0;
            font-size: 8px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #334155;
            font-weight: 700;
        }
        .grid-logo {
            max-width: 122px;
            max-height: 42px;
            object-fit: contain;
        }
        .cards-grid {
            column-count: 4;
            column-gap: 5px;
        }
        .product-card {
            border: 1px solid #cbd5e1;
            background: #fff;
            display: grid;
            grid-template-columns: 40% 60%;
            min-height: 92px;
            break-inside: avoid;
            page-break-inside: avoid;
            margin: 0 0 5px;
            width: 100%;
        }
        .card-media {
            background: #f8fafc;
            border-right: 1px solid #cbd5e1;
        }
        .card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .card-body {
            padding: 4px;
            display: grid;
            grid-template-rows: auto auto 1fr;
            gap: 2px;
        }
        .card-category {
            margin: 0;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #475569;
            font-weight: 700;
        }
        .card-name {
            margin: 0;
            font-size: 10px;
            line-height: 1.12;
            font-weight: 800;
        }
        .card-summary {
            margin: 0;
            font-size: 8px;
            line-height: 1.2;
            color: #1e293b;
            font-weight: 600;
        }
        .card-sections {
            display: grid;
            gap: 0;
            border-top: 1px solid #cbd5e1;
        }
        .card-section {
            padding-top: 2px;
            margin-top: 2px;
            border-top: 1px solid #e2e8f0;
        }
        .card-section:first-child {
            border-top: 0;
            margin-top: 0;
        }
        .card-section-title {
            margin: 0 0 1px;
            font-size: 7px;
            line-height: 1.15;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #334155;
            font-weight: 800;
        }
        @media print {
            .grid-wrap { width: 100%; }
        }
        @media (max-width: 1200px) {
            .cards-grid { column-count: 3; }
        }
        @media (max-width: 700px) {
            .cards-grid { column-count: 2; }
        }
    </style>
</head>
<body>
    <main class="grid-wrap">
        <header class="grid-head">
            <div>
                <h1 class="grid-title"><?php echo esc_html($store_name); ?></h1>
                <p class="grid-subtitle"><?php esc_html_e('Customer Product Information Sheets', 'woocommerce'); ?></p>
            </div>
            <?php if ($logo_url !== '') : ?>
                <img class="grid-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($store_name); ?>">
            <?php endif; ?>
        </header>
        <section class="cards-grid">
            <?php foreach ($cards as $card_html) : ?>
                <?php echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </section>
    </main>
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
