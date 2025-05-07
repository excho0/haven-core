<?php

namespace HavenCore\Utils;

defined('ABSPATH') || exit;

/**
 * Utility class for managing pages used by the plugin.
 */
class PageUtils
{
    /**
     * Creates a WordPress page by slug if it does not already exist.
     *
     * @param string $title The title of the page.
     * @param string $slug The slug for the page.
     * @return int|null The page ID if created or existing, null on failure.
     */
    public static function createPageIfNotExists(string $title, string $slug): ?int
    {
        $existing_page = get_page_by_path($slug);
        if ($existing_page) {
            return $existing_page->ID;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_type'    => 'page',
            'post_status'  => 'publish',
        ]);

        return is_wp_error($post_id) ? null : $post_id;
    }

    /**
     * Safely changes the post status of a page, if needed.
     *
     * @param string $slug The slug of the page.
     * @param string $status The target status ('publish', 'draft', etc.).
     * @return void
     */
    public static function setPageStatus(string $slug, string $status): void
    {
        $valid_statuses = ['publish', 'draft', 'private', 'pending', 'trash'];
        if (!in_array($status, $valid_statuses, true)) {
            return;
        }

        $page = get_page_by_path($slug);
        if ($page && $page->post_type === 'page' && $page->post_status !== $status) {
            wp_update_post([
                'ID' => $page->ID,
                'post_status' => $status,
            ]);
        }
    }

    /**
     * Deletes a page by slug if it exists.
     *
     * @param string $slug
     * @return void
     */
    public static function deletePageIfExists(string $slug): void
    {
        $page = get_page_by_path($slug);
        if ($page && $page->post_type === 'page') {
            wp_delete_post($page->ID, true); // Force delete
        }
    }
}
