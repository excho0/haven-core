<?php

namespace HavenCore\Services;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Handles CRUD operations for supplier/admin conversations and messages.
 */
class HC_Messaging_Service
{
    private wpdb $wpdb;

    public function __construct(?wpdb $wpdb = null)
    {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
    }

    public function createConversation(array $data): ?array
    {
        $supplierId = absint($data['supplier_id'] ?? 0);
        if (!$supplierId) {
            return null;
        }

        $now = $this->now();
        $payload = [
            'supplier_id' => $supplierId,
            'admin_user_id' => empty($data['admin_user_id']) ? null : absint($data['admin_user_id']),
            'order_id' => empty($data['order_id']) ? null : absint($data['order_id']),
            'subject' => sanitize_text_field($data['subject'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'open'),
            'last_message_at' => $data['last_message_at'] ?? $now,
            'unread_admin_count' => absint($data['unread_admin_count'] ?? 0),
            'unread_supplier_count' => absint($data['unread_supplier_count'] ?? 0),
            'meta' => $this->encodeMeta($data['meta'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $this->wpdb->insert(
            $this->conversationTable(),
            $payload,
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if (false === $result) {
            return null;
        }

        return $this->getConversation((int) $this->wpdb->insert_id);
    }

    public function updateConversation(int $conversationId, array $data): bool
    {
        $fields = [];
        $formats = [];
        $allowed = ['subject', 'status', 'order_id', 'admin_user_id', 'unread_admin_count', 'unread_supplier_count', 'meta'];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            switch ($key) {
                case 'subject':
                case 'status':
                    $fields[$key] = sanitize_text_field($data[$key]);
                    $formats[] = '%s';
                    break;
                case 'order_id':
                case 'admin_user_id':
                    $value = $data[$key];
                    if ($value === null || $value === '') {
                        $fields[$key] = null;
                        $formats[] = '%d';
                    } else {
                        $fields[$key] = absint($value);
                        $formats[] = '%d';
                    }
                    break;
                case 'unread_admin_count':
                case 'unread_supplier_count':
                    $fields[$key] = max(0, absint($data[$key]));
                    $formats[] = '%d';
                    break;
                case 'meta':
                    $fields[$key] = $this->encodeMeta($data[$key]);
                    $formats[] = '%s';
                    break;
            }
        }

        if (empty($fields)) {
            return true;
        }

        $fields['updated_at'] = $this->now();
        $formats[] = '%s';

        $updated = $this->wpdb->update(
            $this->conversationTable(),
            $fields,
            ['id' => $conversationId],
            $formats,
            ['%d']
        );

        return false !== $updated;
    }

    public function getConversation(int $conversationId): ?array
    {
        $table = $this->conversationTable();
        $users = $this->wpdb->users;

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT c.*, u.display_name AS supplier_display_name, u.user_nicename AS supplier_user_nicename
                 FROM {$table} c
                 LEFT JOIN {$users} u ON u.ID = c.supplier_id
                 WHERE c.id = %d",
                $conversationId
            ),
            ARRAY_A
        );

        return $row ? $this->normalizeConversation($row) : null;
    }

    public function fetchConversations(array $args = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($args['supplier_id'])) {
            $where[] = 'c.supplier_id = %d';
            $params[] = absint($args['supplier_id']);
        }

        if (!empty($args['status'])) {
            $where[] = 'c.status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['order_id'])) {
            $where[] = 'c.order_id = %d';
            $params[] = absint($args['order_id']);
        }

        if (!empty($args['search'])) {
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = '(c.subject LIKE %s)';
            $params[] = $like;
        }

        if (!empty($args['has_unread_for'])) {
            $column = 'unread_admin_count';
            if ('supplier' === $args['has_unread_for']) {
                $column = 'unread_supplier_count';
            }

            $where[] = "c.{$column} > 0";
        }

        $limit = isset($args['limit']) ? max(1, min(100, absint($args['limit']))) : 20;
        $offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;
        $order = strtoupper($args['order'] ?? 'DESC');
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $table         = $this->conversationTable();
        $message_table = $this->messageTable();
        $users         = $this->wpdb->users;

        $latest_subquery = "SELECT m1.id, m1.conversation_id, m1.sender_type, m1.sender_id, m1.message, m1.created_at
            FROM {$message_table} m1
            INNER JOIN (
                SELECT conversation_id, MAX(id) AS max_id
                FROM {$message_table}
                GROUP BY conversation_id
            ) latest ON latest.max_id = m1.id";

        $sql = "SELECT c.*,
                        u.display_name AS supplier_display_name,
                        u.user_nicename AS supplier_user_nicename,
                        lm.message AS last_message_preview,
                        lm.sender_type AS last_message_sender_type,
                        lm.sender_id AS last_message_sender_id,
                        lm.created_at AS last_message_created_at
                FROM {$table} c
                LEFT JOIN {$users} u ON u.ID = c.supplier_id
                LEFT JOIN ({$latest_subquery}) lm ON lm.conversation_id = c.id
                WHERE " . implode(' AND ', $where) .
            " ORDER BY c.last_message_at {$order} LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $prepared = $this->wpdb->prepare($sql, $params);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A) ?: [];

        return array_map([$this, 'normalizeConversation'], $rows);
    }

    public function createMessage(int $conversationId, array $data): ?array
    {
        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return null;
        }

        $senderType = in_array($data['sender_type'] ?? 'supplier', ['supplier', 'admin'], true)
            ? $data['sender_type']
            : 'supplier';

        $messageBody = $data['message'] ?? '';
        $messageBody = is_string($messageBody) ? $messageBody : wp_json_encode($messageBody);
        $messageBody = wp_kses_post($messageBody);

        $now = $this->now();

        $payload = [
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => empty($data['sender_id']) ? null : absint($data['sender_id']),
            'message' => $messageBody,
            'attachments' => $this->encodeMeta($data['attachments'] ?? null),
            'is_read' => !empty($data['is_read']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $this->wpdb->insert(
            $this->messageTable(),
            $payload,
            ['%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        if (false === $result) {
            return null;
        }

        $messageId = (int) $this->wpdb->insert_id;

        if (!empty($data['files']) && is_array($data['files'])) {
            $this->attachFiles($messageId, $data['files']);
        }

        $this->touchConversation($conversationId, $senderType, !empty($data['is_read']));

        return $this->getMessage($messageId, $conversation);
    }

    public function getMessage(int $messageId, ?array $conversation = null): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->messageTable()} WHERE id = %d",
                $messageId
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $message = $this->normalizeMessage($row);
        $message['files'] = $this->getAttachments($messageId);

        if (!$conversation) {
            $conversation = $this->getConversation((int) ($message['conversation_id'] ?? 0));
        }

        return $this->enrichMessageSender($message, $conversation);
    }

    public function fetchMessages(int $conversationId, array $args = []): array
    {
        $order = strtoupper($args['order'] ?? 'ASC');
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';
        $limit = isset($args['limit']) ? max(1, min(200, absint($args['limit']))) : 50;
        $offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;

        $sql = "SELECT * FROM {$this->messageTable()} WHERE conversation_id = %d ORDER BY created_at {$order} LIMIT %d OFFSET %d";
        $prepared = $this->wpdb->prepare($sql, $conversationId, $limit, $offset);
        $rows = $this->wpdb->get_results($prepared, ARRAY_A) ?: [];

        $conversation = $this->getConversation($conversationId);

        return array_map(function (array $row) use ($conversation) {
            $message = $this->normalizeMessage($row);
            $message['files'] = $this->getAttachments((int) $row['id']);
            return $this->enrichMessageSender($message, $conversation);
        }, $rows);
    }

    public function getUnreadCountForAdmin(): int
    {
        $sum = $this->wpdb->get_var(
            "SELECT SUM(unread_admin_count) FROM {$this->conversationTable()}"
        );

        return (int) ($sum ?: 0);
    }

    public function getUnreadCountForSupplier(int $supplierId): int
    {
        if ($supplierId <= 0) {
            return 0;
        }

        $sum = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(unread_supplier_count) FROM {$this->conversationTable()} WHERE supplier_id = %d",
                $supplierId
            )
        );

