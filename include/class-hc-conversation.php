<?php

namespace HavenCore\Classes;

use HavenCore\Abstracts\HC_Data;

defined('ABSPATH') || exit;

/**
 * Conversation domain object backed by the messaging tables.
 */
class HC_Conversation extends HC_Data
{
    protected $object_type = 'conversation';

    protected $data = [
        'supplier_id'           => 0,
        'admin_user_id'         => null,
        'order_id'              => null,
        'subject'               => '',
        'status'                => 'open',
        'last_message_at'       => null,
        'unread_admin_count'    => 0,
        'unread_supplier_count' => 0,
        'meta'                  => [],
        'created_at'            => null,
        'updated_at'            => null,
    ];

    public function __construct($conversation = 0)
    {
        if ($conversation instanceof self) {
            $this->set_id($conversation->get_id());
        } elseif (is_numeric($conversation)) {
            $this->set_id((int) $conversation);
        }

        $this->data_store = HC_Data_Store::load('conversation');

        if ($this->get_id()) {
            try {
                $this->data_store->read($this);
            } catch (\Exception $e) {
                $this->set_object_read(true);
            }
        } else {
            $this->set_object_read(true);
        }
    }

    public function save(): void
    {
        if ($this->get_id()) {
            $this->data_store->update($this);
        } else {
            $this->data_store->create($this);
        }
    }

    public function hydrate(array $record): void
    {
        if (isset($record['id'])) {
            $this->set_id((int) $record['id']);
        }

        foreach ($record as $key => $value) {
            if (array_key_exists($key, $this->data)) {
                if ('meta' === $key && !is_array($value)) {
                    $value = (array) $value;
                }
                $this->data[$key] = $value;
            }
        }

        $this->changes = [];
        $this->set_object_read(true);
    }

    public function to_array(): array
    {
        return array_merge($this->data, $this->changes);
    }

    public function get_supplier_id(): int
    {
        return (int) ($this->get_prop('supplier_id') ?? 0);
    }

    public function set_supplier_id(int $supplierId): void
    {
        $this->set_prop('supplier_id', absint($supplierId));
    }

    public function get_admin_user_id(): ?int
    {
        $value = $this->get_prop('admin_user_id');
        return null === $value ? null : (int) $value;
    }

    public function set_admin_user_id(?int $adminId): void
    {
        $this->set_prop('admin_user_id', $adminId ? absint($adminId) : null);
    }

    public function get_order_id(): ?int
    {
        $value = $this->get_prop('order_id');
        return null === $value ? null : (int) $value;
    }

    public function set_order_id(?int $orderId): void
    {
        $this->set_prop('order_id', $orderId ? absint($orderId) : null);
    }

    public function get_subject(): string
    {
        return (string) ($this->get_prop('subject') ?? '');
    }

    public function set_subject(string $subject): void
    {
        $this->set_prop('subject', sanitize_text_field($subject));
    }

    public function get_status(): string
    {
        return (string) ($this->get_prop('status') ?? 'open');
    }

    public function set_status(string $status): void
    {
        $this->set_prop('status', sanitize_key($status));
    }

    public function get_last_message_at(): ?string
    {
        $value = $this->get_prop('last_message_at');
        return $value ? (string) $value : null;
    }

    public function set_last_message_at(?string $timestamp): void
    {
        $this->set_prop('last_message_at', $timestamp);
    }

    public function get_unread_admin_count(): int
    {
        return (int) ($this->get_prop('unread_admin_count') ?? 0);
    }

    public function set_unread_admin_count(int $count): void
    {
        $this->set_prop('unread_admin_count', max(0, $count));
    }

    public function get_unread_supplier_count(): int
    {
        return (int) ($this->get_prop('unread_supplier_count') ?? 0);
    }

    public function set_unread_supplier_count(int $count): void
    {
        $this->set_prop('unread_supplier_count', max(0, $count));
    }

    public function get_meta(): array
    {
        $meta = $this->get_prop('meta');
        return is_array($meta) ? $meta : [];
    }

    public function set_meta(array $meta): void
    {
        $this->set_prop('meta', $meta);
    }

    public function get_created_at(): ?string
    {
        $value = $this->get_prop('created_at');
        return $value ? (string) $value : null;
    }

    public function set_created_at(?string $value): void
    {
        $this->set_prop('created_at', $value);
    }

    public function get_updated_at(): ?string
    {
        $value = $this->get_prop('updated_at');
        return $value ? (string) $value : null;
    }

    public function set_updated_at(?string $value): void
    {
        $this->set_prop('updated_at', $value);
    }
}
