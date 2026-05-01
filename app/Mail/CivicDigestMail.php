<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Mail;

use App\Core\EmailTemplateBuilder;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Services\EmailService;
use Throwable;

/**
 * AG90 — Personalised Civic Digest email.
 *
 * Renders the digest as a themed HTML email grouped by source type (safety
 * alerts first, then announcements, projects, events, etc.) and dispatches
 * via the tenant-aware Mailer.
 *
 * Always wrapped in LocaleContext::withLocale() so it renders in the
 * recipient's preferred_language, regardless of caller / queue worker locale.
 */
class CivicDigestMail
{
    /**
     * Order in which sources are rendered in the email body.
     * Safety alerts appear first because they're time-sensitive.
     */
    private const SOURCE_RENDER_ORDER = [
        'safety_alert',
        'announcement',
        'project',
        'event',
        'help_request',
        'vol_org',
        'care_provider',
        'marketplace',
        'feed_post',
    ];

    /**
     * Send the digest email to a recipient.
     *
     * @param object               $recipient  User-like object with email/first_name/last_name/preferred_language
     * @param string               $cadence    'daily' | 'weekly'
     * @param list<array<string,mixed>> $items   Digest items as returned by CivicDigestService::digestForMember()
     * @return bool                            true if EmailService::send() returned true; false otherwise
     */
    public static function send(object $recipient, string $cadence, array $items): bool
    {
        if (empty($recipient->email)) {
            return false;
        }
        if ($items === []) {
            // Service already filters empty digests upstream; defensive guard.
            return false;
        }
        $cadence = $cadence === 'weekly' ? 'weekly' : 'daily';

        return (bool) LocaleContext::withLocale($recipient->preferred_language ?? null, function () use ($recipient, $cadence, $items): bool {
            try {
                $tenantData = TenantContext::get();
                $community = (string) ($tenantData['name'] ?? 'Project NEXUS');

                $base = (string) (config('app.frontend_url') ?: 'https://app.project-nexus.ie');
                $base = rtrim($base, '/');
                $digestUrl = $base . '/caring-community/civic-digest';
                $prefsUrl = $digestUrl;

                $name = trim(((string) ($recipient->first_name ?? '')) . ' ' . ((string) ($recipient->last_name ?? '')));
                if ($name === '') {
                    $name = (string) ($recipient->name ?? __('emails.common.fallback_name'));
                }

                $count = count($items);
                $subjectKey = $cadence === 'weekly' ? 'civic_digest.email.subject_weekly' : 'civic_digest.email.subject_daily';
                $introKey   = $cadence === 'weekly' ? 'civic_digest.email.intro_weekly'   : 'civic_digest.email.intro_daily';

                $subject = __($subjectKey, ['community' => $community]);

                $builder = EmailTemplateBuilder::make()
                    ->theme('brand')
                    ->title($subject)
                    ->previewText(__($introKey, ['count' => $count, 'community' => $community]))
                    ->tenantName($community)
                    ->greeting(__('civic_digest.email.greeting', ['name' => $name]))
                    ->paragraph(__($introKey, ['count' => $count, 'community' => $community]));

                // Group items by source so we can render them in priority order
                $bySource = [];
                foreach ($items as $item) {
                    $src = (string) ($item['source'] ?? '');
                    if ($src === '') {
                        continue;
                    }
                    $bySource[$src] = $bySource[$src] ?? [];
                    $bySource[$src][] = $item;
                }

                $rendered = false;
                foreach (self::SOURCE_RENDER_ORDER as $sourceKey) {
                    if (empty($bySource[$sourceKey])) {
                        continue;
                    }

                    $sourceLabel = __('civic_digest.source_' . $sourceKey);
                    $builder->divider();
                    $builder->paragraph('<strong>' . $sourceLabel . '</strong>');

                    foreach ($bySource[$sourceKey] as $item) {
                        $title = (string) ($item['title'] ?? '');
                        $summary = self::shorten((string) ($item['summary'] ?? ''), 200);
                        $score = (int) ($item['audience_match_score'] ?? 0);
                        $linkPath = isset($item['link_path']) && is_string($item['link_path']) && $item['link_path'] !== ''
                            ? $item['link_path']
                            : null;

                        $rows = [];
                        if ($title !== '') {
                            $rows[$sourceLabel] = $title;
                        }
                        if ($summary !== '') {
                            $rows[__('civic_digest.email.match_score', ['score' => $score])] = $summary;
                        } elseif ($score > 0) {
                            // No summary but we still want the score badge visible
                            $rows[__('civic_digest.email.match_score', ['score' => $score])] = '—';
                        }

                        if ($rows !== []) {
                            $builder->infoCard($rows);
                            $rendered = true;
                        }

                        if ($linkPath !== null) {
                            $builder->button(__('civic_digest.email.cta_open'), $base . $linkPath);
                        }
                    }
                }

                if (! $rendered) {
                    // Defensive fallback — items existed but nothing was rendered (unexpected).
                    $builder->paragraph(__('civic_digest.email.empty'));
                }

                $builder->divider();
                $builder->button(__('civic_digest.email.view_all'), $digestUrl);
                $builder->paragraph(__('civic_digest.email.footer', ['community' => $community]));

                /** @var EmailService $email */
                $email = app(EmailService::class);
                $sent = $email->send(
                    (string) $recipient->email,
                    $subject,
                    $builder->render(),
                    ['unsubscribeUrl' => $prefsUrl],
                );
                return $sent === true;
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('CivicDigestMail::send failed', [
                    'user_id' => $recipient->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        });
    }

    private static function shorten(string $text, int $max): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }
}
