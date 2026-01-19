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
