<?php

namespace HavenCore\Utils;

/**
 * Class ArrayHelpers
 *
 * Provides static methods for transforming and manipulating nested arrays,
 * specifically for handling setting schemas and options in HavenCore.
 */
class ArrayHelpers
{
    /**
     * Build a nested defaults array from a schema definition.
     *
     * @param array $schema  The setting schema array, where each leaf has ['key', 'default'].
     * @return array  Nested array of default values matching the schema.
     */
    public static function buildDefaults(array $schema): array
    {
        $output = [];
    
        foreach ($schema as $key => $value) {
            // Skip non-array values (e.g., 'icon' => 'pi pi-users')
            if (!is_array($value)) {
                continue;
            }
    
            // Field: has a 'key' and 'default'
            if (isset($value['key']) && array_key_exists('default', $value)) {
                $output[$key] = $value['default'];
            }
    
            // Nested group: recurse
            else {
                $output[$key] = self::buildDefaults($value);
            }
        }
    
        return $output;
    }
    

    /**
     * Build a nested reference map from flat current values and a schema.
     *
     * @param array $schema   The setting schema array.
     * @param array $current  Flat array of current values keyed by schema 'key'.
     * @return array  Nested reference map of current or default values.
     */
    public static function buildReferenceMap(array $schema, array $current): array
    {
        $output = [];
    
        foreach ($schema as $group => $def) {
            // Skip if not an array (e.g., 'icon' => 'pi pi-users')
            if (!is_array($def)) {
                continue;
            }
    
            if (isset($def['key'])) {
                $flatKey = $def['key'];
                $output[$group] = $current[$flatKey] ?? $def['default'];
            } else {
                $output[$group] = self::buildReferenceMap($def, $current);
            }
        }
    
        return $output;
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * Example: ['a' => ['b' => 1]] becomes ['a.b' => 1]
     *
     * @param array  $array   The array to flatten.
     * @param string $prefix  Dot notation prefix for recursive calls.
     * @return array  Flattened associative array.
     */
    public static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $composite = $prefix !== '' ? "$prefix.$key" : $key;
            if (is_array($value)) {
                $result += self::flatten($value, $composite);
            } else {
                $result[$composite] = $value;
            }
        }
        return $result;
    }

    /**
     * Recursively sanitize input based on a nested reference map (from buildReferenceMap).
     *
     * @param mixed  $input The user input to sanitize.
     * @param string $path  Dot path (for debugging/logging).
     * @param mixed  $ref   The reference value (used to infer type).
     * @return mixed Sanitized input.
     */
    public static function sanitizeRecursive(mixed $input, string $path = '', mixed $ref = null): mixed
    {
        if ($ref === null) {
            return is_array($input)
                ? array_map(fn($val) => self::sanitizeRecursive($val, $path), $input)
                : sanitize_text_field($input);
        }

        if (is_array($input)) {
            $output = [];
            foreach ($input as $key => $value) {
                $nextRef = is_array($ref) && array_key_exists($key, $ref) ? $ref[$key] : null;
                $nextPath = $path === '' ? $key : "$path.$key";
                $output[$key] = self::sanitizeRecursive($value, $nextPath, $nextRef);
            }
            return $output;
        }

        $refType = gettype($ref);
        // error_log("Sanitizing [$path]: " . var_export($input, true) . " | refType=$refType");

        return match ($refType) {
            'boolean' => filter_var($input, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $input,
            'double'  => (float) $input,
            default   => sanitize_text_field($input),
        };
    }
    
    /**
     * Extracts UI-related metadata (like tooltips and icons) from a settings schema.
     *
     * This is useful for building admin interfaces without hardcoding tooltips/icons in the frontend.
     *
     * @param array $schema The full settings schema array.
     * @return array An array structured similarly to the schema but containing only `tooltip` and `icon` metadata.
     */
    public static function extractUiMeta(array $schema): array
    {
        $out = [];
    
        foreach ($schema as $key => $def) {
            // Skip non-arrays (e.g., strings)
            if (!is_array($def)) {
                continue;
            }
    
            // Field-level definition
            if (isset($def['key'])) {
                $out[$key] = [
                    'tooltip' => $def['tooltip'] ?? '',
                    'icon'    => $def['icon'] ?? '',
                ];

                if (array_key_exists('hidden', $def)) {
                    $out[$key]['hidden'] = (bool) $def['hidden'];
                }
            }
    
            // Group or subsection
            else {
                $out[$key] = self::extractUiMeta($def);

                // Preserve group-level metadata directly
                if (isset($def['icon'])) {
                    $out[$key]['icon'] = $def['icon'];
                }
                if (isset($def['tooltip'])) {
                    $out[$key]['tooltip'] = $def['tooltip'];
                }
                if (array_key_exists('hidden', $def)) {
                    $out[$key]['hidden'] = (bool) $def['hidden'];
                }
            }
        }
    
        return $out;
    }

    /**
     * Recursively extracts and translates labels and tooltips from a settings schema.
     *
     * Builds a flattened or nested structure of i18n-ready keys with WooCommerce translation support.
     *
     * Example output:
     * [
     *   'general.setting_name' => [
     *     'label' => 'Setting Name',
     *     'tooltip' => 'Some translated tooltip',
     *   ]
     * ]
     *
     * @param array $schema The full settings schema array.
     * @param array $i18n Accumulator array for recursion (leave empty when calling externally).
     * @param string $prefix Used internally to build dot-path keys for nested fields.
     * @return array An associative array of i18n labels and tooltips keyed by dot-path.
     */
    public static function extractTranslations(array $schema, array $i18n = [], string $prefix = ''): array {
        foreach ($schema as $key => $value) {
            if (!is_array($value)) continue;
    
            $path = $prefix ? "{$prefix}.{$key}" : $key;
            $label = ucwords(str_replace('_', ' ', $key));
            $translatedLabel = __($label, 'woocommerce');
    
            if (!isset($i18n[$path])) {
                $i18n[$path] = [];
            }
    
            $i18n[$path]['label'] = $translatedLabel;
    
            // Translate tooltip if present
            if (isset($value['tooltip'])) {
                $i18n[$path]['tooltip'] = __($value['tooltip'], 'woocommerce');
            } elseif (!isset($i18n[$path]['tooltip'])) {
                $i18n[$path]['tooltip'] = '';
            }
    
            // Skip further if it's a leaf field
            if (isset($value['key']) && array_key_exists('default', $value)) {
                continue;
            }
    
            // Recurse into nested structure
            $i18n = self::extractTranslations($value, $i18n, $path);
        }
    
        return $i18n;
    }


    /**
     * Filters out keys from a settings array that are not defined in the given schema.
     *
     * Useful for cleaning user-modified or outdated settings before merging or saving.
     *
     * Preserves nested structure and only includes keys that exist in the schema.
     *
     * @param array $data The user or stored settings data.
     * @param array $schema The schema defining allowed keys and structure.
     * @return array A cleaned version of $data that only includes keys present in $schema.
     */
    public static function filterBySchema(array $data, array $schema): array
    {
        $filtered = [];

        foreach ($schema as $key => $schemaValue) {
            if (array_key_exists($key, $data)) {
                if (is_array($schemaValue) && is_array($data[$key])) {
                    // Recurse for nested structures
                    $filtered[$key] = self::filterBySchema($data[$key], $schemaValue);
                } else {
                    // Keep scalar value
                    $filtered[$key] = $data[$key];
                }
            }
        }

        return $filtered;
    }
    
}