<?php

namespace HavenCore\Utils;

defined('ABSPATH') || exit;

/**
 * User-related utilities for role/capability safety and token management.
 */
class UserUtils {

    /**
     * Check if the email belongs to a protected user (admin/root).
     *
     * @param string $email
     * @return bool
     */
    public static function is_protected_user(string $email): bool {
        $user = get_user_by('email', $email);
        return $user && ($user->ID === 1 || in_array('administrator', $user->roles ?? [], true));
    }

    /**
     * Store a verification token as a transient for the given email and prefix.
     *
     * @param string $prefix
     * @param string $email
     * @param int $expiration (in seconds) Default: 24 hours.
     * @return string|false The token if successful, false on failure.
     */
    public static function store_verification_token(string $prefix, string $email, int $expiration = 86400) {
        $transient_key = "{$prefix}_token_" . md5($email);

        $token = wp_generate_uuid4(); // Generate a UUID

        $stored = set_transient($transient_key, $token, $expiration);

        return $stored ? $token : false;
    }


    /**
     * Retrieve the verification token from a transient for the given email and prefix.
     *
     * @param string $prefix
     * @param string $email
     * @return string|null
     */
    public static function get_verification_token(string $prefix, string $email): ?string {
        $transient_key = "{$prefix}_token_" . md5($email);
        $token = get_transient($transient_key);
        return $token !== false ? $token : null;
    }

    /**
     * Delete the verification token transient for the given email and prefix.
     *
     * @param string $prefix
     * @param string $email
     * @return bool
     */
    public static function delete_verification_token(string $prefix, string $email): bool {
        $transient_key = "{$prefix}_token_" . md5($email);
        return delete_transient($transient_key);
    }

    /**
     * Validate the verification token for the given email and prefix.
     *
     * @param string $prefix
     * @param string $email
     * @param string $token
     * @return bool
     */
    public static function validate_verification_token(string $prefix, string $email, string $token): bool {
        $stored_token = self::get_verification_token($prefix, $email);
        return $stored_token !== null && hash_equals($stored_token, $token);
    }
}
