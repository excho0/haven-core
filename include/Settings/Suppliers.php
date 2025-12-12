<?php

namespace HavenCore\Settings;

use HavenCore\Classes\HC_Settings;

class Suppliers
{
    private static ?HC_Settings $settings = null;

    private static function s(): HC_Settings
    {
        if (!self::$settings) {
            self::$settings = new HC_Settings(true);
        }

        return self::$settings;
    }

    public static function all(): array
    {
        $value = self::s()->get('suppliers');
        return is_array($value) ? $value : [];
    }
}
