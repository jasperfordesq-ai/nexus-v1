<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingService — Semantic Vector Embeddings
 *
 * Uses OpenAI text-embedding-3-small to generate vector representations of
 * listings and user profiles, enabling semantic similarity search beyond
 * simple keyword matching.
 *
 * Embeddings are persisted in the content_embeddings table and retrieved via
 * cosine similarity.
 *
 * Algorithm references:
 *   - OpenAI embeddings: https://platform.openai.com/docs/guides/embeddings
 *   - Cosine similarity: https://en.wikipedia.org/wiki/Cosine_similarity
 */
class EmbeddingService
{
    private const MODEL = 'text-embedding-3-small';

    // =========================================================================
    // GENERATE & STORE
    // =========================================================================

    /**
     * Generate and persist an embedding for a listing.
     *
     * @param array $listing Listing row (must have id, title, description, tenant_id)
     */
    public function generateForListing(array $listing): void
    {
        $text = $this->listingToText($listing);
        $this->store((int) $listing['tenant_id'], 'listing', (int) $listing['id'], $text);
    }

    /**
     * Generate and persist an embedding for a user profile.
     *
     * @param array $user User row (must have id, bio, skills, tenant_id)
     */
    public function generateForUser(array $user): void
    {
        $text = $this->userToText($user);
        $this->store((int) $user['tenant_id'], 'user', (int) $user['id'], $text);
    }

    // =========================================================================
    // FIND SIMILAR
    // =========================================================================

    /**
     * Find content IDs most semantically similar to the given item.
     *
     * @param int    $contentId   ID of the source item
     * @param string $contentType 'listing' | 'user' | 'event' | 'group'
     * @param int    $tenantId    Tenant scope
     * @param int    $limit       Max results
     * @return int[]              Similar content IDs (most similar first)
     */
    public function findSimilar(int $contentId, string $contentType, int $tenantId, int $limit = 5): array
    {
        try {
            $sourceRow = DB::table('content_embeddings')
                ->where('tenant_id', $tenantId)
                ->where('content_type', $contentType)
                ->where('content_id', $contentId)
                ->first();

            if (!$sourceRow || empty($sourceRow->embedding)) {
                return [];
            }

            $sourceVec = json_decode($sourceRow->embedding, true);
            if (!is_array($sourceVec) || empty($sourceVec)) {
                return [];
            }

            $rows = DB::table('content_embeddings')
                ->where('tenant_id', $tenantId)
                ->where('content_type', $contentType)
                ->where('content_id', '!=', $contentId)
                ->get();

            $similarities = [];
            foreach ($rows as $row) {
                $vec = json_decode($row->embedding, true);
                if (!is_array($vec) || empty($vec)) {
                    continue;
                }
                $sim = $this->cosineSimilarity($sourceVec, $vec);
                if ($sim > 0.0) {
                    $similarities[(int) $row->content_id] = $sim;
                }
            }

            arsort($similarities);

            return array_keys(array_slice($similarities, 0, $limit, true));
        } catch (\Exception $e) {
            Log::error('EmbeddingService::findSimilar error: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // CORE MATH
    // =========================================================================

    /**
     * Cosine similarity between two dense vectors.
     *
     * cos(A, B) = (A . B) / (|A| x |B|)
     *
     * @param float[] $a
     * @param float[] $b
     * @return float Similarity in [-1, 1]
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            Log::debug('EmbeddingService::cosineSimilarity dimension mismatch: ' . count($a) . ' vs ' . count($b));
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        if ($denom <= 0.0) {
            return 0.0;
        }
        $result = $dot / $denom;
        return (is_nan($result) || is_infinite($result)) ? 0.0 : (float) max(-1.0, min(1.0, $result));
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    private function listingToText(array $listing): string
    {
        $parts = array_filter([
            $listing['title'] ?? '',
            $listing['description'] ?? '',
            $listing['location'] ?? '',
            $listing['skills'] ?? '',
        ]);

        return implode('. ', $parts);
    }

    private function userToText(array $user): string
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $parts = array_filter([$name, $user['bio'] ?? '', $user['skills'] ?? '']);

        return implode('. ', $parts);
    }

    /**
     * Generate an embedding via OpenAI and store it in content_embeddings.
     */
    private function store(int $tenantId, string $contentType, int $contentId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        try {
            $apiKey = DB::table('ai_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'openai_api_key')
                ->value('setting_value');

            if (empty($apiKey)) {
                return;
            }

            // Call OpenAI embeddings API directly
            $response = $this->callOpenAiEmbedding($apiKey, $text);
            if (empty($response)) {
                return;
            }

            DB::statement(
                "INSERT INTO content_embeddings (tenant_id, content_type, content_id, model, embedding)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE model = VALUES(model), embedding = VALUES(embedding), updated_at = NOW()",
                [$tenantId, $contentType, $contentId, self::MODEL, json_encode($response)]
            );
        } catch (\Throwable $e) {
            Log::info("EmbeddingService: failed to store {$contentType}#{$contentId}: " . $e->getMessage());
        }
    }

    /**
     * Call OpenAI embedding API.
     *
     * @return float[]|null
     */
    private function callOpenAiEmbedding(string $apiKey, string $text): ?array
    {
        try {
            $ch = curl_init('https://api.openai.com/v1/embeddings');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'input' => $text,
                    'model' => self::MODEL,
                ]),
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);
            return $data['data'][0]['embedding'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
