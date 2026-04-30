<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AG89 — Municipal Communication & Moderation Copilot.
 *
 * Records auditable proposals for municipal admin announcements before they
 * are published. Each proposal stores the original draft, a polished version,
 * tone assessment, clarity warnings, audience suggestion and moderation flags
 * so a coordinator can later see WHO ran WHAT prompt and what the AI returned.
 *
 * Storage strategy: rolling JSON buffer under tenant_settings setting_key
 * `caring.municipal_copilot.proposals`. Capped at MAX_PROPOSALS per tenant —
 * keeps the deploy story migration-free while still giving an audit trail.
 *
 * Analysis backend: when env('OPENAI_API_KEY') is non-empty, calls
 * https://api.openai.com/v1/chat/completions with model `gpt-4o-mini` and
 * parses a strict JSON response. Otherwise, returns a deterministic offline
 * stub so the page remains usable without an OpenAI key configured.
 *
 * NOT a publish path — accepting a proposal only marks it accepted. The
 * controller/coordinator must then publish via the existing announcement
 * surface (e.g. EmergencyAlertController::store) and call markPublished()
 * with the resulting source_announcement_id.
 */
class MunicipalCommunicationCopilotService
{
    public const SETTING_KEY = 'caring.municipal_copilot.proposals';

    public const MAX_PROPOSALS = 50;

    public const STATUSES = ['proposed', 'accepted', 'rejected', 'published'];

    public const TONE_VALUES = ['too_formal', 'too_informal', 'condescending', 'ok'];

    public const AUDIENCES = [
        'all_members',
        'caregivers',
        'care_recipients',
        'volunteers',
        'coordinators',
        'verified_only',
        'sub_region',
    ];

    /**
     * Generate and persist a new proposal for the given draft.
     *
     * @return array<string, mixed>
     */
    public function generateProposal(
        int $tenantId,
        int $adminUserId,
        string $draft,
        ?string $audienceHint,
        ?string $subRegionId,
    ): array {
        $analysis = $this->analyseDraft($draft);

        $now = now()->toIso8601String();
        $proposal = [
            'id'                    => $this->generateId(),
            'draft_text'            => $draft,
            'polished_text'         => (string) ($analysis['polished_text'] ?? $draft),
            'tone_assessment'       => (string) ($analysis['tone_assessment'] ?? 'ok'),
            'clarity_warnings'      => $this->normaliseStringList($analysis['clarity_warnings'] ?? []),
            'audience_suggestion'   => (string) ($analysis['audience_suggestion'] ?? ($audienceHint ?: 'all_members')),
            'audience_hint'         => $audienceHint ?: '',
            'sub_region_id'         => $subRegionId ?: null,
            'moderation_flags'      => $this->normaliseStringList($analysis['moderation_flags'] ?? []),
            'model_used'            => (string) ($analysis['model_used'] ?? 'stub'),
            'created_by'            => $adminUserId,
            'created_at'            => $now,
            'status'                => 'proposed',
            'accepted_at'           => null,
            'rejected_at'           => null,
            'rejection_reason'      => null,
            'source_announcement_id' => null,
            'updated_at'            => $now,
        ];

        $items = $this->loadItems($tenantId);
        array_unshift($items, $proposal);
        if (count($items) > self::MAX_PROPOSALS) {
            $items = array_slice($items, 0, self::MAX_PROPOSALS);
        }
        $this->save($tenantId, $items);

        return $proposal;
    }

    /**
     * Return proposals (newest first), bounded by $limit.
     *
     * @return list<array<string, mixed>>
     */
    public function listProposals(int $tenantId, int $limit = 20): array
    {
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > self::MAX_PROPOSALS) {
            $limit = self::MAX_PROPOSALS;
        }

