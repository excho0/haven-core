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
    private static bool $translatePressBootstrapped = false;

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

    /**
     * Bootstrap TranslatePress gettext processing for standalone templates that
     * do not call the normal WordPress frontend hooks like wp_head().
     */
    public static function bootstrapTranslatePress(): bool
    {
        if (self::$translatePressBootstrapped) {
            return true;
        }

        if (!class_exists('\TRP_Translate_Press')) {
            return false;
        }

        $trp = \TRP_Translate_Press::get_trp_instance();
        if (!is_object($trp) || !method_exists($trp, 'get_component')) {
            return false;
        }

        $gettext_manager = $trp->get_component('gettext_manager');
        if (!is_object($gettext_manager) || !method_exists($gettext_manager, 'apply_gettext_filter')) {
            return false;
        }

        $gettext_manager->apply_gettext_filter();
        self::$translatePressBootstrapped = true;

        return true;
    }

    /**
     * Resolve the active TranslatePress language for the current request or user.
     */
    public static function resolveTranslatePressLanguage(?\WP_User $user = null): ?string
    {
        if (!class_exists('\TRP_Translate_Press')) {
            return null;
        }

        $trp = \TRP_Translate_Press::get_trp_instance();
        if (!is_object($trp) || !method_exists($trp, 'get_component')) {
            return null;
        }

        $settings_component = $trp->get_component('settings');
        $url_converter = $trp->get_component('url_converter');
        $settings = is_object($settings_component) && method_exists($settings_component, 'get_settings')
            ? $settings_component->get_settings()
            : [];
        $languages = $settings['translation-languages'] ?? [];

        if (!is_array($languages) || empty($languages)) {
            return null;
        }

        $requested_language = is_object($url_converter) && method_exists($url_converter, 'get_lang_from_url_string')
            ? $url_converter->get_lang_from_url_string()
            : null;

        if (!empty($requested_language) && in_array($requested_language, $languages, true)) {
            return $requested_language;
        }

        global $TRP_LANGUAGE;

        if (!empty($TRP_LANGUAGE) && in_array($TRP_LANGUAGE, $languages, true)) {
            return $TRP_LANGUAGE;
        }

        if ($user instanceof \WP_User && $user->exists()) {
            $preferred_language = get_user_meta($user->ID, 'trp_language', true);

            if (empty($preferred_language)) {
                $fallback_locale = get_user_locale($user);
                if (in_array($fallback_locale, $languages, true)) {
                    $preferred_language = $fallback_locale;
                }
            }

            if (!empty($preferred_language) && in_array($preferred_language, $languages, true)) {
                return $preferred_language;
            }
        }

        return null;
    }

    /**
     * Convert a local site URL to the requested TranslatePress language.
     */
    public static function localizeUrl(string $url, ?string $language = null): string
    {
        if (!class_exists('\TRP_Translate_Press')) {
            return $url;
        }

        $language = $language ?: self::resolveTranslatePressLanguage(is_user_logged_in() ? wp_get_current_user() : null);
        if (empty($language)) {
            return $url;
        }

        $trp = \TRP_Translate_Press::get_trp_instance();
        if (!is_object($trp) || !method_exists($trp, 'get_component')) {
            return $url;
        }

        $url_converter = $trp->get_component('url_converter');
        if (!is_object($url_converter) || !method_exists($url_converter, 'get_url_for_language')) {
            return $url;
        }

        return $url_converter->get_url_for_language($language, $url, '');
    }

    /**
     * Build the supplier portal URL in the active or preferred language.
     */
    public static function supplierPortalUrl(string $hash = '', ?\WP_User $user = null): string
    {
        $portal_url = self::localizeUrl(
            home_url('/supplier-portal'),
            self::resolveTranslatePressLanguage($user)
        );

        if ($hash === '') {
            return $portal_url;
        }

        return $portal_url . '#' . ltrim($hash, '#');
    }

    /**
     * Build the current request URL as an absolute site URL.
     */
    public static function currentUrl(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        return home_url($request_uri);
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
