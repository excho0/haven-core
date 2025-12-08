<?php

namespace HavenCore\Classes;

use HavenCore\Abstracts\HC_Data;

defined('ABSPATH') || exit;

/**
 * Message domain object for supplier/admin conversations.
 */
class HC_Message extends HC_Data
{
    protected $object_type = 'message';

    protected $data = [
        'conversation_id' => 0,
        'sender_type'     => 'supplier',
        'sender_id'       => null,
        'message'         => '',
        'attachments'     => null,
        'is_read'         => false,
        'files'           => [],
        'created_at'      => null,
        'updated_at'      => null,
    ];

    public function __construct($message = 0)
    {
        if ($message instanceof self) {
            $this->set_id($message->get_id());
        } elseif (is_numeric($message)) {
            $this->set_id((int) $message);
        }

        $this->data_store = HC_Data_Store::load('message');

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
                if (in_array($key, ['attachments', 'files'], true) && !is_array($value)) {
                    $value = $value ? (array) $value : [];
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

    public function get_conversation_id(): int
    {
        return (int) ($this->get_prop('conversation_id') ?? 0);
    }

    public function set_conversation_id(int $conversationId): void
    {
        $this->set_prop('conversation_id', absint($conversationId));
    }

    public function get_sender_type(): string
    {
        return (string) ($this->get_prop('sender_type') ?? 'supplier');
    }

    public function set_sender_type(string $senderType): void
    {
        $this->set_prop('sender_type', sanitize_key($senderType));
    }

    public function get_sender_id(): ?int
    {
        $value = $this->get_prop('sender_id');
        return null === $value ? null : (int) $value;
    }

    public function set_sender_id(?int $senderId): void
    {
        $this->set_prop('sender_id', $senderId ? absint($senderId) : null);
    }

    public function get_body(): string
    {
        return (string) ($this->get_prop('message') ?? '');
    }

    public function set_body(string $body): void
    {
        $this->set_prop('message', wp_kses_post($body));
    }

    public function is_read(): bool
    {
        return (bool) ($this->get_prop('is_read') ?? false);
    }

    public function set_is_read(bool $isRead): void
    {
        $this->set_prop('is_read', $isRead);
    }

    public function get_attachments()
    {
        return $this->get_prop('attachments');
    }

    public function set_attachments($attachments): void
    {
        $this->set_prop('attachments', $attachments);
    }

    public function get_files(): array
    {
        $files = $this->get_prop('files');
        return is_array($files) ? $files : [];
    }

    public function set_files(array $files): void
    {
        $this->set_prop('files', $files);
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
