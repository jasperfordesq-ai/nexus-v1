<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Models\AiSettings;
use Nexus\Services\AI\Providers\OpenAIProvider;

/**
 * EmbeddingService — Semantic Vector Embeddings
 *
 * Uses OpenAI text-embedding-3-small to generate vector representations of
 * listings and user profiles, enabling semantic similarity search beyond
 * simple keyword matching.
 *
 * Embeddings are persisted in the content_embeddings table (see migration
 * 2026_03_05_create_content_embeddings.sql) and retrieved via cosine similarity.
 *
 * Algorithm references:
 *   - OpenAI embeddings: https://platform.openai.com/docs/guides/embeddings
 *   - Cosine similarity: https://en.wikipedia.org/wiki/Cosine_similarity
 *
 * Usage:
 *   // Generate and store
 *   EmbeddingService::generateForListing($listing);
 *   EmbeddingService::generateForUser($user);
 *
 *   // Query for similar content
 *   $similarIds = EmbeddingService::findSimilar($listingId, 'listing', $tenantId, 5);
 */
class EmbeddingService
{
    private const MODEL = 'text-embedding-3-small';

    // =========================================================================
    // GENERATE & STORE
    // =========================================================================

    /**
     * Generate and persist an embedding for a listing.
     * Skips silently if OpenAI is not configured for this tenant.
     *
     * @param array $listing  Listing row (must have id, title, description, tenant_id)
     */
    public static function generateForListing(array $listing): void
    {
        $text = self::listingToText($listing);
        self::store((int)$listing['tenant_id'], 'listing', (int)$listing['id'], $text);
    }

    /**
     * Generate and persist an embedding for a user profile.
     * Skips silently if OpenAI is not configured for this tenant.
     *
     * @param array $user  User row (must have id, bio, skills, tenant_id)
     */
    public static function generateForUser(array $user): void
    {
        $text = self::userToText($user);
        self::store((int)$user['tenant_id'], 'user', (int)$user['id'], $text);
    }

    // =========================================================================
    // FIND SIMILAR
    // =========================================================================

    /**
     * Find content IDs most semantically similar to the given item.
     *
     * @param int    $contentId    ID of the source item
     * @param string $contentType  'listing' | 'user' | 'event' | 'group'
     * @param int    $tenantId     Tenant scope
     * @param int    $limit        Max results
     * @return int[]               Similar content IDs (most similar first)
     */
    public static function findSimilar(
        int $contentId,
        string $contentType,
        int $tenantId,
        int $limit = 5
    ): array {
        // Load source embedding
        $sourceRow = Database::query(
            "SELECT embedding FROM content_embeddings
             WHERE tenant_id = ? AND content_type = ? AND content_id = ?",
            [$tenantId, $contentType, $contentId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$sourceRow || empty($sourceRow['embedding'])) {
            return [];
        }

        $sourceVec = json_decode($sourceRow['embedding'], true);
        if (!is_array($sourceVec) || empty($sourceVec)) {
            return [];
        }

        // Load all embeddings of the same type for this tenant
        $rows = Database::query(
            "SELECT content_id, embedding FROM content_embeddings
             WHERE tenant_id = ? AND content_type = ? AND content_id != ?",
            [$tenantId, $contentType, $contentId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $similarities = [];
        foreach ($rows as $row) {
            $vec = json_decode($row['embedding'], true);
            if (!is_array($vec) || empty($vec)) {
                continue;
            }
            $sim = self::cosineSimilarity($sourceVec, $vec);
            if ($sim > 0.0) {
                $similarities[(int)$row['content_id']] = $sim;
            }
        }

        arsort($similarities);

        return array_keys(array_slice($similarities, 0, $limit, true));
    }

    // =========================================================================
    // CORE MATH
    // =========================================================================

    /**
     * Cosine similarity between two dense vectors.
     *
     * cos(A, B) = (A · B) / (|A| × |B|)
     * Returns 0.0 if either vector is zero-length.
     *
     * @param float[] $a
     * @param float[] $b
     * @return float Similarity in [-1, 1]
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Convert listing data to a single string for embedding
     */
    private static function listingToText(array $listing): string
    {
        $parts = array_filter([
            $listing['title']       ?? '',
            $listing['description'] ?? '',
            $listing['location']    ?? '',
            $listing['skills']      ?? '',
        ]);

        return implode('. ', $parts);
    }

    /**
     * Convert user data to a single string for embedding
     */
    private static function userToText(array $user): string
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $parts = array_filter([
            $name,
            $user['bio']    ?? '',
            $user['skills'] ?? '',
        ]);

        return implode('. ', $parts);
    }

    /**
     * Generate an embedding via OpenAI and store it in content_embeddings.
     * Silently skips if OpenAI is not configured or the text is empty.
     */
    private static function store(int $tenantId, string $contentType, int $contentId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        try {
            $provider = self::getProvider($tenantId);
            if ($provider === null) {
                return;
            }

            $embedding = $provider->embed($text);

            if (empty($embedding)) {
                return;
            }

            Database::query(
                "INSERT INTO content_embeddings (tenant_id, content_type, content_id, model, embedding)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE model = VALUES(model), embedding = VALUES(embedding), updated_at = NOW()",
                [$tenantId, $contentType, $contentId, self::MODEL, json_encode($embedding)]
            );
        } catch (\Throwable $e) {
            // Non-fatal — embedding generation may fail due to API limits or config
            error_log("EmbeddingService: failed to store {$contentType}#{$contentId}: " . $e->getMessage());
        }
    }

    /**
     * Get a configured OpenAIProvider for the given tenant, or null if not set up.
     */
    private static function getProvider(int $tenantId): ?OpenAIProvider
    {
        try {
            $apiKey = AiSettings::get($tenantId, 'openai_api_key');
            if (empty($apiKey)) {
                return null;
            }

            $orgId = AiSettings::get($tenantId, 'openai_org_id');

            return new OpenAIProvider(array_filter([
                'api_key' => $apiKey,
                'org_id'  => $orgId,
            ]));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
