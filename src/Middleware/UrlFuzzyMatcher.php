<?php

namespace Nexus\Middleware;

use Nexus\Core\Database;

/**
 * URL Fuzzy Matcher Middleware
 * Attempts to find similar URLs when a 404 occurs
 */
class UrlFuzzyMatcher
{
    /**
     * Common URL pattern fixes
     */
    private static $patterns = [
        // Singular to plural
        '/^\/listing\//' => '/listings/',
        '/^\/group\//' => '/groups/',
        '/^\/blog\//' => '/news/',

        // Old forum URLs
        '/\/forum\//' => '/discussions/',

        // Trailing slashes
        '/\/$/' => '',

        // Common typos
        '/volunt[ae]er/' => 'volunteer',
        '/gard[ae]ning/' => 'gardening',
    ];

    /**
     * Try to find a matching URL for a 404 request
     *
     * @param string $requestedUrl The URL that returned 404
     * @return string|null Suggested URL or null if no match found
     */
    public static function findSuggestion($requestedUrl)
    {
        // Try pattern-based corrections first
        $corrected = self::tryPatternCorrection($requestedUrl);
        if ($corrected && $corrected !== $requestedUrl) {
            return $corrected;
        }

        // Try finding similar content in database
        $dbSuggestion = self::findSimilarContent($requestedUrl);
        if ($dbSuggestion) {
            return $dbSuggestion;
        }

        return null;
    }

    /**
     * Try to correct URL using pattern matching
     *
     * @param string $url Original URL
     * @return string Corrected URL
     */
    private static function tryPatternCorrection($url)
    {
        $corrected = $url;

        foreach (self::$patterns as $pattern => $replacement) {
            $corrected = preg_replace($pattern, $replacement, $corrected);
        }

        return $corrected;
    }

    /**
     * Find similar content in database
     *
     * @param string $url Original URL
     * @return string|null Suggested URL or null
     */
    private static function findSimilarContent($url)
    {
        $db = Database::getInstance();

        // Extract path segments and search terms
        $segments = array_filter(explode('/', trim($url, '/')));

        if (empty($segments)) {
            return null;
        }

        $lastSegment = end($segments);

        // Try to find matching content by slug
        try {
            // Check help articles
            if (count($segments) > 0 && $segments[0] === 'help') {
                $stmt = $db->prepare("
                    SELECT slug FROM help_articles
                    WHERE slug LIKE ? OR title LIKE ?
                    LIMIT 1
                ");
                $searchTerm = '%' . $lastSegment . '%';
                $stmt->execute([$searchTerm, $searchTerm]);
                $result = $stmt->fetch();

                if ($result) {
                    return '/help/' . $result['slug'];
                }
            }

            // Check blog posts
            if (count($segments) > 0 && ($segments[0] === 'blog' || $segments[0] === 'news')) {
                $stmt = $db->prepare("
                    SELECT slug FROM posts
                    WHERE slug LIKE ? OR title LIKE ?
                    LIMIT 1
                ");
                $searchTerm = '%' . $lastSegment . '%';
                $stmt->execute([$searchTerm, $searchTerm]);
                $result = $stmt->fetch();

                if ($result) {
                    return '/news/' . $result['slug'];
                }
            }

            // Check listings (by ID if it looks like a number, otherwise by title)
            if (count($segments) > 0 && $segments[0] === 'listings') {
                if (is_numeric($lastSegment)) {
                    $stmt = $db->prepare("SELECT id FROM listings WHERE id = ? LIMIT 1");
                    $stmt->execute([$lastSegment]);
                    $result = $stmt->fetch();

                    if ($result) {
                        return '/listings/' . $result['id'];
                    }
                } else {
                    // Try to find by title
                    $stmt = $db->prepare("
                        SELECT id FROM listings
                        WHERE title LIKE ? OR description LIKE ?
                        LIMIT 1
                    ");
                    $searchTerm = '%' . str_replace('-', ' ', $lastSegment) . '%';
                    $stmt->execute([$searchTerm, $searchTerm]);
                    $result = $stmt->fetch();

                    if ($result) {
                        return '/listings/' . $result['id'];
                    }
                }
            }

            // Check groups
            if (count($segments) > 0 && $segments[0] === 'groups') {
                if (is_numeric($lastSegment)) {
                    $stmt = $db->prepare("SELECT id FROM groups WHERE id = ? LIMIT 1");
                    $stmt->execute([$lastSegment]);
                    $result = $stmt->fetch();

                    if ($result) {
                        return '/groups/' . $result['id'];
                    }
                } else {
                    // Try to find by name
                    $stmt = $db->prepare("
                        SELECT id FROM groups
                        WHERE name LIKE ? OR description LIKE ?
                        LIMIT 1
                    ");
                    $searchTerm = '%' . str_replace('-', ' ', $lastSegment) . '%';
                    $stmt->execute([$searchTerm, $searchTerm]);
                    $result = $stmt->fetch();

                    if ($result) {
                        return '/groups/' . $result['id'];
                    }
                }
            }

        } catch (\Exception $e) {
            // Silently fail - don't break the application
            error_log('Fuzzy matcher error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Calculate Levenshtein distance between two strings
     * (for future fuzzy string matching)
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return int Distance
     */
    public static function calculateDistance($str1, $str2)
    {
        return levenshtein(strtolower($str1), strtolower($str2));
    }
}