        return (int) ($sum ?: 0);
    }

    public function markThreadAsRead(int $conversationId, string $audience): int
    {
        $audience = 'supplier' === $audience ? 'supplier' : 'admin';
        $column = 'admin' === $audience ? 'unread_admin_count' : 'unread_supplier_count';
        $oppositeSender = 'admin' === $audience ? 'supplier' : 'admin';

        $updated = $this->wpdb->update(
            $this->conversationTable(),
            [
                $column => 0,
                'updated_at' => $this->now(),
            ],
            ['id' => $conversationId],
            ['%d', '%s'],
            ['%d']
        );

        $this->wpdb->update(
            $this->messageTable(),
            ['is_read' => 1],
            [
                'conversation_id' => $conversationId,
                'sender_type' => $oppositeSender,
            ],
            ['%d'],
            ['%d', '%s']
        );

        return (int) $updated;
    }

    public function deleteConversation(int $conversationId): bool
    {
        $messageIds = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->messageTable()} WHERE conversation_id = %d",
                $conversationId
            )
        );

        if ($messageIds) {
            $placeholders = implode(',', array_fill(0, count($messageIds), '%d'));
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->attachmentTable()} WHERE message_id IN ({$placeholders})",
                    ...array_map('absint', $messageIds)
                )
            );
        }

        $this->wpdb->delete($this->messageTable(), ['conversation_id' => $conversationId], ['%d']);
        $deleted = $this->wpdb->delete($this->conversationTable(), ['id' => $conversationId], ['%d']);

        return (bool) $deleted;
    }

    public function updateMessage(int $messageId, array $data): bool
    {
        $fields  = [];
        $formats = [];

        if (array_key_exists('message', $data)) {
            $fields['message'] = wp_kses_post((string) $data['message']);
            $formats[]         = '%s';
        }

        if (array_key_exists('attachments', $data)) {
            $fields['attachments'] = $this->encodeMeta($data['attachments']);
            $formats[]             = '%s';
        }

        if (array_key_exists('is_read', $data)) {
            $fields['is_read'] = !empty($data['is_read']) ? 1 : 0;
            $formats[]         = '%d';
        }

        if (array_key_exists('sender_type', $data)) {
            $fields['sender_type'] = sanitize_key($data['sender_type']);
            $formats[]             = '%s';
        }

        if (array_key_exists('sender_id', $data)) {
            $fields['sender_id'] = $data['sender_id'] ? absint($data['sender_id']) : null;
            $formats[]           = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        $fields['updated_at'] = $this->now();
        $formats[]            = '%s';

        $updated = $this->wpdb->update(
            $this->messageTable(),
            $fields,
            ['id' => $messageId],
            $formats,
            ['%d']
        );

        return false !== $updated;
    }

    public function deleteMessage(int $messageId): bool
    {
        $this->wpdb->delete($this->attachmentTable(), ['message_id' => $messageId], ['%d']);
        $deleted = $this->wpdb->delete($this->messageTable(), ['id' => $messageId], ['%d']);
        return (bool) $deleted;
    }

    public function attachFiles(int $messageId, array $files): void
    {
        foreach ($files as $file) {
            $url = esc_url_raw($file['url'] ?? '');
            if (empty($url)) {
                continue;
            }

            $this->wpdb->insert(
                $this->attachmentTable(),
                [
                    'message_id' => $messageId,
                    'file_url' => $url,
                    'file_name' => sanitize_file_name($file['name'] ?? ''),
                    'mime_type' => sanitize_text_field($file['mime_type'] ?? ''),
                    'meta' => $this->encodeMeta($file['meta'] ?? null),
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    public function getAttachments(int $messageId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->attachmentTable()} WHERE message_id = %d ORDER BY id ASC",
                $messageId
            ),
            ARRAY_A
        ) ?: [];

        return array_map(function (array $row) {
            return [
                'id' => (int) $row['id'],
                'url' => esc_url_raw($row['file_url']),
                'name' => $row['file_name'],
                'mime_type' => $row['mime_type'],
                'meta' => $this->decodeMeta($row['meta']),
                'created_at' => $row['created_at'],
            ];
        }, $rows);
    }

    private function touchConversation(int $conversationId, string $senderType, bool $isRead): void
    {
        $this->wpdb->update(
            $this->conversationTable(),
            [
                'last_message_at' => $this->now(),
                'updated_at' => $this->now(),
            ],
            ['id' => $conversationId],
            ['%s', '%s'],
            ['%d']
        );

        if ($isRead) {
            return;
        }

        $column = 'supplier' === $senderType ? 'unread_admin_count' : 'unread_supplier_count';
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->conversationTable()} SET {$column} = {$column} + 1 WHERE id = %d",
                $conversationId
            )
        );
    }

    private function normalizeConversation(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['supplier_id'] = (int) $row['supplier_id'];
        $row['admin_user_id'] = isset($row['admin_user_id']) ? (int) $row['admin_user_id'] : null;
        $row['order_id'] = isset($row['order_id']) ? (int) $row['order_id'] : null;
        $row['unread_admin_count'] = (int) $row['unread_admin_count'];
        $row['unread_supplier_count'] = (int) $row['unread_supplier_count'];
        $row['meta'] = $this->decodeMeta($row['meta'] ?? null);
        $row['supplier_display_name'] = $row['supplier_display_name'] ?? null;
        $row['supplier_user_nicename'] = $row['supplier_user_nicename'] ?? null;
        if (empty($row['supplier_name']) && !empty($row['supplier_display_name'])) {
            $row['supplier_name'] = $row['supplier_display_name'];
        }
        if (empty($row['supplier_name']) && !empty($row['supplier_id'])) {
            $user = get_userdata((int) $row['supplier_id']);
            if ($user) {
                $row['supplier_name'] = $user->display_name ?: $user->user_login ?: $user->user_email;
                $row['supplier_display_name'] = $row['supplier_display_name'] ?: $row['supplier_name'];
            }
        }

        $preview = $row['last_message_preview'] ?? '';
        $preview = is_string($preview) ? wp_strip_all_tags($preview) : '';
        $row['last_message_preview'] = $preview ? wp_trim_words($preview, 40, '…') : '';
        $row['last_message_sender_type'] = isset($row['last_message_sender_type'])
            ? strtolower((string) $row['last_message_sender_type'])
            : null;
        $row['last_message_sender_id'] = isset($row['last_message_sender_id'])
            ? (int) $row['last_message_sender_id']
            : null;
        $row['last_message_created_at'] = $row['last_message_created_at'] ?? $row['last_message_at'] ?? null;

        return $row;
    }

    private function normalizeMessage(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['conversation_id'] = (int) $row['conversation_id'];
        $row['sender_id'] = isset($row['sender_id']) ? (int) $row['sender_id'] : null;
        $row['is_read'] = (bool) $row['is_read'];
        $row['attachments'] = $this->decodeMeta($row['attachments'] ?? null);

        return $row;
    }

    private function enrichMessageSender(array $message, ?array $conversation = null): array
    {
        if (!empty($message['sender_name'])) {
            return $message;
        }

        $senderType = $message['sender_type'] ?? 'supplier';
        $senderId = isset($message['sender_id']) ? (int) $message['sender_id'] : 0;

        if (!$senderId && $conversation) {
            if ('supplier' === $senderType) {
                $senderId = (int) ($conversation['supplier_id'] ?? 0);
            } else {
                $senderId = (int) ($conversation['admin_user_id'] ?? 0);
            }
        }

        if ($senderId) {
            $user = get_userdata($senderId);
            if ($user) {
                $message['sender_name'] = $user->display_name ?: $user->user_login ?: $user->user_email;
                return $message;
            }
        }

        if ('supplier' === $senderType) {
            $message['sender_name'] = __('Supplier', HAVEN_CORE_TEXT_DOMAIN);
        } else {
            $message['sender_name'] = __('Store Admin', HAVEN_CORE_TEXT_DOMAIN);
        }

        return $message;
    }

    private function encodeMeta($data): ?string
    {
        if (empty($data)) {
            return null;
        }

        $encoded = wp_json_encode($data);
        return false === $encoded ? null : $encoded;
    }

    private function decodeMeta(?string $payload)
    {
        if (empty($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function conversationTable(): string
    {
        return HC_Database_Schema::table('conversations');
    }

    private function messageTable(): string
    {
        return HC_Database_Schema::table('messages');
    }

    private function attachmentTable(): string
    {
        return HC_Database_Schema::table('message_attachments');
    }

    private function now(): string
    {
        return current_time('mysql', true);
    }
}
