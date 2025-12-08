<?php

namespace HavenCore\DataStores;

use HavenCore\Interfaces\HC_Object_Data_Store_Interface;
use HavenCore\Classes\HC_Message;
use HavenCore\Services\HC_Messaging_Service;

defined('ABSPATH') || exit;

class HC_Message_Data_Store implements HC_Object_Data_Store_Interface
{
    private ?HC_Messaging_Service $service = null;

    public function create(&$message)
    {
        $payload        = $message->to_array();
        $conversationId = (int) $payload['conversation_id'];
        unset($payload['conversation_id']);

        $record = $this->service()->createMessage($conversationId, $payload);
        if (!$record) {
            throw new \RuntimeException('Unable to create message.');
        }

        $message->hydrate($record);
    }

    public function read(&$message)
    {
        $record = $this->service()->getMessage($message->get_id());
        if (!$record) {
            throw new \RuntimeException('Message not found.');
        }

        $message->hydrate($record);
    }

    public function update(&$message)
    {
        $changes = $message->get_changes();
        if (empty($changes)) {
            return;
        }

        $this->service()->updateMessage($message->get_id(), $changes);
        $message->apply_changes();
        $fresh = $this->service()->getMessage($message->get_id());
        if ($fresh) {
            $message->hydrate($fresh);
        }
    }

    public function delete(&$message, $args = array())
    {
        $this->service()->deleteMessage($message->get_id());
    }

    public function read_meta(&$message)
    {
        return [];
    }

    public function delete_meta(&$message, $meta)
    {
        return false;
    }

    public function add_meta(&$message, $meta)
    {
        return 0;
    }

    public function update_meta(&$message, $meta)
    {
        return;
    }

    private function service(): HC_Messaging_Service
    {
        if (!$this->service) {
            $this->service = new HC_Messaging_Service();
        }

        return $this->service;
    }
}
