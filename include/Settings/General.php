<?php

namespace HavenCore\Settings;

use HavenCore\Classes\HC_Settings;

class General
{
    private static ?HC_Settings $settings = null;

    private static function s(): HC_Settings
    {
        if (!self::$settings) {
            self::$settings = new HC_Settings(true);
        }
        return self::$settings;
    }

    public static function customLoginPageEnabled(): bool
    {
        return self::s()->enabled('general.custom_login_page');
    }

    public static function passwordResetPageEnabled(): bool
    {
        return self::s()->enabled('general.password_reset_page');
    }
}
