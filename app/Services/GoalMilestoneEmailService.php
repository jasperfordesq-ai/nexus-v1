<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GoalMilestoneEmailService — Sends congratulatory emails when a user hits
 * 25%, 50%, 75%, or 100% progress on a goal.
 *
 * Uses a permanent cache key per (tenant, user, goal, milestone) to ensure
 * each milestone email fires at most once even if progress is undone and redone.
 */
class GoalMilestoneEmailService
{
    /** Milestone thresholds (percent). */
    private const MILESTONES = [25, 50, 75, 100];

    /**
     * Check whether any milestone was crossed and send a single email for the
     * first one found (lowest milestone that was newly crossed).
     *
     * @param int    $tenantId   Current tenant ID
     * @param int    $userId     Goal owner user ID
     * @param int    $goalId     Goal ID
     * @param string $goalTitle  Human-readable goal title
     * @param float  $oldPercent Progress percentage before this update (0–100)
     * @param float  $newPercent Progress percentage after this update  (0–100)
     */
    public static function checkAndSendMilestone(
        int $tenantId,
        int $userId,
        int $goalId,
        string $goalTitle,
        float $oldPercent,
        float $newPercent
    ): void {
        foreach (self::MILESTONES as $milestone) {
            if ($oldPercent >= $milestone || $newPercent < $milestone) {
                continue;
            }

            // Check permanent dedup cache — never re-send even if progress regresses
            $cacheKey = "goal_milestone:{$tenantId}:{$userId}:{$goalId}:{$milestone}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            // Mark as sent before attempting delivery so a mailer failure does
            // not result in a re-send on the next progress update
            Cache::forever($cacheKey, true);

            try {
                self::sendMilestoneEmail($tenantId, $userId, $goalTitle, $goalId, $milestone);
            } catch (\Throwable $e) {
                Log::warning('[GoalMilestoneEmailService] email failed', [
                    'goal_id'   => $goalId,
                    'user_id'   => $userId,
                    'milestone' => $milestone,
                    'error'     => $e->getMessage(),
                ]);
            }

            // Only fire one email per progress update
            break;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function sendMilestoneEmail(
        int $tenantId,
        int $userId,
        string $goalTitle,
        int $goalId,
        int $milestone
    ): void {
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['email', 'first_name', 'name'])
            ->first();

        if (!$user || empty($user->email)) {
            return;
        }

        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
        $safeTitle = htmlspecialchars($goalTitle, ENT_QUOTES, 'UTF-8');
        $goalUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/goals/' . $goalId;

        $isComplete = ($milestone === 100);

        if ($isComplete) {
            $subject = __('emails_goals.goal_milestone.complete_subject');
            $title   = __('emails_goals.goal_milestone.complete_title');
            $preview = __('emails_goals.goal_milestone.complete_preview', ['goal' => $safeTitle]);
            $body    = __('emails_goals.goal_milestone.complete_body', ['goal' => $safeTitle, 'name' => $firstName]);
            $cta     = __('emails_goals.goal_milestone.complete_cta');
            $theme   = 'success';
        } else {
            $subject = __('emails_goals.goal_milestone.progress_subject', ['percent' => $milestone]);
            $title   = __('emails_goals.goal_milestone.progress_title', ['percent' => $milestone]);
            $preview = __('emails_goals.goal_milestone.progress_preview', ['goal' => $safeTitle]);
            $body    = __('emails_goals.goal_milestone.progress_body', ['percent' => $milestone, 'goal' => $safeTitle]);
            $cta     = __('emails_goals.goal_milestone.progress_cta');
            $theme   = 'brand';
        }

        $html = EmailTemplateBuilder::make()
            ->theme($theme)
            ->title($title)
            ->previewText($preview)
            ->greeting($firstName)
            ->paragraph($body)
            ->button($cta, $goalUrl)
            ->render();

        if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
            Log::warning('[GoalMilestoneEmailService] mailer send failed', [
                'user_id'   => $userId,
                'goal_id'   => $goalId,
                'milestone' => $milestone,
            ]);
        }
    }
}
