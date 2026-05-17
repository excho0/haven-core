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
    private static bool $fetchClientLoaded = false;

    private static function assetUrl(string $relativePath, bool $forceTimestamp = false): string
    {
        $relativePath = ltrim($relativePath, '/');
        $url          = rtrim(HAVEN_CORE_URL, '/') . '/' . $relativePath;
        $path         = rtrim(HAVEN_CORE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if ($forceTimestamp) {
            return $url . '?v=' . time();
        }

        if (file_exists($path)) {
            return $url . '?ver=' . filemtime($path);
        }

        return $url;
    }

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
            'withMiniQr'      => false,
            'withTheme'       => true,
            'withTailwind'    => true,
            'withPrimeIcons'  => true,
            'withFrontendCss' => false,
            'withMainStyle'   => true,
            'withFetchClient' => true,
            'fetchClientDebug' => null,
        ];

        $opts   = array_merge($defaults, $options);
        $mode   = strtolower($opts['mode']) === 'auto' ? (defined('WP_DEBUG') && WP_DEBUG ? 'dev' : 'prod') : strtolower($opts['mode']);
        $isProd = $mode === 'prod';
        $asset = static fn(string $path): string => self::assetUrl(
            'assets/' . ltrim($path, '/'),
            !$isProd
        );

        // Scripts
        $scripts = [
            // Core Vue
            $asset($isProd ? 'js/vue.global.prod.js' : 'js/vue.global.js'),
            $asset($isProd ? 'js/vue-router.global.prod.js' : 'js/vue-router.global.js'),
            $asset($isProd ? 'js/pinia.iife.prod.js' : 'js/pinia.iife.js'),
        ];

        if ($opts['withGlobalStore']) {
            $scripts[] = $asset('js/globalStore.js');
        }

        if ($opts['withPrimeVue']) {
            $scripts[] = $asset('js/primevue.min.js');
        }

        if ($opts['withSonner']) {
            $scripts[] = $asset('js/vue-sonner.umd.prod.js');
        }

        if ($opts['withDotLottie']) {
            $scripts[] = $asset('js/vue-dotlottie.umd.prod.js');
        }

        if ($opts['withConfetti']) {
            $scripts[] = $asset('js/confetti.browser.js');
        }

        if ($opts['withDraggable']) {
            $scripts[] = $asset('js/Sortable.min.js');              // 🟢 Sortable core
            $scripts[] = $asset('js/vuedraggable.umd.js');          // 🟢 Vue draggable (same file for both modes)
        }   

        if ($opts['withMiniQr']) {
            $scripts[] = $asset('js/mini-qr.umd.prod.js');
        }

        if ($opts['withTheme']) {
            $scripts[] = $asset('js/aura.min.js');
        }

        // Styles
        $styles = [];

        if ($opts['withMainStyle']) {
            $styles[] = $asset('css/style.min.css');
        }

        if ($opts['withFrontendCss']) {
            $styles[] = $asset('css/public-front.min.css');
        }

        if ($opts['withTailwind']) {
            $styles[] = $asset('css/tailwind.min.css');
        }

        if ($opts['withPrimeIcons']) {
            $styles[] = $asset('css/primeicons/primeicons.css');
        }

        $fetchClientDebug = $opts['fetchClientDebug'];
        if ($fetchClientDebug === null) {
            $fetchClientDebug = ($mode !== 'prod');
        }

        if ($opts['withFetchClient']) {
            self::loadFetchClient($fetchClientDebug);
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

    public static function loadFetchClient(bool $debug = false): void
    {
        if (self::$fetchClientLoaded) {
            return;
        }

        self::$fetchClientLoaded = true;

        $debugFlag = $debug ? 'true' : 'false';
        echo <<<HTML
<script>
    window.HavenCoreFetchClientConfig = window.HavenCoreFetchClientConfig || {};
    window.HavenCoreFetchClientConfig.debug = {$debugFlag};
</script>
HTML;
        $script = self::assetUrl('assets/js/fetchClient.js', $debug);
        echo '<script src="' . esc_url($script) . '"></script>' . "\n";
    }


}
