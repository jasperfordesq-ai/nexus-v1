<?php
/**
 * Nexus Score Widget Component
 * Compact widget to display user's Nexus Score on profile pages
 *
 * Usage: Include this on profile pages to show quick score overview
 *
 * @var int $userId - User ID to display score for
 * @var int $tenantId - Tenant ID
 * @var bool $isOwner - Whether viewing own profile (optional, default false)
 */

// Get required data from parent scope or parameters
$userId = $userId ?? ($_SESSION['user_id'] ?? 0);
$tenantId = $tenantId ?? ($_SESSION['current_tenant_id'] ?? 1);
$isOwner = $isOwner ?? false;

// Initialize services
require_once __DIR__ . '/../../../bootstrap.php';

use Nexus\Services\NexusScoreService;
use Nexus\Services\NexusScoreCacheService;

try {
    $scoreService = new NexusScoreService();
    $cacheService = new NexusScoreCacheService($scoreService);

    // Try to get cached score first, fall back to live calculation
    $scoreData = $cacheService->getCachedScore($userId, $tenantId);
    if (!$scoreData) {
        $scoreData = $scoreService->calculateNexusScore($userId, $tenantId);
    }

    $totalScore = $scoreData['total_score'] ?? 0;
    $tier = $scoreData['tier'] ?? ['name' => 'Novice', 'icon' => '🌱'];
    $percentile = $scoreData['percentile'] ?? 50;

} catch (Exception $e) {
    // Fallback data if calculation fails
    $totalScore = 0;
    $tier = ['name' => 'Novice', 'icon' => '🌱'];
    $percentile = 0;
}
?>

<style>
    /* Nexus Score Widget Styles */
    .nexus-score-widget {
        background: linear-gradient(135deg,
            rgba(30, 41, 59, 0.95) 0%,
            rgba(15, 23, 42, 0.9) 100%);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 1.75rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow:
            0 8px 24px rgba(0, 0, 0, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
    }

    .nexus-score-widget::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
        opacity: 0.8;
    }

    .nexus-score-widget:hover {
        transform: translateY(-2px);
        box-shadow:
            0 12px 32px rgba(0, 0, 0, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.15);
    }

    .nexus-widget-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
    }

    .nexus-widget-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.125rem;
        font-weight: 700;
        color: white;
    }

    .nexus-widget-icon {
        font-size: 1.5rem;
        filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.6));
    }

    .nexus-widget-score-display {
        text-align: center;
        margin: 1.5rem 0;
    }

    .nexus-widget-score-value {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
        margin-bottom: 0.5rem;
        animation: scoreGlow 3s ease-in-out infinite;
    }

    @keyframes scoreGlow {
        0%, 100% { filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.4)); }
        50% { filter: drop-shadow(0 0 16px rgba(139, 92, 246, 0.6)); }
    }

    .nexus-widget-score-label {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.6);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .nexus-widget-tier {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg,
            rgba(99, 102, 241, 0.2),
            rgba(139, 92, 246, 0.2));
        border-radius: 12px;
        margin-bottom: 1rem;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .nexus-widget-tier-icon {
        font-size: 1.5rem;
    }

    .nexus-widget-tier-name {
        font-size: 1rem;
        font-weight: 600;
        color: white;
    }

    .nexus-widget-rank {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem;
        background: rgba(16, 185, 129, 0.1);
        border-radius: 10px;
        border: 1px solid rgba(16, 185, 129, 0.2);
        margin-bottom: 1.25rem;
    }

    .nexus-widget-rank-text {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .nexus-widget-rank-value {
        font-weight: 700;
        color: #10b981;
    }

    .nexus-widget-cta {
        display: block;
        width: 100%;
        padding: 0.875rem;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 0.95rem;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow:
            0 4px 16px rgba(99, 102, 241, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.3);
        position: relative;
        overflow: hidden;
    }

    .nexus-widget-cta::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .nexus-widget-cta:hover::before {
        width: 300px;
        height: 300px;
    }

    .nexus-widget-cta:hover {
        transform: translateY(-2px);
        box-shadow:
            0 8px 24px rgba(99, 102, 241, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.4);
    }

    .nexus-widget-cta:active {
        transform: translateY(0);
    }
</style>

<div class="nexus-score-widget">
    <div class="nexus-widget-header">
        <div class="nexus-widget-title">
            <span class="nexus-widget-icon">🏆</span>
            <span><?php echo $isOwner ? 'My' : ''; ?> Nexus Score</span>
        </div>
    </div>

    <div class="nexus-widget-score-display">
        <div class="nexus-widget-score-value"><?php echo number_format($totalScore); ?></div>
        <div class="nexus-widget-score-label">Points</div>
    </div>

    <div class="nexus-widget-tier">
        <span class="nexus-widget-tier-icon"><?php echo htmlspecialchars($tier['icon']); ?></span>
        <span class="nexus-widget-tier-name"><?php echo htmlspecialchars($tier['name']); ?></span>
    </div>

    <div class="nexus-widget-rank">
        <span class="nexus-widget-rank-text">Community Rank:</span>
        <span class="nexus-widget-rank-value">Top <?php echo $percentile; ?>%</span>
    </div>

    <a href="/nexus-score" class="nexus-widget-cta">
        <?php echo $isOwner ? 'View Full Dashboard →' : 'View Leaderboard →'; ?>
    </a>
</div>
