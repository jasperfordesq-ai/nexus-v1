<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AI Usage Model
 *
 * Tracks detailed AI usage for billing and analytics.
 */
class AiUsage
{
    /**
     * Log a usage record
     */
    public static function log(int $userId, string $provider, string $feature, array $data = []): int
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            INSERT INTO ai_usage
            (tenant_id, user_id, provider, feature, tokens_input, tokens_output, cost_usd, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $tenantId,
            $userId,
            $provider,
            $feature,
            $data['tokens_input'] ?? 0,
            $data['tokens_output'] ?? 0,
            $data['cost_usd'] ?? 0,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get usage for a user
     */
    public static function getByUserId(int $userId, int $limit = 100): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;

        $stmt = $db->prepare("
            SELECT * FROM ai_usage
            WHERE tenant_id = ? AND user_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");

        $stmt->execute([$tenantId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get usage stats for a time period
     */
    public static function getStats(string $period = 'month'): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $dateCondition = match ($period) {
            'day' => "created_at >= CURDATE()",
            'week' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => "1=1",
        };

        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(tokens_input) as total_tokens_input,
                SUM(tokens_output) as total_tokens_output,
                SUM(tokens_input + tokens_output) as total_tokens,
                SUM(cost_usd) as total_cost,
                AVG(tokens_input + tokens_output) as avg_tokens_per_request
            FROM ai_usage
            WHERE tenant_id = ? AND $dateCondition
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get usage by provider
     */
    public static function getByProvider(string $period = 'month'): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $dateCondition = match ($period) {
            'day' => "created_at >= CURDATE()",
            'week' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            default => "1=1",
        };

        $stmt = $db->prepare("
            SELECT
                provider,
                COUNT(*) as requests,
                SUM(tokens_input) as tokens_input,
                SUM(tokens_output) as tokens_output,
                SUM(cost_usd) as cost
            FROM ai_usage
            WHERE tenant_id = ? AND $dateCondition
            GROUP BY provider
            ORDER BY requests DESC
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get usage by feature
     */
    public static function getByFeature(string $period = 'month'): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $dateCondition = match ($period) {
            'day' => "created_at >= CURDATE()",
            'week' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            default => "1=1",
        };

        $stmt = $db->prepare("
            SELECT
                feature,
                COUNT(*) as requests,
                SUM(tokens_input + tokens_output) as total_tokens,
                SUM(cost_usd) as cost
            FROM ai_usage
            WHERE tenant_id = ? AND $dateCondition
            GROUP BY feature
            ORDER BY requests DESC
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get daily usage trend
     */
    public static function getDailyTrend(int $days = 30): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as requests,
                SUM(tokens_input + tokens_output) as tokens,
                SUM(cost_usd) as cost
            FROM ai_usage
            WHERE tenant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");

        $stmt->execute([$tenantId, $days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get cost for current month
     */
    public static function getCurrentMonthCost(): float
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(cost_usd), 0) as cost
            FROM ai_usage
            WHERE tenant_id = ?
            AND YEAR(created_at) = YEAR(CURDATE())
            AND MONTH(created_at) = MONTH(CURDATE())
        ");

        $stmt->execute([$tenantId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Calculate cost based on provider and tokens
     */
    public static function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        // Cost per 1000 tokens (approximate)
        $costs = [
            'openai' => [
                'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
                'gpt-4' => ['input' => 0.03, 'output' => 0.06],
                'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
                'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
                'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            ],
            'anthropic' => [
                'claude-sonnet-4-20250514' => ['input' => 0.003, 'output' => 0.015],
                'claude-3-5-sonnet-20241022' => ['input' => 0.003, 'output' => 0.015],
                'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075],
                'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125],
            ],
            'gemini' => [
                // Gemini free tier - no cost
                'default' => ['input' => 0, 'output' => 0],
            ],
            'ollama' => [
                // Self-hosted - no API cost
                'default' => ['input' => 0, 'output' => 0],
            ],
        ];

        $providerCosts = $costs[$provider] ?? [];
        $modelCosts = $providerCosts[$model] ?? $providerCosts['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($inputTokens / 1000) * $modelCosts['input'];
        $outputCost = ($outputTokens / 1000) * $modelCosts['output'];

        return round($inputCost + $outputCost, 6);
    }
}
