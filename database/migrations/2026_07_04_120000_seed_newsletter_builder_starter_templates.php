<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed curated MJML "starter" templates for the drag-and-drop newsletter
 * Design Studio, for EVERY active tenant.
 *
 * Why: the Design tab previously opened onto a near-blank canvas, so
 * non-technical staff had nothing to build from. These starters are stored as
 * MJML markup in `content` with content_format='builder' — the studio seeds the
 * editor from that markup (setComponents), so they parse into real mj-*
 * components and export to inbox-safe table HTML on send.
 *
 * design_json is intentionally NULL: MJML markup is far easier to author and
 * maintain than a hand-written GrapesJS project blob, and the editor derives
 * design_json on the first edit. The gallery shows a neutral placeholder for
 * MJML content (it can't be iframe-previewed as raw tags).
 *
 * Idempotent: skips any (tenant, name, category='starter') that already exists,
 * so re-running — and running alongside the tenant-2 backfill migration — is a
 * safe no-op.
 */
return new class extends Migration {
    /** @return array<int,array{name:string,description:string,subject:string,preview_text:string,content:string}> */
    private function starters(): array
    {
        $footer =
            '<mj-section background-color="#ffffff" padding="0 24px 20px">'
            . '<mj-column>'
            . '<mj-divider border-color="#e5e7eb" border-width="1px" />'
            . '<mj-text align="center" font-size="12px" color="#9ca3af" line-height="18px">'
            . 'You are receiving this email as a member of {{tenant_name}}.<br />'
            . '<a href="{{unsubscribe_link}}" style="color:#9ca3af;">Unsubscribe</a>'
            . '</mj-text>'
            . '</mj-column>'
            . '</mj-section>';

        return [
            [
                'name' => 'Announcement',
                'description' => 'A clean single-message layout with a headline, a short paragraph and one call-to-action button.',
                'subject' => 'A quick update from {{tenant_name}}',
                'preview_text' => 'We have some news to share.',
                'content' =>
                    '<mjml><mj-body background-color="#f4f4f5">'
                    . '<mj-section background-color="#ffffff" padding="28px 24px 8px">'
                    . '<mj-column>'
                    . '<mj-text font-size="24px" font-weight="700" color="#111827" line-height="30px">A big announcement</mj-text>'
                    . '<mj-text font-size="15px" color="#374151" line-height="24px">Hi {{first_name}}, we have some news to share with the community. Replace this text with your update — keep it short and lead with what matters most.</mj-text>'
                    . '<mj-button background-color="#2563eb" color="#ffffff" border-radius="8px" font-size="15px" href="#" padding="16px 0 8px">Read more</mj-button>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . $footer
                    . '</mj-body></mjml>',
            ],
            [
                'name' => 'Newsletter digest',
                'description' => 'A multi-story digest: a title plus three stacked content blocks separated by dividers.',
                'subject' => '{{tenant_name}} — this month’s highlights',
                'preview_text' => 'The latest from around the community.',
                'content' =>
                    '<mjml><mj-body background-color="#f4f4f5">'
                    . '<mj-section background-color="#ffffff" padding="28px 24px 4px">'
                    . '<mj-column>'
                    . '<mj-text font-size="22px" font-weight="700" color="#111827">This month at {{tenant_name}}</mj-text>'
                    . '<mj-text font-size="14px" color="#6b7280">Hi {{first_name}}, here is what has been happening.</mj-text>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . '<mj-section background-color="#ffffff" padding="4px 24px">'
                    . '<mj-column>'
                    . '<mj-text font-size="17px" font-weight="700" color="#111827">First story</mj-text>'
                    . '<mj-text font-size="14px" color="#374151" line-height="22px">Summarise your first update here and link out for the full detail.</mj-text>'
                    . '<mj-divider border-color="#e5e7eb" border-width="1px" padding="12px 0" />'
                    . '<mj-text font-size="17px" font-weight="700" color="#111827">Second story</mj-text>'
                    . '<mj-text font-size="14px" color="#374151" line-height="22px">Add another short update — events, member spotlights or opportunities all work well.</mj-text>'
                    . '<mj-divider border-color="#e5e7eb" border-width="1px" padding="12px 0" />'
                    . '<mj-text font-size="17px" font-weight="700" color="#111827">Third story</mj-text>'
                    . '<mj-text font-size="14px" color="#374151" line-height="22px">Round things off with a final note or a call to get involved.</mj-text>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . $footer
                    . '</mj-body></mjml>',
            ],
            [
                'name' => 'Event invite',
                'description' => 'Invite members to an event: title, the key details, and an RSVP button.',
                'subject' => 'You’re invited — {{tenant_name}}',
                'preview_text' => 'Save the date and RSVP.',
                'content' =>
                    '<mjml><mj-body background-color="#f4f4f5">'
                    . '<mj-section background-color="#111827" padding="28px 24px">'
                    . '<mj-column>'
                    . '<mj-text align="center" font-size="13px" letter-spacing="2px" color="#93c5fd">YOU’RE INVITED</mj-text>'
                    . '<mj-text align="center" font-size="26px" font-weight="700" color="#ffffff" line-height="32px">Community Get-Together</mj-text>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . '<mj-section background-color="#ffffff" padding="24px">'
                    . '<mj-column>'
                    . '<mj-text font-size="15px" color="#374151" line-height="24px">Hi {{first_name}}, we would love to see you there. Add the date, time and place below.</mj-text>'
                    . '<mj-text font-size="15px" color="#111827" font-weight="700" padding="8px 0 0">📅 Date &amp; time</mj-text>'
                    . '<mj-text font-size="15px" color="#111827" font-weight="700" padding="0">📍 Location</mj-text>'
                    . '<mj-button background-color="#2563eb" color="#ffffff" border-radius="8px" href="#" padding="16px 0 4px">RSVP now</mj-button>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . $footer
                    . '</mj-body></mjml>',
            ],
            [
                'name' => 'Single call-to-action',
                'description' => 'A focused, high-conversion layout: one bold message and one button.',
                'subject' => 'One thing we’d love you to do',
                'preview_text' => 'It only takes a moment.',
                'content' =>
                    '<mjml><mj-body background-color="#f4f4f5">'
                    . '<mj-section background-color="#ffffff" padding="40px 24px">'
                    . '<mj-column>'
                    . '<mj-text align="center" font-size="26px" font-weight="700" color="#111827" line-height="34px">Ready to take the next step?</mj-text>'
                    . '<mj-text align="center" font-size="15px" color="#374151" line-height="24px" padding="8px 0 12px">Hi {{first_name}}, replace this with one clear, compelling sentence about what you want members to do.</mj-text>'
                    . '<mj-button background-color="#16a34a" color="#ffffff" border-radius="8px" font-size="16px" href="#">Get started</mj-button>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . $footer
                    . '</mj-body></mjml>',
            ],
            [
                'name' => 'Welcome',
                'description' => 'Warmly welcome a new member with an intro and a first-step button.',
                'subject' => 'Welcome to {{tenant_name}}!',
                'preview_text' => 'We’re glad you’re here.',
                'content' =>
                    '<mjml><mj-body background-color="#f4f4f5">'
                    . '<mj-section background-color="#ffffff" padding="32px 24px 8px">'
                    . '<mj-column>'
                    . '<mj-text font-size="24px" font-weight="700" color="#111827">Welcome, {{first_name}} 👋</mj-text>'
                    . '<mj-text font-size="15px" color="#374151" line-height="24px">We are delighted to have you as part of {{tenant_name}}. Here is a little about how to get the most out of your membership — edit this to suit your community.</mj-text>'
                    . '<mj-button background-color="#2563eb" color="#ffffff" border-radius="8px" href="#" padding="16px 0 8px">Explore the community</mj-button>'
                    . '</mj-column>'
                    . '</mj-section>'
                    . $footer
                    . '</mj-body></mjml>',
            ],
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('newsletter_templates') || !Schema::hasTable('tenants')) {
            return;
        }

        $tenantIds = DB::table('tenants')->where('is_active', 1)->pluck('id');
        if ($tenantIds->isEmpty()) {
            return;
        }

        $now = now();
        $inserted = 0;
        foreach ($tenantIds as $tenantId) {
            foreach ($this->starters() as $tpl) {
                $exists = DB::table('newsletter_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $tpl['name'])
                    ->where('category', 'starter')
                    ->exists();
                if ($exists) {
                    continue;
                }
                try {
                    DB::table('newsletter_templates')->insert([
                        'tenant_id' => $tenantId,
                        'name' => $tpl['name'],
                        'description' => $tpl['description'],
                        'category' => 'starter',
                        'subject' => $tpl['subject'],
                        'preview_text' => $tpl['preview_text'],
                        'content' => $tpl['content'],
                        'content_format' => 'builder',
                        'design_json' => null,
                        'thumbnail' => null,
                        'is_active' => 1,
                        'use_count' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                } catch (\Throwable $e) {
                    // Best-effort per row (schema variance across tenants).
                }
            }
        }

        if ($inserted > 0 && app()->runningInConsole()) {
            fwrite(STDOUT, "  Seeded {$inserted} newsletter builder starter templates across "
                . count($tenantIds) . " tenants.\n");
        }
    }

    public function down(): void
    {
        // Non-destructive: an admin may have edited or duplicated a starter.
    }
};
