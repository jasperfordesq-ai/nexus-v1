<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobSpamDetectionService — Automated spam and fraud detection for job postings.
 *
 * Analyzes job data against multiple heuristics and returns a spam score
 * (0-100) with flags indicating which checks triggered. Integrates with
 * the moderation workflow — high scores auto-flag or block postings.
 */
class JobSpamDetectionService
{
    /**
     * Known suspicious/spam domains that commonly appear in fraudulent job posts.
     */
    private const SUSPICIOUS_DOMAINS = [
        'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly',
        'is.gd', 'buff.ly', 'rebrand.ly', 'shorturl.at',
        'clickbait.com', 'freemoney.com', 'earnfast.com',
        'workfromhome-scam.com', 'getrichquick.com',
    ];

    /**
     * Maximum number of jobs a user can post per hour before triggering rate limit.
     */
    private const MAX_JOBS_PER_HOUR = 5;

    /**
     * Minimum account age in hours before posting is considered non-suspicious.
     */
    private const MIN_ACCOUNT_AGE_HOURS = 24;

    /**
     * Analyze a job posting for spam/fraud indicators.
     *
     * @param array  $jobData  The job data being submitted
     * @param int    $userId   The user posting the job
     * @param int    $tenantId The tenant context
     * @return array{score: int, flags: string[], action: string}
     */
    public static function analyzeJob(array $jobData, int $userId, int $tenantId): array
    {
        $flags = [];
        $score = 0;

        // Check 1: Duplicate content
        $duplicateScore = self::checkDuplicateContent($jobData, $userId, $tenantId);
        if ($duplicateScore > 0) {
            $flags[] = 'duplicate_content';
            $score += $duplicateScore;
        }

        // Check 2: Suspicious URLs
        $urlScore = self::checkSuspiciousUrls($jobData);
        if ($urlScore > 0) {
            $flags[] = 'suspicious_links';
            $score += $urlScore;
        }

        // Check 3: Rate limiting — too many posts in short time
        $rateScore = self::checkPostingRate($userId, $tenantId);
        if ($rateScore > 0) {
            $flags[] = 'excessive_posting_rate';
            $score += $rateScore;
        }

        // Check 4: Suspicious text patterns
        $patternScore = self::checkSuspiciousPatterns($jobData);
        if ($patternScore > 0) {
            $flags[] = 'suspicious_patterns';
            $score += $patternScore;
        }

        // Check 5: New account check
        $accountScore = self::checkNewAccount($userId);
        if ($accountScore > 0) {
            $flags[] = 'new_account';
            $score += $accountScore;
        }

        // Cap score at 100
        $score = min(100, $score);

        // Determine action
        $action = 'allow';
        if ($score > 90) {
            $action = 'block';
        } elseif ($score > 70) {
            $action = 'flag';
        }

        return [
            'score' => $score,
            'flags' => $flags,
            'action' => $action,
        ];
    }

