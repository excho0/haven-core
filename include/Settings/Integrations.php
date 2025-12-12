<?php

namespace HavenCore\Settings;

use HavenCore\Classes\HC_Settings;

class Integrations
{
    private static ?HC_Settings $settings = null;

    private static function s(): HC_Settings
    {
        if (!self::$settings) {
            self::$settings = new HC_Settings(true);
        }

        return self::$settings;
    }

    public static function paypalAutoTrackingEnabled(): bool
    {
        return self::s()->enabled('integrations.paypal.auto_tracking');
    }
}
