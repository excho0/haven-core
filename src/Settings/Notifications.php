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

    // Customer emails
    public static function customerVerificationEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customer_verification_email');
    }

    public static function customerPasswordResetEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customer_password_reset_email');
    }

    public static function customerAccountRemovalEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customer_account_removal_email');
    }

    public static function customerTrackingEmailsEnabled(): bool
    {
        return self::s()->enabled('notifications.customer_tracking_emails');
    }

    public static function customerPaymentReminderEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.customer_payment_reminder_email');
    }

    // Supplier emails
    public static function supplierAssignmentEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.supplier_assignment_email');
    }

    public static function supplierReassignmentEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.supplier_reassignment_email');
    }

    public static function supplierWelcomeEmailEnabled(): bool
    {
        return self::s()->enabled('notifications.supplier_welcome_email');
    }

    // Admin notifications
    public static function adminSupplierProductUpdatesEnabled(): bool
    {
        return self::s()->enabled('notifications.notify_admin_supplier_product_updates');
    }
}
