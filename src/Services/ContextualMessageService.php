<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ContextualMessageService - Context awareness for messages
 *
 * Messages can reference a specific listing, event, job, volunteering opportunity, etc.
 * via `context_type` and `context_id` columns on the messages table.
 *
 * Features:
 * - Send messages with context (e.g., "Re: Gardening Help listing")
 * - Show context card in message thread
 * - Pre-fill context when messaging from a listing/event page
 * - Retrieve context info for display
 *
 * Valid context types: listing, event, job, volunteering, group
 */
class ContextualMessageService
{
    /** Valid context types */
    private const VALID_TYPES = ['listing', 'event', 'job', 'volunteering', 'group'];

    /**
     * Send a message with context
     *
     * @param int $senderId
     * @param int $receiverId
     * @param string $body Message body
     * @param string|null $contextType Type of referenced entity
     * @param int|null $contextId ID of referenced entity
     * @param string $subject Optional subject
     * @return int|null Message ID or null on failure
     */
    public static function sendWithContext(
        int $senderId,
        int $receiverId,
        string $body,
        ?string $contextType = null,
        ?int $contextId = null,
        string $subject = ''
    ): ?int {
        $tenantId = TenantContext::getId();

        // Validate context type
        if ($contextType !== null && !in_array($contextType, self::VALID_TYPES)) {
            $contextType = null;
            $contextId = null;
        }

        // If context_type is set but context_id is not, clear both
        if ($contextType !== null && $contextId === null) {
            $contextType = null;
        }

        // Create the base message using existing MessageService/Model
        $messageId = \Nexus\Models\Message::create($tenantId, $senderId, $receiverId, $subject, $body);

        // Attach context if provided
        if ($messageId && $contextType !== null && $contextId !== null) {
            try {
                $db = Database::getConnection();
                $db->prepare("UPDATE messages SET context_type = ?, context_id = ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$contextType, $contextId, $messageId, $tenantId]);

                // Also update listing_id for backward compatibility
                if ($contextType === 'listing') {
                    $db->prepare("UPDATE messages SET listing_id = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$contextId, $messageId, $tenantId]);
                }
            } catch (\Exception $e) {
                error_log("ContextualMessageService: Failed to attach context: " . $e->getMessage());
                // Message still sent, just without context
            }
        }

        return $messageId ? (int)$messageId : null;
    }

    /**
     * Get context info for a message (for display in UI)
     *
     * @param string $contextType
     * @param int $contextId
     * @return array|null Context card data or null if not found
     */
    public static function getContextInfo(string $contextType, int $contextId): ?array
    {
        if (!in_array($contextType, self::VALID_TYPES)) {
            return null;
        }

        try {
            switch ($contextType) {
                case 'listing':
                    return self::getListingContext($contextId);
                case 'event':
                    return self::getEventContext($contextId);
                case 'job':
                    return self::getJobContext($contextId);
                case 'volunteering':
                    return self::getVolunteeringContext($contextId);
                case 'group':
                    return self::getGroupContext($contextId);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            error_log("ContextualMessageService::getContextInfo error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get context info for multiple messages in batch
     *
     * @param array $contextPairs Array of ['type' => string, 'id' => int]
     * @return array Keyed by "type:id"
     */
    public static function getContextInfoBatch(array $contextPairs): array
    {
        $results = [];

        foreach ($contextPairs as $pair) {
            $key = $pair['type'] . ':' . $pair['id'];
            if (!isset($results[$key])) {
                $info = self::getContextInfo($pair['type'], (int)$pair['id']);
                if ($info) {
                    $results[$key] = $info;
                }
            }
        }

        return $results;
    }

    /**
     * Get messages with context for a conversation thread
     * Enhances existing messages with context card data
     *
     * @param array $messages Array of message data
     * @return array Messages with context_info added
     */
    public static function enrichMessagesWithContext(array $messages): array
    {
        // Collect unique context pairs
        $contextPairs = [];
        foreach ($messages as $msg) {
            if (!empty($msg['context_type']) && !empty($msg['context_id'])) {
                $contextPairs[] = ['type' => $msg['context_type'], 'id' => (int)$msg['context_id']];
            }
        }

        if (empty($contextPairs)) {
            return $messages;
        }

        // Batch fetch context info
        $contextData = self::getContextInfoBatch($contextPairs);

        // Attach to messages
        foreach ($messages as &$msg) {
            if (!empty($msg['context_type']) && !empty($msg['context_id'])) {
                $key = $msg['context_type'] . ':' . $msg['context_id'];
                $msg['context_info'] = $contextData[$key] ?? null;
            } else {
                $msg['context_info'] = null;
            }
        }

        return $messages;
    }

    // =========================================================================
    // CONTEXT RESOLVERS
    // =========================================================================

    private static function getListingContext(int $id): ?array
    {
        $row = Database::query(
            "SELECT l.id, l.title, l.type, l.description, u.name as user_name
             FROM listings l JOIN users u ON l.user_id = u.id
             WHERE l.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'type' => 'listing',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'subtitle' => ucfirst($row['type'] ?? 'listing') . ' by ' . $row['user_name'],
            'description' => mb_substr($row['description'] ?? '', 0, 120),
            'link' => '/listings/' . $row['id'],
        ];
    }

    private static function getEventContext(int $id): ?array
    {
        $row = Database::query(
            "SELECT e.id, e.title, e.start_time, e.location
             FROM events e WHERE e.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $subtitle = 'Event';
        if (!empty($row['start_time'])) {
            $subtitle .= ' on ' . date('M j, Y', strtotime($row['start_time']));
        }

        return [
            'type' => 'event',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'subtitle' => $subtitle,
            'description' => $row['location'] ?? '',
            'link' => '/events/' . $row['id'],
        ];
    }

    private static function getJobContext(int $id): ?array
    {
        $row = Database::query(
            "SELECT j.id, j.title, j.location, o.name as org_name
             FROM job_vacancies j LEFT JOIN organizations o ON j.organization_id = o.id
             WHERE j.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'type' => 'job',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'subtitle' => $row['org_name'] ? 'at ' . $row['org_name'] : 'Job vacancy',
            'description' => $row['location'] ?? '',
            'link' => '/jobs/' . $row['id'],
        ];
    }

    private static function getVolunteeringContext(int $id): ?array
    {
        $row = Database::query(
            "SELECT v.id, v.title, v.location, o.name as org_name
             FROM volunteer_opportunities v LEFT JOIN organizations o ON v.organization_id = o.id
             WHERE v.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'type' => 'volunteering',
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'subtitle' => $row['org_name'] ? 'with ' . $row['org_name'] : 'Volunteer opportunity',
            'description' => $row['location'] ?? '',
            'link' => '/volunteering/' . $row['id'],
        ];
    }

    private static function getGroupContext(int $id): ?array
    {
        $row = Database::query(
            "SELECT g.id, g.name, g.description, g.image_url
             FROM `groups` g WHERE g.id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'type' => 'group',
            'id' => (int)$row['id'],
            'title' => $row['name'],
            'subtitle' => 'Group',
            'description' => mb_substr($row['description'] ?? '', 0, 120),
            'link' => '/groups/' . $row['id'],
        ];
    }
}
