<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent\Agents;

use App\Services\CaringTandemMatchingService;

/**
 * AG61 — Tandem matchmaker agent.
 *
 * Wraps the existing CaringTandemMatchingService::suggestTandems() but adds
 * an LLM-generated "why this pair" reasoning string saved alongside the
 * proposal. Admins read the reasoning before approving.
 */
final class TandemMatchmakerAgent extends BaseAgent
{
    public function run(): array
    {
        $maxProposals = (int) ($this->config['max_proposals_per_run'] ?? 20);
        $minScore     = (float) ($this->config['min_score'] ?? 0.4);

        $service     = app(CaringTandemMatchingService::class);
        $suggestions = $service->suggestTandems($this->tenantId, $maxProposals);

        $created = 0;
        foreach ($suggestions as $pair) {
            $score = (float) ($pair['score'] ?? 0.0);
            if ($score < $minScore || $created >= $maxProposals) {
                continue;
            }

            $supporterId = (int) ($pair['supporter']['id'] ?? 0);
            $recipientId = (int) ($pair['recipient']['id'] ?? 0);
            if (!$supporterId || !$recipientId) {
                continue;
            }

            $reasoning = $this->generateReasoning($pair);

            $this->createProposal(
                type: 'create_tandem',
                data: [
                    'supporter_id'   => $supporterId,
                    'supporter_name' => $pair['supporter']['name'] ?? '',
                    'recipient_id'   => $recipientId,
                    'recipient_name' => $pair['recipient']['name'] ?? '',
                    'signals'        => $pair['signals'] ?? [],
                ],
                reasoning: $reasoning,
                confidence: $score,
                subjectUserId: $supporterId,
                targetUserId: $recipientId,
            );
            $created++;
        }

        return [
            'proposals_created' => $created,
            'summary'           => "Generated {$created} tandem-match proposal(s).",
            'llm_input_tokens'  => $this->totalInputTokens,
            'llm_output_tokens' => $this->totalOutputTokens,
            'cost_cents'        => $this->estimateCostCents($this->totalInputTokens, $this->totalOutputTokens),
        ];
    }

    /**
     * @param array<string,mixed> $pair
     */
    private function generateReasoning(array $pair): string
    {
        $signals  = $pair['signals'] ?? [];
        $fallback = (string) ($pair['reason'] ?? 'High compatibility based on profile signals.');

        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are a community-care matching assistant. Given two members and their compatibility signals, write 2 short sentences explaining why they would be a good caring tandem. Be warm, specific, and avoid making up facts.',
            ],
            [
                'role'    => 'user',
                'content' => sprintf(
                    "Supporter: %s\nRecipient: %s\nSignals: %s\nWrite the explanation:",
                    $pair['supporter']['name'] ?? 'Member A',
                    $pair['recipient']['name'] ?? 'Member B',
                    json_encode($signals, JSON_UNESCAPED_UNICODE),
                ),
            ],
        ];

        $resp = $this->callLlm($messages, ['max_tokens' => 200]);
        $content = trim($resp['content']);
        return $content !== '' ? $content : $fallback;
    }
}
