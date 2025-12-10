<?php

namespace HavenCore\Utils;

/**
 * Class ScriptHelpers
 *
 * Utility class for injecting frontend assets (scripts and styles) into custom or barebone WordPress templates.
 *
 * Designed for use in templates that bypass traditional WordPress enqueue mechanisms (e.g., full-screen apps, Vue-based UIs, custom admin pages).
 *
 * Responsibilities:
 * - Load Vue.js ecosystem (Vue, Router, Pinia, Global Store)
 * - Conditionally include supporting libraries like PrimeVue, vue-sonner, DotLottie, Sortable.js
 * - Inject associated stylesheets: Tailwind, PrimeIcons, custom themes
 * - Support automatic or explicit environment mode (`dev`, `prod`, or `auto`)
 * - Handle cache-busting for development freshness
 * - Output clean, ordered `<script>` and `<link>` tags directly into the page
 */
class ScriptHelpers
{
    /**
     * Initializes wp-api-fetch and related dependencies manually.
     *
     * Use this in templates that do NOT call wp_head() or wp_footer(),
     * such as barebone or headless-friendly PHP views.
     *
     * @return void
     */
    public static function loadApiFetch(): void
    {
        // Enqueue required dependencies
        wp_enqueue_script('wp-hooks');
        wp_enqueue_script('wp-i18n');
        wp_enqueue_script('wp-url');
        wp_enqueue_script('wp-api-fetch');

        // Force output of the scripts (manual since no wp_head/wp_footer)
        global $wp_scripts;
        $wp_scripts->do_items(['wp-hooks', 'wp-i18n', 'wp-url', 'wp-api-fetch']);

        // Output nonce and attach it to wp.apiFetch
        $nonce = wp_create_nonce('wp_rest');

        echo <<<HTML
            <script type="text/javascript">
                const wpApiSettings = { nonce: "{$nonce}" };

                if (window.wp?.apiFetch) {
                    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(wpApiSettings.nonce));
                } else {
                    console.warn("wp.apiFetch is not available.");
                }
            </script>
        HTML;
    }

    public static function loadVue(array $options = []): void
    {
        $defaults = [
            'mode'            => 'auto',
            'withGlobalStore'     => true,
            'withPrimeVue'    => true,
            'withSonner'      => true,
            'withDotLottie'   => false,
            'withConfetti'    => false,
            'withDraggable'   => false,   // Draggable bundle (Sortable + VueDraggable)
            'withTheme'       => true,
            'withTailwind'    => true,
            'withPrimeIcons'  => true,
            'withFrontendCss' => false,
            'withMainStyle'   => true,
        ];

        $opts   = array_merge($defaults, $options);
        $mode   = strtolower($opts['mode']) === 'auto' ? (defined('WP_DEBUG') && WP_DEBUG ? 'dev' : 'prod') : strtolower($opts['mode']);
        $isProd = $mode === 'prod';
        $base   = rtrim(HAVEN_CORE_URL, '/') . '/assets';
        $ver = (!$isProd) ? ('?v=' . time()) : '';

        // Scripts
        $scripts = [
            // Core Vue
            $isProd ? "$base/js/vue.global.prod.js"        : "$base/js/vue.global.js",
            $isProd ? "$base/js/vue-router.global.prod.js" : "$base/js/vue-router.global.js",
            $isProd ? "$base/js/pinia.iife.prod.js$ver"    : "$base/js/pinia.iife.js$ver",
        ];

        if ($opts['withGlobalStore']) {
            $scripts[] = "$base/js/globalStore.js$ver";
            $scripts[] = "$base/js/fetchClient.js$ver";
        }

        if ($opts['withPrimeVue']) {
            $scripts[] = "$base/js/primevue.min.js";
        }

        if ($opts['withSonner']) {
            $scripts[] = "$base/js/vue-sonner.umd.prod.js$ver";
        }

        if ($opts['withDotLottie']) {
            $scripts[] = "$base/js/vue-dotlottie.umd.prod.js$ver";
        }

        if ($opts['withConfetti']) {
            $scripts[] = "$base/js/confetti.browser.js";
        }

        if ($opts['withDraggable']) {
            $scripts[] = "$base/js/Sortable.min.js";                // 🟢 Sortable core
            $scripts[] = "$base/js/vuedraggable.umd.js";            // 🟢 Vue draggable (same file for both modes)
        }   

        if ($opts['withTheme']) {
            $scripts[] = "$base/js/aura.min.js";
        }

        // Styles
        $styles = [];

        if ($opts['withMainStyle']) {
            $styles[] = "$base/css/style.min.css$ver";
        }

        if ($opts['withFrontendCss']) {
            $styles[] = "$base/css/public-front.min.css$ver";
        }

        if ($opts['withTailwind']) {
            $styles[] = "$base/css/tailwind.min.css";
        }

        if ($opts['withPrimeIcons']) {
            $styles[] = "$base/css/primeicons/primeicons.css";
        }

        // Output styles
        foreach ($styles as $style) {
            echo '<link rel="stylesheet" href="' . esc_url($style) . '">' . "\n";
        }

        // Output scripts
        foreach ($scripts as $script) {
            echo '<script src="' . esc_url($script) . '"></script>' . "\n";
        }

        echo "<!-- 🧩 Vue app initialized in {$mode} mode -->\n";
    }


}
