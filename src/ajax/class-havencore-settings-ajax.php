<?php

namespace HavenCore\Ajax;

use HavenCore\Classes\HC_Settings;
use HavenCore\Utils\ArrayHelpers;

/**
 * Class SettingsAjax
 *
 * Handles AJAX actions related to saving HavenCore plugin settings.
 *
 * Registered via AjaxManager::registerAll().
 *
 * @package HavenCore\Ajax
 */
class SettingsAjax
{
    /**
     * Registers all AJAX actions for the plugin settings.
     */
    public static function register(): void
    {
        add_action('wp_ajax_havencore_save_plugin_settings', [self::class, 'save']);
    }

    /**
     * Handles saving settings via AJAX.
     */
    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        // Initialize Settings without auto-loading defaults (we'll merge ourselves)
        $settings = new HC_Settings(false);
        $current  = json_decode(json_encode($settings->getAll()), true);

        // Read and decode JSON body
        $body     = json_decode(file_get_contents('php://input'), true);
        $rawInput = $body['settings'] ?? [];
        // error_log("RawInput: " . var_export($rawInput, true));

        if (!is_array($rawInput)) {
            error_log("❌ Invalid JSON input or 'settings' missing");
            wp_send_json_error(['message' => 'Invalid input'], 400);
        }

        // Build nested reference map from schema and current flat values
        $nestedRef = ArrayHelpers::buildReferenceMap(
            HC_Settings::$settingSchema,
            $current
        );

        // Recursive sanitizer (could be moved to Settings class)
        $sanitized = ArrayHelpers::sanitizeRecursive($rawInput, '', $nestedRef);
        
        // Flatten and set each value into Settings
        $flat    = ArrayHelpers::flatten($sanitized);
        foreach ($flat as $dotKey => $value) {
            // error_log("Setting [{$dotKey}]: " . var_export($value, true));
            $settings->set($dotKey, $value);
        }

        // Persist to database
        if ($settings->save()) {
            // error_log("✅ Settings saved successfully.");
            wp_send_json_success(['message' => 'Settings saved.']);
        }

        error_log("❌ Failed to save settings.");
        wp_send_json_error(['message' => 'Failed to save settings.'], 500);
    }
}