<?php

namespace Nexus\Models;

use Nexus\Core\Database;

/**
 * AI Message Model
 *
 * Manages individual messages within AI conversations.
 */
class AiMessage
{
    /**
     * Create a new message
     */
    public static function create(int $conversationId, string $role, string $content, array $data = []): int
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            INSERT INTO ai_messages
            (conversation_id, role, content, tokens_used, model, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $conversationId,
            $role,
            $content,
            $data['tokens_used'] ?? 0,
            $data['model'] ?? null,
        ]);

        // Touch the conversation's updated_at
        AiConversation::touch($conversationId);

        return (int) $db->lastInsertId();
    }

    /**
     * Create a user message
     */
    public static function createUserMessage(int $conversationId, string $content): int
    {
        return self::create($conversationId, 'user', $content);
    }

    /**
     * Create an assistant message
     */
    public static function createAssistantMessage(int $conversationId, string $content, array $data = []): int
    {
        return self::create($conversationId, 'assistant', $content, $data);
    }

    /**
     * Create a system message
     */
    public static function createSystemMessage(int $conversationId, string $content): int
    {
        return self::create($conversationId, 'system', $content);
    }

    /**
     * Get a message by ID
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ai_messages WHERE id = ?");
        $stmt->execute([$id]);

        $message = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $message ?: null;
    }

    /**
     * Get all messages for a conversation
     */
    public static function getByConversationId(int $conversationId, int $limit = 100): array
    {
        $db = Database::getConnection();
        $limit = (int) $limit;

        $stmt = $db->prepare("
            SELECT * FROM ai_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT {$limit}
        ");

        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent messages for context (for AI)
     */
    public static function getRecentForContext(int $conversationId, int $maxMessages = 20): array
    {
        $db = Database::getConnection();
        $maxMessages = (int) $maxMessages;

        // Get last N messages
        $stmt = $db->prepare("
            SELECT role, content FROM ai_messages
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT {$maxMessages}
        ");

        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Reverse to get chronological order
        return array_reverse($messages);
    }

    /**
     * Update a message
     */
    public static function update(int $id, array $data): bool
    {
        $db = Database::getConnection();

        $fields = [];
        $values = [];

        foreach (['content', 'tokens_used', 'model'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $values[] = $id;

        $sql = "UPDATE ai_messages SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Delete a message
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM ai_messages WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count messages in a conversation
     */
    public static function countByConversationId(int $conversationId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ?");
        $stmt->execute([$conversationId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get total tokens used in a conversation
     */
    public static function getTotalTokens(int $conversationId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT SUM(tokens_used) FROM ai_messages WHERE conversation_id = ?");
        $stmt->execute([$conversationId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete all messages in a conversation
     */
    public static function deleteByConversationId(int $conversationId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM ai_messages WHERE conversation_id = ?");
        return $stmt->execute([$conversationId]);
    }
}
