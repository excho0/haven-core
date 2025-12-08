<?php

namespace HavenCore\DataStores;

use HavenCore\Interfaces\HC_Object_Data_Store_Interface;
use HavenCore\Classes\HC_Conversation;
use HavenCore\Services\HC_Messaging_Service;

defined('ABSPATH') || exit;

class HC_Conversation_Data_Store implements HC_Object_Data_Store_Interface
{
    private ?HC_Messaging_Service $service = null;

    public function create(&$conversation)
    {
        $record = $this->service()->createConversation($conversation->to_array());
        if (!$record) {
            throw new \RuntimeException('Unable to create conversation.');
        }

        $conversation->hydrate($record);
    }

    public function read(&$conversation)
    {
        $record = $this->service()->getConversation($conversation->get_id());
        if (!$record) {
            throw new \RuntimeException('Conversation not found.');
        }

        $conversation->hydrate($record);
    }

    public function update(&$conversation)
    {
        $changes = $conversation->get_changes();
        if (empty($changes)) {
            return;
        }

        $this->service()->updateConversation($conversation->get_id(), $changes);
        $conversation->apply_changes();
        $fresh = $this->service()->getConversation($conversation->get_id());
        if ($fresh) {
            $conversation->hydrate($fresh);
        }
    }

    public function delete(&$conversation, $args = array())
    {
        $this->service()->deleteConversation($conversation->get_id());
    }

    public function read_meta(&$conversation)
    {
        return $conversation->get_meta();
    }

    public function delete_meta(&$conversation, $meta)
    {
        if (empty($meta->key)) {
            return false;
        }

        $metaData = $conversation->get_meta();
        if (!array_key_exists($meta->key, $metaData)) {
            return false;
        }

        unset($metaData[$meta->key]);
        $conversation->set_meta($metaData);
        $this->service()->updateConversation($conversation->get_id(), ['meta' => $metaData]);
        return true;
    }

    public function add_meta(&$conversation, $meta)
    {
        if (empty($meta->key)) {
            return 0;
        }

        $metaData = $conversation->get_meta();
        $metaData[$meta->key] = $meta->value ?? null;
        $conversation->set_meta($metaData);
        $this->service()->updateConversation($conversation->get_id(), ['meta' => $metaData]);
        return 1;
    }

    public function update_meta(&$conversation, $meta = null)
    {
        if (!$meta || empty($meta->key)) {
            $this->service()->updateConversation($conversation->get_id(), ['meta' => $conversation->get_meta()]);
            return;
        }

        $metaData = $conversation->get_meta();
        $metaData[$meta->key] = $meta->value ?? null;
        $conversation->set_meta($metaData);
        $this->service()->updateConversation($conversation->get_id(), ['meta' => $metaData]);
    }

    private function service(): HC_Messaging_Service
    {
        if (!$this->service) {
            $this->service = new HC_Messaging_Service();
        }

        return $this->service;
    }
}
