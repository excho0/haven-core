<?php

namespace HavenCore\Services;

defined('ABSPATH') || exit;

/**
 * Handles storage of verification/reset tokens using WordPress transients.
 */
class EmailVerificationService
{
    private const TRANSIENT_PREFIX = 'havencore_email_verification_';
    private const EMAIL_INDEX_PREFIX = 'havencore_email_verification_email_';

    /**
     * Create a verification/reset request and cache it.
     *
     * @param array $data {
     *     Required. Payload describing the request.
     *
     *     @type string $username   Desired username (for registrations).
     *     @type string $user_email Email address tied to the request.
     *     @type string $context    Free-form context string (registration, reset, etc).
     *     @type array  $meta       Optional associative metadata (user_id, etc).
     * }
     * @param int $expiration Expiration in seconds. Defaults to one day.
     * @return array|null
     */
    public function create_request(array $data, int $expiration = DAY_IN_SECONDS): ?array
    {
        if (empty($data['user_email']) || !is_email($data['user_email'])) {
            return null;
        }

        $username = sanitize_text_field($data['username'] ?? '');
        $email    = sanitize_email($data['user_email']);
        $context  = sanitize_key($data['context'] ?? 'registration');
        $meta     = is_array($data['meta'] ?? null) ? $this->sanitize_meta_array($data['meta']) : [];

        $token       = wp_generate_uuid4();
        $created_at  = current_time('mysql');
        $expires_at  = current_time('timestamp') + $expiration;
        $expire_date = wp_date('Y-m-d H:i:s', $expires_at);

        $record = [
            'username'    => $username,
            'user_email'  => $email,
            'context'     => $context,
            'meta'        => $meta,
            'token'       => $token,
            'created_at'  => $created_at,
            'expire_date' => $expire_date,
            'expires_at'  => $expires_at,
            'is_active'   => 1,
        ];

        $ttl = max(1, $expires_at - current_time('timestamp'));

        return self::store_request($token, $record, $ttl) ? $record : null;
    }

    /**
     * Retrieve a verification request by token.
     *
     * @param string $token
     * @return array|null
     */
    public static function get_request(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $record = get_transient(self::token_key($token));

        if (!is_array($record)) {
            return null;
        }

        if (empty($record['is_active'])) {
            return null;
        }

        if (!empty($record['expires_at']) && $record['expires_at'] < current_time('timestamp')) {
            delete_transient(self::token_key($token));
            return null;
        }

        return $record;
    }

    /**
     * Mark a verification request as inactive (used).
     *
     * @param string $token
     * @return bool
     */
    public static function mark_inactive(string $token): bool
    {
        $record = get_transient(self::token_key($token));
        if (!is_array($record)) {
            return false;
        }

        $record['is_active'] = 0;
        return self::save_request($token, $record);
    }

    /**
     * Remove a verification request completely.
     *
     * @param string $token
     * @return void
     */
    public static function delete_request(string $token): void
    {
        $record = self::get_request($token);
        delete_transient(self::token_key($token));

        if ($record && !empty($record['user_email'])) {
            delete_transient(self::email_index_key($record['user_email']));
        }
    }

    /**
     * Fetch an active request by email if available.
     *
     * @param string $email
     * @return array|null
     */
    public static function get_request_by_email(string $email): ?array
    {
        $token = get_transient(self::email_index_key($email));
        return $token ? self::get_request($token) : null;
    }

    /**
     * Persist an updated request record.
     *
     * @param string   $token
     * @param array    $record
     * @param int|null $ttl Optional TTL override in seconds.
     * @return bool
     */
    public static function save_request(string $token, array $record, ?int $ttl = null): bool
    {
        if ($ttl === null) {
            $expires_at = $record['expires_at'] ?? (current_time('timestamp') + DAY_IN_SECONDS);
            $ttl = max(1, $expires_at - current_time('timestamp'));
        } else {
            $record['expires_at'] = current_time('timestamp') + $ttl;
            $record['expire_date'] = wp_date('Y-m-d H:i:s', $record['expires_at']);
        }

        return self::store_request($token, $record, $ttl);
    }

    /**
     * Store the request data and keep the email index up to date.
     *
     * @param string $token
     * @param array  $record
     * @param int    $ttl
     * @return bool
     */
    private static function store_request(string $token, array $record, int $ttl): bool
    {
        $stored = set_transient(self::token_key($token), $record, $ttl);

        if ($stored && !empty($record['user_email'])) {
            set_transient(self::email_index_key($record['user_email']), $token, $ttl);
        }

        return $stored;
    }

    /**
     * Recursively sanitize the meta payload.
     *
     * @param array $meta
     * @return array
     */
    private function sanitize_meta_array(array $meta): array
    {
        $sanitized = [];

        foreach ($meta as $key => $value) {
            $sanitized_key = is_string($key) ? sanitize_key($key) : $key;

            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_meta_array($value);
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = 0 + $value;
            } elseif (is_scalar($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Build the transient key for a token.
     */
    private static function token_key(string $token): string
    {
        return self::TRANSIENT_PREFIX . $token;
    }

    /**
     * Build the transient key for an email index entry.
     */
    private static function email_index_key(string $email): string
    {
        return self::EMAIL_INDEX_PREFIX . md5(strtolower(trim($email)));
    }
}
