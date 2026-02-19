<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AI Conversation Model
 *
 * Manages AI chat conversations and their messages.
 */
class AiConversation
{
    /**
     * Create a new conversation
     */
    public static function create(int $userId, array $data = []): int
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            INSERT INTO ai_conversations
            (tenant_id, user_id, title, provider, model, context_type, context_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $userId,
            $data['title'] ?? 'New Chat',
            $data['provider'] ?? null,
            $data['model'] ?? null,
            $data['context_type'] ?? 'general',
            $data['context_id'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get a conversation by ID
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            SELECT * FROM ai_conversations
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);

        $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $conversation ?: null;
    }

    /**
     * Get a conversation with its messages
     */
    public static function getWithMessages(int $id): ?array
    {
        $conversation = self::findById($id);

        if (!$conversation) {
            return null;
        }

        $conversation['messages'] = AiMessage::getByConversationId($id);
        return $conversation;
    }

    /**
     * Get all conversations for a user
     */
    public static function getByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        $stmt = $db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM ai_messages WHERE conversation_id = c.id) as message_count,
                   (SELECT content FROM ai_messages WHERE conversation_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message
            FROM ai_conversations c
            WHERE c.tenant_id = ? AND c.user_id = ?
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $stmt->execute([$tenantId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update conversation
     */
    public static function update(int $id, array $data): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $fields = [];
        $values = [];

        foreach (['title', 'provider', 'model'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        $values[] = $tenantId;

        $sql = "UPDATE ai_conversations SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $db->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Update the title based on first message
     */
    public static function updateTitleFromContent(int $id, string $content): bool
    {
        // Generate a short title from the content
        $title = mb_substr(strip_tags($content), 0, 50);
        if (mb_strlen($content) > 50) {
            $title .= '...';
        }

        return self::update($id, ['title' => $title]);
    }

    /**
     * Touch updated_at timestamp
     */
    public static function touch(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Delete a conversation and its messages
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Messages are deleted via CASCADE
        $stmt = $db->prepare("DELETE FROM ai_conversations WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tenantId]);
    }

    /**
     * Delete all conversations for a user
     */
    public static function deleteAllForUser(int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("DELETE FROM ai_conversations WHERE tenant_id = ? AND user_id = ?");
        return $stmt->execute([$tenantId, $userId]);
    }

    /**
     * Check if user owns the conversation
     */
    public static function belongsToUser(int $id, int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("SELECT 1 FROM ai_conversations WHERE id = ? AND tenant_id = ? AND user_id = ?");
        $stmt->execute([$id, $tenantId, $userId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Count conversations for a user
     */
    public static function countByUserId(int $userId): int
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_conversations WHERE tenant_id = ? AND user_id = ?");
        $stmt->execute([$tenantId, $userId]);

        return (int) $stmt->fetchColumn();
    }
}
