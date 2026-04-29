<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — Nudge drafter agent.
 *
 * Finds members who haven't logged in for >= 14 days and drafts a gentle
 * "we miss you" nudge in the member's preferred language. The proposal must
 * be approved by an admin before any push/notification is sent.
 */
final class NudgeDrafterAgent extends BaseAgent
{
    public function run(): array
    {
        if (!Schema::hasTable('users')) {
            return $this->emptyResult('users table not found');
        }

        $maxProposals = (int) ($this->config['max_proposals_per_run'] ?? 20);
        $minIdleDays  = (int) ($this->config['min_idle_days'] ?? 14);

        $cutoff = now()->subDays($minIdleDays)->toDateTimeString();

        $candidates = DB::table('users')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_login')
                  ->orWhere('last_login', '<', $cutoff);
            })
            ->whereNotNull('email')
            ->select(['id', 'name', 'preferred_language', 'last_login'])
            ->limit($maxProposals)
            ->get();

        $created = 0;
        foreach ($candidates as $user) {
            $locale = $user->preferred_language ?: 'en';
            $name   = (string) ($user->name ?? 'friend');

            $draft = $this->draftMessage($name, $locale);

            $this->createProposal(
                type: 'send_nudge',
                data: [
                    'title'  => $draft['title'],
                    'body'   => $draft['body'],
                    'extra'  => ['type' => 'inactivity_nudge', 'locale' => $locale],
                ],
                reasoning: sprintf(
                    'Member has been inactive since %s (>%d days). Drafting a gentle re-engagement nudge in %s.',
                    $user->last_login ?: 'never',
                    $minIdleDays,
                    $locale,
                ),
                confidence: 0.55,
                subjectUserId: (int) $user->id,
            );
            $created++;
        }

        return [
            'proposals_created' => $created,
            'summary'           => "Drafted {$created} re-engagement nudge(s) for inactive members.",
            'llm_input_tokens'  => $this->totalInputTokens,
            'llm_output_tokens' => $this->totalOutputTokens,
            'cost_cents'        => $this->estimateCostCents($this->totalInputTokens, $this->totalOutputTokens),
        ];
    }

    /**
     * @return array{title:string, body:string}
     */
    private function draftMessage(string $name, string $locale): array
    {
        $fallback = [
            'title' => 'We miss you!',
            'body'  => "Hi {$name}, your community has new activity waiting for you. Pop in and say hi.",
        ];

        $messages = [
            [
                'role'    => 'system',
                'content' => "You write short, warm community re-engagement nudges in the user's preferred language. Output strict JSON: {\"title\":\"...\",\"body\":\"...\"}. Title <= 8 words. Body <= 30 words. Locale code: {$locale}. No markdown, no emojis.",
            ],
            [
                'role'    => 'user',
                'content' => "Recipient first name: {$name}. Locale: {$locale}. Tone: warm, gentle, not pushy. Mention that the community has activity waiting.",
            ],
        ];

        $resp = $this->callLlm($messages, ['max_tokens' => 200, 'temperature' => 0.5]);
        $content = trim($resp['content']);
        if ($content === '') {
            return $fallback;
        }

        // Strip code fences if any
        $content = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content);
        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['title'], $decoded['body'])) {
            return $fallback;
        }

        return [
            'title' => (string) $decoded['title'],
            'body'  => (string) $decoded['body'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyResult(string $reason): array
    {
        return [
            'proposals_created' => 0,
            'summary'           => "No proposals: {$reason}",
            'llm_input_tokens'  => 0,
            'llm_output_tokens' => 0,
            'cost_cents'        => 0,
        ];
    }
}