        return array_values(array_slice($this->loadItems($tenantId), 0, $limit));
    }

    public function getProposal(int $tenantId, string $proposalId): ?array
    {
        $items = $this->loadItems($tenantId);
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $proposalId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Mark a proposal accepted. Optional editedFields lets the admin override
     * the polished_text and audience before acceptance — those overrides are
     * recorded directly onto the proposal.
     *
     * NOTE: this method does NOT publish the announcement. The caller (the
     * controller) is responsible for invoking the existing announcement
     * publish path, then calling markPublished() with the resulting
     * source_announcement_id.
     *
     * @param array<string, mixed>|null $editedFields
     * @return array<string, mixed>|null
     */
    public function acceptProposal(
        int $tenantId,
        string $proposalId,
        ?array $editedFields,
        int $adminUserId,
    ): ?array {
        $items = $this->loadItems($tenantId);
        $idx = $this->findIndex($items, $proposalId);
        if ($idx === null) {
            return null;
        }

        $now = now()->toIso8601String();
        $existing = $items[$idx];

        if ($editedFields !== null) {
            if (isset($editedFields['edited_polished_text']) && is_string($editedFields['edited_polished_text'])) {
                $existing['polished_text'] = $editedFields['edited_polished_text'];
            }
            if (isset($editedFields['edited_audience']) && is_string($editedFields['edited_audience']) && $editedFields['edited_audience'] !== '') {
                $existing['audience_suggestion'] = $editedFields['edited_audience'];
            }
        }

        $existing['status']        = 'accepted';
        $existing['accepted_at']   = $now;
        $existing['rejected_at']   = null;
        $existing['rejection_reason'] = null;
        $existing['updated_at']    = $now;
        $existing['accepted_by']   = $adminUserId;

        $items[$idx] = $existing;
        $this->save($tenantId, $items);

        return $existing;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rejectProposal(int $tenantId, string $proposalId, string $reason, int $adminUserId): ?array
    {
        $items = $this->loadItems($tenantId);
        $idx = $this->findIndex($items, $proposalId);
        if ($idx === null) {
            return null;
        }

        $now = now()->toIso8601String();
        $existing = $items[$idx];
        $existing['status']           = 'rejected';
        $existing['rejected_at']      = $now;
        $existing['accepted_at']      = null;
        $existing['rejection_reason'] = trim($reason);
        $existing['rejected_by']      = $adminUserId;
        $existing['updated_at']       = $now;

        $items[$idx] = $existing;
        $this->save($tenantId, $items);

        return $existing;
    }

    /**
     * Stamp a proposal as published once the announcement has been created
     * via the existing publish path.
     *
     * @return array<string, mixed>|null
     */
    public function markPublished(int $tenantId, string $proposalId, int $sourceAnnouncementId): ?array
    {
        $items = $this->loadItems($tenantId);
        $idx = $this->findIndex($items, $proposalId);
        if ($idx === null) {
            return null;
        }

        $now = now()->toIso8601String();
        $existing = $items[$idx];
        $existing['status']                 = 'published';
        $existing['source_announcement_id'] = $sourceAnnouncementId;
        $existing['updated_at']             = $now;

        $items[$idx] = $existing;
        $this->save($tenantId, $items);

        return $existing;
    }

    // ------------------------------------------------------------------
    // Analysis
    // ------------------------------------------------------------------

    /**
     * Run AI analysis on the draft. Falls back to a deterministic stub when
     * no OPENAI_API_KEY env is configured, or when the OpenAI call fails for
     * any reason — so the page is always usable.
     *
     * @return array{
     *     polished_text: string,
     *     tone_assessment: string,
     *     clarity_warnings: list<string>,
     *     audience_suggestion: string,
     *     moderation_flags: list<string>,
     *     model_used: string,
     * }
     */
    private function analyseDraft(string $draft): array
    {
        $apiKey = (string) env('OPENAI_API_KEY', '');
        $stub = [
            'polished_text'       => $draft,
            'tone_assessment'     => 'ok',
            'clarity_warnings'    => [],
            'audience_suggestion' => 'all_members',
            'moderation_flags'    => [],
            'model_used'          => 'stub',
        ];

        if ($apiKey === '') {
            return $stub;
        }

        $systemPrompt = <<<'PROMPT'
You are an editorial copilot for a municipal community-care platform. The
user is an admin drafting an announcement to elderly residents, caregivers
and volunteers. Review the draft and return ONLY a JSON object — no prose,
no markdown — with these exact keys:

{
  "polished_text": string,
  "tone_assessment": "too_formal" | "too_informal" | "condescending" | "ok",
  "clarity_warnings": string[],
  "audience_suggestion": "all_members" | "caregivers" | "care_recipients" | "volunteers" | "coordinators" | "verified_only" | "sub_region",
  "moderation_flags": string[]
}

Rules:
- polished_text: a clearer, kinder, accessible rewrite of the draft. Preserve
  factual content. No invented facts. No emoji. Keep within ~250 words.
- tone_assessment: pick exactly one of the four allowed values.
- clarity_warnings: short, specific warnings (e.g. "long sentence in
  paragraph 2", "jargon: 'AHV-Anmeldung'", "missing call to action").
- audience_suggestion: pick exactly one of the seven allowed values based
  on the content.
- moderation_flags: short flags for unsafe / non-compliant phrasing
  (e.g. "implies medical claim", "reads as fundraising appeal",
  "personal data risk"). Empty array if none.

Return strict JSON. No leading/trailing commentary.
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o-mini',
                    'temperature'     => 0.4,
                    'max_tokens'      => 800,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Draft:\n\n" . $draft],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('AG89 copilot OpenAI non-200', [
                    'status' => $response->status(),
                ]);
                return $stub;
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            if (!is_string($content) || $content === '') {
                return $stub;
            }

            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                return $stub;
            }

            return [
                'polished_text'       => isset($parsed['polished_text']) && is_string($parsed['polished_text'])
                    ? $parsed['polished_text']
                    : $draft,
                'tone_assessment'     => $this->normaliseTone($parsed['tone_assessment'] ?? null),
                'clarity_warnings'    => $this->normaliseStringList($parsed['clarity_warnings'] ?? []),
                'audience_suggestion' => $this->normaliseAudience($parsed['audience_suggestion'] ?? null),
                'moderation_flags'    => $this->normaliseStringList($parsed['moderation_flags'] ?? []),
                'model_used'          => 'gpt-4o-mini',
            ];
        } catch (\Throwable $e) {
            Log::warning('AG89 copilot OpenAI exception', ['msg' => $e->getMessage()]);
            return $stub;
        }
    }

    private function normaliseTone(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::TONE_VALUES, true)) {
            return $value;
        }
        return 'ok';
    }

    private function normaliseAudience(mixed $value): string
    {
        if (is_string($value) && in_array($value, self::AUDIENCES, true)) {
            return $value;
        }
        return 'all_members';
    }

    /**
     * @return list<string>
     */
    private function normaliseStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = mb_substr(trim($item), 0, 280);
            }
            if (count($out) >= 12) {
                break;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Storage
    // ------------------------------------------------------------------

    private function generateId(): string
    {
        return 'prop_' . substr(bin2hex(random_bytes(8)), 0, 16);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function findIndex(array $items, string $proposalId): ?int
    {
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $proposalId) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadItems(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return [];
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if (!$row || !$row->setting_value) {
            return [];
        }

        $decoded = json_decode((string) $row->setting_value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            foreach ($decoded['items'] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function save(int $tenantId, array $items): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            return;
        }

        $now = now();
        $envelope = [
            'items'      => array_values($items),
            'updated_at' => $now->toIso8601String(),
        ];

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'description'   => 'AG89 municipal communication copilot proposals (rolling buffer)',
                'updated_at'    => $now,
            ],
        );
    }
}