    /**
     * Check for duplicate content — same or very similar description posted multiple times.
     */
    private static function checkDuplicateContent(array $jobData, int $userId, int $tenantId): int
    {
        $description = trim($jobData['description'] ?? '');
        $title = trim($jobData['title'] ?? '');

        if (empty($description) && empty($title)) {
            return 0;
        }

        // Check for exact title+description match by the same user in the last 30 days
        $query = JobVacancy::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30));

        if (!empty($title)) {
            $exactTitleMatch = (clone $query)->where('title', $title)->exists();
            if ($exactTitleMatch) {
                return 30;
            }
        }

        if (!empty($description)) {
            // Check for identical description
            $exactDescMatch = (clone $query)->where('description', $description)->exists();
            if ($exactDescMatch) {
                return 35;
            }

            // Check for very similar descriptions (using first 200 chars)
            $prefix = mb_substr($description, 0, 200);
            $similarCount = (clone $query)
                ->where('description', 'LIKE', $prefix . '%')
                ->count();
            if ($similarCount > 0) {
                return 20;
            }
        }

        return 0;
    }

    /**
     * Check for suspicious URLs in the job description.
     */
    private static function checkSuspiciousUrls(array $jobData): int
    {
        $description = $jobData['description'] ?? '';
        $title = $jobData['title'] ?? '';
        $text = $title . ' ' . $description;

        if (empty(trim($text))) {
            return 0;
        }

        $score = 0;

        // Extract URLs from text
        preg_match_all(
            '#https?://([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,})(?:/[^\s]*)?#i',
            $text,
            $matches
        );

        $domains = $matches[1] ?? [];

        // Count total external links — excessive links are suspicious
        $linkCount = count($domains);
        if ($linkCount > 5) {
            $score += 20;
        } elseif ($linkCount > 3) {
            $score += 10;
        }

        // Check against known suspicious domains
        foreach ($domains as $domain) {
            $domain = strtolower($domain);
            foreach (self::SUSPICIOUS_DOMAINS as $spamDomain) {
                if ($domain === $spamDomain || str_ends_with($domain, '.' . $spamDomain)) {
                    $score += 25;
                    break;
                }
            }
        }

        return min(50, $score);
    }

    /**
     * Check if user is posting too many jobs in a short time.
     */
    private static function checkPostingRate(int $userId, int $tenantId): int
    {
        $recentCount = JobVacancy::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= self::MAX_JOBS_PER_HOUR) {
            return 30;
        }

        if ($recentCount >= 3) {
            return 15;
        }

        return 0;
    }

    /**
     * Check for suspicious text patterns in job data.
     *
     * Flags: ALL CAPS titles, excessive special characters, phone numbers in title.
     */
    private static function checkSuspiciousPatterns(array $jobData): int
    {
        $title = trim($jobData['title'] ?? '');
        $description = trim($jobData['description'] ?? '');
        $score = 0;

        // ALL CAPS title (at least 5 letters, 80%+ uppercase)
        if (mb_strlen($title) >= 5) {
            $letters = preg_replace('/[^a-zA-Z]/', '', $title);
            if (strlen($letters) > 0) {
                $upperRatio = strlen(preg_replace('/[^A-Z]/', '', $letters)) / strlen($letters);
                if ($upperRatio > 0.8) {
                    $score += 15;
                }
            }
        }

        // Excessive special characters in title
        if (mb_strlen($title) > 0) {
            $specialChars = preg_replace('/[a-zA-Z0-9\s]/', '', $title);
            $specialRatio = mb_strlen($specialChars) / mb_strlen($title);
            if ($specialRatio > 0.3) {
                $score += 10;
            }
        }

        // Phone numbers in title (suspicious — should be in contact fields)
        if (preg_match('/(\+?\d[\d\s\-\.]{7,}\d)/', $title)) {
            $score += 15;
        }

        // Excessive exclamation marks or dollar signs
        $exclamationCount = substr_count($title . ' ' . $description, '!');
        $dollarCount = substr_count($title . ' ' . $description, '$');
        if ($exclamationCount > 5 || $dollarCount > 3) {
            $score += 10;
        }

        // Common spam phrases
        $spamPhrases = [
            'earn money fast', 'work from home guaranteed', 'no experience needed earn',
            'make money online', 'get rich quick', 'financial freedom guaranteed',
            'unlimited income', 'pyramid', 'mlm opportunity',
        ];
        $lowerText = strtolower($title . ' ' . $description);
        foreach ($spamPhrases as $phrase) {
            if (str_contains($lowerText, $phrase)) {
                $score += 20;
                break; // Only count once
            }
        }

        return min(40, $score);
    }

    /**
     * Check if the posting user's account was created very recently.
     */
    private static function checkNewAccount(int $userId): int
    {
        $user = User::where('id', $userId)->first(['id', 'created_at']);

        if (!$user || !$user->created_at) {
            return 0;
        }

        $hoursSinceCreation = $user->created_at->diffInHours(now());

        if ($hoursSinceCreation < self::MIN_ACCOUNT_AGE_HOURS) {
            return 15;
        }

        return 0;
    }

    /**
     * Get aggregate spam detection statistics for a tenant.
     *
     * @return array{total_analyzed: int, blocked: int, flagged: int, avg_score: float, top_flags: array}
     */
    public static function getSpamStats(int $tenantId): array
    {
        $totalAnalyzed = JobVacancy::where('tenant_id', $tenantId)
            ->whereNotNull('spam_score')
            ->count();

        $blocked = JobVacancy::where('tenant_id', $tenantId)
            ->where('spam_score', '>', 90)
            ->count();

        $flagged = JobVacancy::where('tenant_id', $tenantId)
            ->where('spam_score', '>', 70)
            ->where('spam_score', '<=', 90)
            ->count();

        $avgScore = JobVacancy::where('tenant_id', $tenantId)
            ->whereNotNull('spam_score')
            ->avg('spam_score') ?? 0;

        // Aggregate flag counts from JSON column
        $flagCounts = [];
        $jobsWithFlags = JobVacancy::where('tenant_id', $tenantId)
            ->whereNotNull('spam_flags')
            ->pluck('spam_flags');

        foreach ($jobsWithFlags as $flagsJson) {
            $flags = is_string($flagsJson) ? json_decode($flagsJson, true) : $flagsJson;
            if (is_array($flags)) {
                foreach ($flags as $flag) {
                    $flagCounts[$flag] = ($flagCounts[$flag] ?? 0) + 1;
                }
            }
        }

        arsort($flagCounts);

        return [
            'total_analyzed' => $totalAnalyzed,
            'blocked' => $blocked,
            'flagged' => $flagged,
            'avg_score' => round((float) $avgScore, 1),
            'top_flags' => $flagCounts,
        ];
    }
}
