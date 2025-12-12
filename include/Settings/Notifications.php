<?php

namespace HavenCore\Settings;

use HavenCore\Classes\HC_Settings;

class Notifications
{
    private static ?HC_Settings $settings = null;

    private static function s(): HC_Settings
    {
        if (!self::$settings) {
            self::$settings = new HC_Settings(true);
        }

        return self::$settings;
    }

    public static function customerVerificationEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customers.verification_email');
    }

    public static function customerPasswordResetEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customers.password_reset_email');
    }

    public static function customerAccountRemovalEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customers.account_removal_email');
    }

    public static function customerTrackingEmailsEnabled(): bool
    {
        return self::s()->enabled('notifications.customers.tracking_emails');
    }

    public static function customerPaymentReminderEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customers.payment_reminder_email');
    }

    public static function supplierAssignmentEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.suppliers.assignment_email');
    }

    public static function supplierReassignmentEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.suppliers.reassignment_email');
    }

    public static function supplierWelcomeEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.suppliers.welcome_email');
    }

    public static function adminSupplierProductUpdatesEnabled(): bool
    {
        return self::s()->enabled('notifications.administrators.supplier_product_updates_email');
    }
}
