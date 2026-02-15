<?php
/**
 * Mobile warning screen for supplier portal.
 */
?>
<!DOCTYPE html>
<html lang="en" style="overflow: hidden;">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚠️ Not Built for Mobile</title>
    <?php wp_site_icon(); ?>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100%;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        .warning {
            background: white;
            padding: 2.25rem 2rem;
            border-radius: 1.25rem;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 95%;
            width: 420px;
            animation: fadeIn 0.4s ease-out both;
        }
        .warning h1 {
            font-size: 1.75rem;
            color: #dc2626;
            margin-bottom: 1.2rem;
        }
        .warning p {
            font-size: 1rem;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .warning button {
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            color: white;
            padding: 1rem 1.75rem;
            border-radius: 9999px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .warning button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.5);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="warning">
        <h1>⚠️ Not Built for Mobile</h1>
        <p>
            This dashboard is optimized for desktop and laptop devices.<br><br>
            Mobile phones — even mid-range ones — may experience lag, glitches, or crashes.<br><br>
            We strongly recommend using a computer or tablet.<br><br>
            If you still want to continue, you may proceed below.
        </p>
        <form method="POST">
            <button type="submit" name="confirm_continue">I Understand, Continue →</button>
        </form>
    </div>
</body>
</html>
