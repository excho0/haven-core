<?php

namespace HavenCore\Settings;

use HavenCore\Classes\HC_Settings;

class WooCommerce
{
    private static ?HC_Settings $settings = null;

    private static function s(): HC_Settings
    {
        if (!self::$settings) {
            self::$settings = new HC_Settings(true);
        }
        return self::$settings;
    }

    public static function customerFarewellEnabled(): bool
    {
        return self::s()->enabled('WooCommerce.customer_farewell');
    }

    public static function orderReviewBeforePaymentEnabled(): bool
    {
        return self::s()->enabled('WooCommerce.order_review_before_payment');
    }

    public static function accountSecurityFlowEnabled(): bool
    {
        return self::s()->enabled('WooCommerce.account_security_flow');
    }
}
