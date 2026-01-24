<?php

/**
 * Component: Leaderboard
 *
 * Ranked list of users/items.
 *
 * @param array $users Array of user data: ['id', 'name', 'avatar', 'value', 'subtitle']
 * @param string $metric Metric label (e.g., 'XP', 'Hours', 'Points')
 * @param int $highlightUserId ID of user to highlight (e.g., current user)
 * @param string $class Additional CSS classes
 * @param bool $showRankBadge Show medal icons for top 3 (default: true)
 * @param int $limit Max items to show (default: 10)
 * @param string $emptyMessage Message when no data
 * @param string $baseUrl Base URL for user profile links
 */

$users = $users ?? [];
$metric = $metric ?? 'Points';
$highlightUserId = $highlightUserId ?? null;
$class = $class ?? '';
$showRankBadge = $showRankBadge ?? true;
$limit = $limit ?? 10;
$emptyMessage = $emptyMessage ?? 'No rankings yet';
$baseUrl = $baseUrl ?? '';

$displayUsers = array_slice($users, 0, $limit);
$cssClass = trim('component-leaderboard leaderboard-list ' . $class);
?>

<?php if (empty($displayUsers)): ?>
    <div class="glass-empty-state">
        <div class="empty-icon">🏆</div>
        <h3 class="empty-title"><?= e($emptyMessage) ?></h3>
    </div>
<?php else: ?>
    <div class="<?= e($cssClass) ?>">
        <?php foreach ($displayUsers as $index => $user): ?>
            <?php
            $rank = $index + 1;
            $userId = $user['id'] ?? 0;
            $userName = $user['name'] ?? 'Unknown';
            $userAvatar = $user['avatar'] ?? '';
            $userValue = $user['value'] ?? 0;
            $userSubtitle = $user['subtitle'] ?? '';
            $isHighlighted = $highlightUserId && $userId == $highlightUserId;
            $profileUrl = $baseUrl ? $baseUrl . '/members/' . $userId : '#';

            $itemClass = 'component-leaderboard__item' . ($isHighlighted ? ' component-leaderboard__item--highlighted' : '');
            $rankClass = 'component-leaderboard__rank';
            if ($rank === 1) $rankClass .= ' component-leaderboard__rank--gold';
            elseif ($rank === 2) $rankClass .= ' component-leaderboard__rank--silver';
            elseif ($rank === 3) $rankClass .= ' component-leaderboard__rank--bronze';
            ?>
            <div class="<?= e($itemClass) ?>">
                <!-- Rank -->
                <div class="<?= e($rankClass) ?>">
                    <?php if ($showRankBadge && $rank <= 3): ?>
                        <i class="fa-solid fa-medal"></i>
                    <?php else: ?>
                        <span class="component-leaderboard__rank-number"><?= $rank ?></span>
                    <?php endif; ?>
                </div>

                <!-- Avatar -->
                <a href="<?= e($profileUrl) ?>" class="component-leaderboard__avatar">
                    <?= webp_avatar($userAvatar, $userName, 40) ?>
                </a>

                <!-- User Info -->
                <div class="component-leaderboard__user">
                    <a href="<?= e($profileUrl) ?>" class="component-leaderboard__name">
                        <?= e($userName) ?>
                        <?php if ($isHighlighted): ?>
                            <span class="component-leaderboard__you">(You)</span>
                        <?php endif; ?>
                    </a>
                    <?php if ($userSubtitle): ?>
                        <div class="component-leaderboard__subtitle">
                            <?= e($userSubtitle) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Value -->
                <div class="component-leaderboard__value">
                    <span class="component-leaderboard__score"><?= number_format($userValue) ?></span>
                    <span class="component-leaderboard__metric"><?= e($metric) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
