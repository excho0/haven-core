<?php

namespace HavenCore\Lifecycle;

use HavenCore\Classes\HC_Settings;
use HavenCore\Utils\PageUtils;
use HavenCore\Services\HC_Database_Schema;


/**
 * Handles the lifecycle events for the HavenCore plugin.
 *
 * This includes:
 * - Activation (e.g., role creation, initial setup)
 * - Deactivation (e.g., cleanup non-persistent state)
 * - Uninstall (e.g., complete data removal)
 *
 * All lifecycle logic is centralized here for consistency and scalability.
 */
class LifecycleManager
{
    /**
     * Triggered when the plugin is activated.
     *
     * Responsibilities:
     * - Create the 'supplier' user role if it doesn't exist
     * - Create necessary pages like goodbye and supplier portal
     * - Flush rewrite rules
     *
     * @return void
     */
    public static function activate(): void
    {
        if (!get_role('supplier')) {
            add_role('supplier', 'Supplier', [
                'read' => true,
            ]);
        }

        PageUtils::createPageIfNotExists('Goodbye', 'goodbye');
        PageUtils::createPageIfNotExists('Supplier Portal', 'supplier-portal');
        PageUtils::createPageIfNotExists('Place Order', 'place-order');
        PageUtils::createPageIfNotExists('Account Security', HAVEN_CORE_PASSWORD_RESET_SLUG);

        HC_Database_Schema::install();

        // Re-publish pages if they exist but were previously set to draft
        PageUtils::setPageStatus('goodbye', 'publish');
        PageUtils::setPageStatus('supplier-portal', 'publish');
        PageUtils::setPageStatus('place-order', 'publish');
        PageUtils::setPageStatus(HAVEN_CORE_PASSWORD_RESET_SLUG, 'publish');


        flush_rewrite_rules();
    }

    /**
     * Triggered when the plugin is deactivated.
     *
     * Responsibilities:
     * - Flush rewrite rules
     *
     * @return void
     */
    public static function deactivate(): void
    {
        PageUtils::setPageStatus('goodbye', 'draft');
        PageUtils::setPageStatus('supplier-portal', 'draft');
        PageUtils::setPageStatus('place-order', 'draft');
        PageUtils::setPageStatus(HAVEN_CORE_PASSWORD_RESET_SLUG, 'draft');


        flush_rewrite_rules();
    }

    /**
     * Triggered when the plugin is uninstalled.
     *
     * Responsibilities:
     * - Remove plugin settings and supplier role
     * - Delete supplier-related post meta
     *
     * @return void
     */
    public static function uninstall(): void
    {
        // Delete all plugin settings
        $settings = new HC_Settings(false);
        $settings->deleteAll();

        // Remove the custom supplier role
        remove_role('supplier');

        // Delete post meta related to suppliers
        delete_post_meta_by_key('_supplier_data');
        delete_post_meta_by_key('_supplier_data_created');
        delete_post_meta_by_key('_supplier_email_action_id');

        
        PageUtils::deletePageIfExists('goodbye');
        PageUtils::deletePageIfExists('supplier-portal');
        PageUtils::deletePageIfExists('place-order');
        PageUtils::deletePageIfExists(HAVEN_CORE_PASSWORD_RESET_SLUG);

        HC_Database_Schema::uninstall();
    }

    /**
     * Injects custom templates for plugin-managed pages.
     *
     * @param string $template
     * @return string
     */
    public static function overrideTemplates(string $template): string
    {
        $slug = get_post_field('post_name', get_queried_object_id());

        switch ($slug) {
            case 'goodbye':
                return HAVEN_CORE_PATH . 'woocommerce/views/page-confirm-ac-removal.php';

            case 'supplier-portal':
                return HAVEN_CORE_PATH . 'views/suppliers/page-havencore-supplier-portal.php';

            case 'place-order':
                return HAVEN_CORE_PATH . 'woocommerce/views/page-place-order.php';

            case HAVEN_CORE_PASSWORD_RESET_SLUG:
                return HAVEN_CORE_PATH . 'views/auth/page-password-reset.php';

            default:
                return $template;
        }
    }
}
