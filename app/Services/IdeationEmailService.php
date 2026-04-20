<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IdeationEmailService — Transactional email notifications for the Ideation module.
 *
 * Sends email confirmations and alerts for idea lifecycle events:
 * submission confirmation, votes, comments, status changes, and winner announcements.
 *
 * All strings are sourced from the `emails_ideation` translation namespace.
 * Email is sent via `Mailer::forCurrentTenant()` so tenant branding is applied.
 */
class IdeationEmailService
{
    public function __construct()
    {
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Confirm to the idea author that their idea was submitted.
     *
     * @param int $ideaId    The challenge_ideas row id
     * @param int $authorId  The user who submitted the idea
     */
    public function notifyIdeaSubmitted(int $ideaId, int $authorId): void
    {
        try {
            $tenantId = TenantContext::getId();

            $idea = DB::table('challenge_ideas')
                ->where('id', $ideaId)
                ->first();

            if (! $idea) {
                return;
            }

            $challenge = DB::table('ideation_challenges')
                ->where('id', $idea->challenge_id)
                ->where('tenant_id', $tenantId)
                ->first();

            $author = DB::selectOne(
                'SELECT id, email, first_name, name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$authorId, $tenantId]
            );

            if (! $author || empty($author->email)) {
                return;
            }

            $firstName    = $author->first_name ?? $author->name ?? __('emails.common.fallback_name');
            $ideaTitle    = $idea->title ?? '';
            $challengeTitle = $challenge->title ?? '';
            $tenantName   = TenantContext::getSetting('site_name', 'Project NEXUS');
            $ideaUrl      = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/ideation/' . ($idea->challenge_id ?? 0)
                . '/ideas/' . $ideaId;

            $html = EmailTemplateBuilder::make()
                ->theme('brand')
                ->title(__('emails_ideation.notifications.submitted_title'))
                ->previewText(__('emails_ideation.notifications.submitted_preview', [
                    'title'     => $ideaTitle,
                    'challenge' => $challengeTitle,
                ]))
                ->greeting($firstName)
                ->paragraph(__('emails_ideation.notifications.submitted_body', [
                    'title'     => htmlspecialchars($ideaTitle, ENT_QUOTES, 'UTF-8'),
                    'challenge' => htmlspecialchars($challengeTitle, ENT_QUOTES, 'UTF-8'),
                ]))
                ->button(__('emails_ideation.notifications.submitted_cta'), $ideaUrl)
                ->render();

            Mailer::forCurrentTenant()->send(
                $author->email,
                __('emails_ideation.notifications.submitted_subject', ['community' => $tenantName]),
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('[IdeationEmailService] notifyIdeaSubmitted failed', [
                'idea_id'   => $ideaId,
                'author_id' => $authorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the idea author that someone voted on their idea.
     *
     * @param int $ideaId    The challenge_ideas row id
     * @param int $authorId  The user who owns the idea
     * @param int $voterId   The user who cast the vote
     */
    public function notifyIdeaVoted(int $ideaId, int $authorId, int $voterId): void
    {
        if ($authorId === $voterId) {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $idea = DB::table('challenge_ideas')
                ->where('id', $ideaId)
                ->first();

            $author = DB::selectOne(
                'SELECT id, email, first_name, name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$authorId, $tenantId]
            );

            if (! $author || empty($author->email)) {
                return;
            }

            $voter = DB::selectOne(
                'SELECT name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$voterId, $tenantId]
            );

            $firstName  = $author->first_name ?? $author->name ?? __('emails.common.fallback_name');
            $ideaTitle  = $idea->title ?? '';
            $voterName  = $voter->name ?? __('emails.common.fallback_someone');
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $ideaUrl    = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/ideation/' . ($idea->challenge_id ?? 0)
                . '/ideas/' . $ideaId;

            $html = EmailTemplateBuilder::make()
                ->theme('brand')
                ->title(__('emails_ideation.notifications.voted_title'))
                ->previewText(__('emails_ideation.notifications.voted_preview', [
                    'voter' => $voterName,
                    'title' => $ideaTitle,
                ]))
                ->greeting($firstName)
                ->paragraph(__('emails_ideation.notifications.voted_body', [
                    'voter' => htmlspecialchars($voterName, ENT_QUOTES, 'UTF-8'),
                    'title' => htmlspecialchars($ideaTitle, ENT_QUOTES, 'UTF-8'),
                ]))
                ->button(__('emails_ideation.notifications.voted_cta'), $ideaUrl)
                ->render();

            Mailer::forCurrentTenant()->send(
                $author->email,
                __('emails_ideation.notifications.voted_subject', ['community' => $tenantName]),
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('[IdeationEmailService] notifyIdeaVoted failed', [
                'idea_id'   => $ideaId,
                'author_id' => $authorId,
                'voter_id'  => $voterId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the idea author that a new comment was posted on their idea.
     *
     * @param int    $ideaId         The challenge_ideas row id
     * @param int    $authorId       The user who owns the idea
     * @param int    $commenterId    The user who posted the comment
     * @param string $commentPreview A short text preview of the comment
     */
    public function notifyIdeaCommented(int $ideaId, int $authorId, int $commenterId, string $commentPreview): void
    {
        if ($authorId === $commenterId) {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $idea = DB::table('challenge_ideas')
                ->where('id', $ideaId)
                ->first();

            $author = DB::selectOne(
                'SELECT id, email, first_name, name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$authorId, $tenantId]
            );

            if (! $author || empty($author->email)) {
                return;
            }

            $commenter = DB::selectOne(
                'SELECT name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$commenterId, $tenantId]
            );

            $firstName     = $author->first_name ?? $author->name ?? __('emails.common.fallback_name');
            $ideaTitle     = $idea->title ?? '';
            $commenterName = $commenter->name ?? __('emails.common.fallback_someone');
            $tenantName    = TenantContext::getSetting('site_name', 'Project NEXUS');
            $shortPreview  = strlen($commentPreview) > 120 ? substr($commentPreview, 0, 120) . '...' : $commentPreview;
            $commentUrl    = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/ideation/' . ($idea->challenge_id ?? 0)
                . '/ideas/' . $ideaId;

            $html = EmailTemplateBuilder::make()
                ->theme('brand')
                ->title(__('emails_ideation.notifications.commented_title'))
                ->previewText(__('emails_ideation.notifications.commented_preview', [
                    'commenter' => $commenterName,
                    'title'     => $ideaTitle,
                ]))
                ->greeting($firstName)
                ->paragraph(__('emails_ideation.notifications.commented_body', [
                    'commenter' => htmlspecialchars($commenterName, ENT_QUOTES, 'UTF-8'),
                    'title'     => htmlspecialchars($ideaTitle, ENT_QUOTES, 'UTF-8'),
                ]))
                ->highlight(htmlspecialchars($shortPreview, ENT_QUOTES, 'UTF-8'))
                ->button(__('emails_ideation.notifications.commented_cta'), $commentUrl)
                ->render();

            Mailer::forCurrentTenant()->send(
                $author->email,
                __('emails_ideation.notifications.commented_subject', ['community' => $tenantName]),
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('[IdeationEmailService] notifyIdeaCommented failed', [
                'idea_id'     => $ideaId,
                'author_id'   => $authorId,
                'commenter_id' => $commenterId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the idea author that their idea's status has changed.
     *
     * @param int    $ideaId    The challenge_ideas row id
     * @param int    $authorId  The user who owns the idea
     * @param string $newStatus The new status value (e.g. 'under_review', 'approved', 'rejected', 'implemented', 'shortlisted')
     */
    public function notifyIdeaStatusChanged(int $ideaId, int $authorId, string $newStatus): void
    {
        try {
            $tenantId = TenantContext::getId();

            $idea = DB::table('challenge_ideas')
                ->where('id', $ideaId)
                ->first();

            $author = DB::selectOne(
                'SELECT id, email, first_name, name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$authorId, $tenantId]
            );

            if (! $author || empty($author->email)) {
                return;
            }

            $firstName  = $author->first_name ?? $author->name ?? __('emails.common.fallback_name');
            $ideaTitle  = $idea->title ?? '';
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $ideaUrl    = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/ideation/' . ($idea->challenge_id ?? 0)
                . '/ideas/' . $ideaId;

            $statusLabel = match ($newStatus) {
                'under_review'  => __('notifications.ideation_status_under_review', [], null, 'en') ?: 'under review',
                'approved'      => __('notifications.ideation_status_approved', [], null, 'en') ?: 'approved',
                'rejected'      => __('notifications.ideation_status_rejected', [], null, 'en') ?: 'rejected',
                'implemented'   => __('notifications.ideation_status_implemented', [], null, 'en') ?: 'implemented',
                'shortlisted'   => __('notifications.ideation_status_shortlisted'),
                'withdrawn'     => __('notifications.ideation_status_withdrawn'),
                default         => $newStatus,
            };

            $html = EmailTemplateBuilder::make()
                ->theme('brand')
                ->title(__('emails_ideation.notifications.status_title'))
                ->previewText(__('emails_ideation.notifications.status_preview', [
                    'title'  => $ideaTitle,
                    'status' => $statusLabel,
                ]))
                ->greeting($firstName)
                ->paragraph(__('emails_ideation.notifications.status_body', [
                    'title'  => htmlspecialchars($ideaTitle, ENT_QUOTES, 'UTF-8'),
                    'status' => htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'),
                ]))
                ->button(__('emails_ideation.notifications.status_cta'), $ideaUrl)
                ->render();

            Mailer::forCurrentTenant()->send(
                $author->email,
                __('emails_ideation.notifications.status_subject', ['community' => $tenantName]),
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('[IdeationEmailService] notifyIdeaStatusChanged failed', [
                'idea_id'    => $ideaId,
                'author_id'  => $authorId,
                'new_status' => $newStatus,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the idea author that their idea was selected as a winner.
     *
     * @param int $ideaId    The challenge_ideas row id
     * @param int $authorId  The user who owns the idea
     */
    public function notifyIdeaWon(int $ideaId, int $authorId): void
    {
        try {
            $tenantId = TenantContext::getId();

            $idea = DB::table('challenge_ideas')
                ->where('id', $ideaId)
                ->first();

            $author = DB::selectOne(
                'SELECT id, email, first_name, name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$authorId, $tenantId]
            );

            if (! $author || empty($author->email)) {
                return;
            }

            $firstName  = $author->first_name ?? $author->name ?? __('emails.common.fallback_name');
            $ideaTitle  = $idea->title ?? '';
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $ideaUrl    = TenantContext::getFrontendUrl()
                . TenantContext::getSlugPrefix()
                . '/ideation/' . ($idea->challenge_id ?? 0)
                . '/ideas/' . $ideaId;

            $html = EmailTemplateBuilder::make()
                ->theme('achievement')
                ->title(__('emails_ideation.notifications.won_title'))
                ->previewText(__('emails_ideation.notifications.won_preview', [
                    'title' => $ideaTitle,
                ]))
                ->greeting($firstName)
                ->highlight(__('emails_ideation.notifications.won_body', [
                    'title' => htmlspecialchars($ideaTitle, ENT_QUOTES, 'UTF-8'),
                ]))
                ->button(__('emails_ideation.notifications.won_cta'), $ideaUrl)
                ->render();

            Mailer::forCurrentTenant()->send(
                $author->email,
                __('emails_ideation.notifications.won_subject', ['community' => $tenantName]),
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('[IdeationEmailService] notifyIdeaWon failed', [
                'idea_id'   => $ideaId,
                'author_id' => $authorId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
