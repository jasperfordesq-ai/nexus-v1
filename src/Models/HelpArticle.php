<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use PDO;

class HelpArticle
{
    /**
     * Get all articles, optionally filtered by module tags.
     *
     * @param array $allowedModules List of active modules for the current tenant.
     * @return array
     */
    public static function getAll(array $allowedModules = [])
    {
        $db = Database::getConnection();

        // Always include 'core' and 'getting_started'
        $allowedModules[] = 'core';
        $allowedModules[] = 'getting_started';

        // Ensure array is unique and re-indexed (0, 1, 2...) for PDO
        $allowedModules = array_values(array_unique($allowedModules));

        // Create placeholders for prepared statement
        $placeholders = implode(',', array_fill(0, count($allowedModules), '?'));

        $sql = "SELECT * FROM help_articles
                WHERE is_public = 1
                AND module_tag IN ($placeholders)
                ORDER BY module_tag ASC, title ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($allowedModules);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific article by slug.
     *
     * @param string $slug
     * @return array|false
     */
    public static function findBySlug($slug)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM help_articles WHERE slug = ? AND is_public = 1 LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get article by ID
     *
     * @param int $id
     * @return array|false
     */
    public static function findById($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM help_articles WHERE id = ? AND is_public = 1 LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search articles.
     *
     * @param string $query
     * @param array $allowedModules
     * @return array
     */
    public static function search($query, array $allowedModules)
    {
        $db = Database::getConnection();
        $allowedModules[] = 'core';
        $allowedModules[] = 'getting_started';

        // Ensure array is unique and re-indexed
        $allowedModules = array_values(array_unique($allowedModules));

        $placeholders = implode(',', array_fill(0, count($allowedModules), '?'));

        $sql = "SELECT * FROM help_articles
                WHERE is_public = 1
                AND module_tag IN ($placeholders)
                AND (title LIKE ? OR content LIKE ?)
                ORDER BY title ASC";

        $stmt = $db->prepare($sql);

        // Merge allowed modules with search params
        $params = array_merge($allowedModules, ["%$query%", "%$query%"]);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get related articles from the same module (excluding current article)
     *
     * @param string $moduleTag
     * @param int $excludeId
     * @param int $limit
     * @return array
     */
    public static function getRelated($moduleTag, $excludeId, $limit = 5)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, title, slug, module_tag FROM help_articles
                              WHERE is_public = 1
                              AND module_tag = ?
                              AND id != ?
                              ORDER BY view_count DESC, title ASC
                              LIMIT ?");
        $stmt->execute([$moduleTag, $excludeId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get popular articles (most viewed)
     *
     * @param array $allowedModules
     * @param int $limit
     * @return array
     */
    public static function getPopular(array $allowedModules, $limit = 5)
    {
        $db = Database::getConnection();
        $allowedModules[] = 'core';
        $allowedModules[] = 'getting_started';

        // Ensure array is unique and re-indexed
        $allowedModules = array_values(array_unique($allowedModules));

        $placeholders = implode(',', array_fill(0, count($allowedModules), '?'));

        // Cast limit to int and embed directly (safe since we control it)
        $limit = (int) $limit;

        $sql = "SELECT id, title, slug, module_tag, view_count FROM help_articles
                WHERE is_public = 1
                AND module_tag IN ($placeholders)
                ORDER BY view_count DESC, title ASC
                LIMIT $limit";

        $stmt = $db->prepare($sql);
        $stmt->execute($allowedModules);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Increment view count for an article
     *
     * @param int $id
     * @return bool
     */
    public static function incrementViewCount($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE help_articles SET view_count = view_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Record article feedback
     *
     * @param int $articleId
     * @param bool $helpful
     * @param int|null $userId
     * @param string|null $ipAddress
     * @return bool
     */
    public static function recordFeedback($articleId, $helpful, $userId = null, $ipAddress = null)
    {
        $db = Database::getConnection();

        // Check if this user/IP already gave feedback on this article
        if ($userId) {
            $stmt = $db->prepare("SELECT id FROM help_article_feedback WHERE article_id = ? AND user_id = ?");
            $stmt->execute([$articleId, $userId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM help_article_feedback WHERE article_id = ? AND ip_address = ? AND user_id IS NULL");
            $stmt->execute([$articleId, $ipAddress]);
        }

        if ($stmt->fetch()) {
            return false; // Already submitted feedback
        }

        $stmt = $db->prepare("INSERT INTO help_article_feedback (article_id, helpful, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$articleId, $helpful ? 1 : 0, $userId, $ipAddress]);
    }

    /**
     * Get feedback stats for an article
     *
     * @param int $articleId
     * @return array
     */
    public static function getFeedbackStats($articleId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT
                                SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful_count,
                                SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) as not_helpful_count,
                                COUNT(*) as total_feedback
                              FROM help_article_feedback
                              WHERE article_id = ?");
        $stmt->execute([$articleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'helpful' => (int)($result['helpful_count'] ?? 0),
            'not_helpful' => (int)($result['not_helpful_count'] ?? 0),
            'total' => (int)($result['total_feedback'] ?? 0)
        ];
    }
}
