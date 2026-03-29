<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserStreak;
use App\Models\UserXpLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GamificationEmailService — Sends weekly progress digests and achievement notifications.
 *
 * Handles weekly digest emails for users with gamification activity
 * and milestone notification emails for achievements.
 */
class GamificationEmailService
{
    public function __construct()
    {
    }

    /**
     * Send weekly progress digests to users who actually have activity.
     *
     * Iterates all active tenants, finds users with XP activity in the past 7 days,
     * generates a digest for each, and sends it via EmailService.
     *
     * @return array{sent: int, skipped: int, errors: int}
     */
    public function sendWeeklyDigests(): array
    {
        $sent = 0;
        $skipped = 0;
        $errors = 0;

        /** @var EmailService $emailService */
        $emailService = app(EmailService::class);

        try {
            $tenants = DB::table('tenants')
                ->where('is_active', 1)
                ->select(['id', 'slug', 'name'])
                ->get();
        } catch (\Throwable $e) {
            Log::error('GamificationEmailService: Failed to query tenants', ['error' => $e->getMessage()]);
            return ['sent' => 0, 'skipped' => 0, 'errors' => 1];
        }

        foreach ($tenants as $tenant) {
            try {
                TenantContext::setById($tenant->id);
            } catch (\Throwable $e) {
                Log::warning('GamificationEmailService: Failed to set tenant context', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
                continue;
            }

            try {
                // Find users with XP activity in the past 7 days
                $activeUserIds = DB::table('user_xp_log')
                    ->where('tenant_id', $tenant->id)
                    ->where('created_at', '>=', now()->subWeek())
                    ->distinct()
                    ->pluck('user_id')
                    ->all();

                if (empty($activeUserIds)) {
                    continue;
                }

                // Get active users with email addresses
                $users = User::whereIn('id', $activeUserIds)
                    ->where('status', 'active')
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get(['id', 'email', 'first_name', 'last_name']);

                foreach ($users as $user) {
                    try {
                        // Check email_gamification_digest preference — default to sending (opt-out model)
                        try {
                            $prefs = User::getNotificationPreferences($user->id);
                            if (!((bool) ($prefs['email_gamification_digest'] ?? true))) {
                                $skipped++;
                                continue;
                            }
                        } catch (\Throwable $prefError) {
                            Log::debug('GamificationEmailService: could not read email_gamification_digest pref', [
                                'user_id' => $user->id,
                                'error' => $prefError->getMessage(),
                            ]);
                            // Default to sending on error
                        }

                        $digest = $this->generateUserDigest($user->id);

                        if (empty($digest) || ($digest['xp_earned'] ?? 0) === 0) {
                            $skipped++;
                            continue;
                        }

                        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                        $subject = 'Your Weekly Progress Digest';
                        $body = $this->buildDigestEmailBody($name, $digest, $tenant->name ?? 'your community');

                        $success = $emailService->send($user->email, $subject, $body);

                        if ($success) {
                            $sent++;
                        } else {
                            $errors++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('GamificationEmailService: Failed to send digest', [
                            'user_id' => $user->id,
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);
                        $errors++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('GamificationEmailService: Error processing tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Generate a user's weekly digest data.
     *
     * @return array{user_id: int, xp_earned: int, badges_earned: array, streak: int, rank: int|null, level: int}
     */
    public function generateUserDigest(int $userId): array
    {
        $weekAgo = now()->subWeek();

        try {
            // XP earned this week
            $xpEarned = (int) UserXpLog::where('user_id', $userId)
                ->where('created_at', '>=', $weekAgo)
                ->sum('xp_amount');
        } catch (\Throwable $e) {
            $xpEarned = 0;
        }

        try {
            // Badges earned this week
            $badgesEarned = UserBadge::where('user_id', $userId)
                ->where('awarded_at', '>=', $weekAgo)
                ->get(['badge_key', 'name', 'icon'])
                ->toArray();
        } catch (\Throwable $e) {
            $badgesEarned = [];
        }

        try {
            // Current streak
            $streak = (int) (UserStreak::where('user_id', $userId)
                ->where('streak_type', 'login')
                ->value('current_streak') ?? 0);
        } catch (\Throwable $e) {
            $streak = 0;
        }

        try {
            // User level and XP
            $user = User::find($userId, ['id', 'xp', 'level', 'points']);
            $level = (int) ($user->level ?? 1);
            $totalXp = (int) ($user->xp ?? $user->points ?? 0);
        } catch (\Throwable $e) {
            $level = 1;
            $totalXp = 0;
        }

        // Leaderboard rank: count users with more XP in the same tenant
        $rank = null;
        try {
            $tenantId = TenantContext::getId();
            $rank = (int) DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('is_approved', true)
                ->whereRaw('COALESCE(xp, points, 0) > ?', [$totalXp])
                ->count() + 1;
        } catch (\Throwable $e) {
            // Rank unavailable
        }

        return [
            'user_id'       => $userId,
            'xp_earned'     => $xpEarned,
            'badges_earned' => $badgesEarned,
            'streak'        => $streak,
            'rank'          => $rank,
            'level'         => $level,
        ];
    }

    /**
     * Send a milestone achievement email.
     *
     * @param int    $userId User to notify
     * @param string $type   Milestone type: 'level_up', 'badge_earned', 'streak_milestone', 'leaderboard_top'
     * @param array  $data   Context data for the milestone (varies by type)
     */
    public function sendMilestoneEmail(int $userId, string $type, array $data): bool
    {
        try {
            // Check email_gamification_milestones preference — default to sending (opt-out model)
            try {
                $prefs = User::getNotificationPreferences($userId);
                if (!((bool) ($prefs['email_gamification_milestones'] ?? true))) {
                    return false;
                }
            } catch (\Throwable $prefError) {
                Log::debug('GamificationEmailService: could not read email_gamification_milestones pref', [
                    'user_id' => $userId,
                    'error' => $prefError->getMessage(),
                ]);
                // Default to sending on error
            }

            $user = User::find($userId, ['id', 'email', 'first_name', 'last_name']);

            if (!$user || empty($user->email)) {
                return false;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            [$subject, $body] = $this->buildMilestoneEmail($name, $type, $data);

            /** @var EmailService $emailService */
            $emailService = app(EmailService::class);

            return $emailService->send($user->email, $subject, $body);
        } catch (\Throwable $e) {
            Log::error('GamificationEmailService: Failed to send milestone email', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build the plain-text body for a weekly digest email.
     */
    private function buildDigestEmailBody(string $name, array $digest, string $communityName): string
    {
        $greeting = $name ? "Hi {$name}," : 'Hi,';
        $lines = [
            $greeting,
            '',
            "Here's your weekly progress update from {$communityName}:",
            '',
            "XP Earned This Week: {$digest['xp_earned']}",
            "Current Level: {$digest['level']}",
        ];

        if ($digest['rank']) {
            $lines[] = "Leaderboard Rank: #{$digest['rank']}";
        }

        if ($digest['streak'] > 0) {
            $lines[] = "Current Login Streak: {$digest['streak']} days";
        }

        if (!empty($digest['badges_earned'])) {
            $lines[] = '';
            $lines[] = 'Badges Earned This Week:';
            foreach ($digest['badges_earned'] as $badge) {
                $icon = $badge['icon'] ?? '';
                $badgeName = $badge['name'] ?? $badge['badge_key'] ?? 'Badge';
                $lines[] = "  {$icon} {$badgeName}";
            }
        }

        $lines[] = '';
        $lines[] = 'Keep up the great work!';

        return implode("\n", $lines);
    }

    /**
     * Build subject and body for a milestone email.
     *
     * @return array{0: string, 1: string} [subject, body]
     */
    private function buildMilestoneEmail(string $name, string $type, array $data): array
    {
        $greeting = $name ? "Hi {$name}," : 'Hi,';

        switch ($type) {
            case 'level_up':
                $level = $data['level'] ?? '?';
                $subject = "Congratulations! You reached Level {$level}";
                $body = implode("\n", [
                    $greeting,
                    '',
                    "Congratulations! You've just reached Level {$level}!",
                    '',
                    'Keep contributing to your community to unlock even more achievements.',
                ]);
                break;

            case 'badge_earned':
                $badgeName = $data['badge_name'] ?? $data['name'] ?? 'a new badge';
                $badgeIcon = $data['icon'] ?? '';
                $subject = "You earned a new badge: {$badgeName}";
                $body = implode("\n", [
                    $greeting,
                    '',
                    "You've just earned the {$badgeIcon} {$badgeName} badge!",
                    '',
                    !empty($data['description']) ? $data['description'] : 'Great work contributing to your community!',
                ]);
                break;

            case 'streak_milestone':
                $days = $data['days'] ?? $data['streak'] ?? '?';
                $subject = "Amazing! {$days}-day login streak";
                $body = implode("\n", [
                    $greeting,
                    '',
                    "Incredible dedication! You've maintained a {$days}-day login streak.",
                    '',
                    'Your consistency is inspiring to the whole community.',
                ]);
                break;

            case 'leaderboard_top':
                $position = $data['position'] ?? $data['rank'] ?? '?';
                $subject = "You're #{$position} on the leaderboard!";
                $body = implode("\n", [
                    $greeting,
                    '',
                    "You've climbed to position #{$position} on the community leaderboard!",
                    '',
                    'Keep it up to maintain your spot at the top.',
                ]);
                break;

            default:
                $subject = 'Achievement Unlocked!';
                $body = implode("\n", [
                    $greeting,
                    '',
                    'Congratulations on your latest achievement!',
                    '',
                    !empty($data['message']) ? $data['message'] : 'Keep contributing to your community.',
                ]);
                break;
        }

        return [$subject, $body];
    }
}
