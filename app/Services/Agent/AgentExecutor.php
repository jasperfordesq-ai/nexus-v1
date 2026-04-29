<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent;

use App\Services\FCMPushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — KI-Agenten executor.
 *
 * Once an admin approves a proposal, this class performs the underlying
 * action (creates the tandem, sends the nudge, assigns the coordinator,
 * etc.) and writes an audit row to agent_decisions.
 */
final class AgentExecutor
{
    /**
     * Approve a proposal: dispatch the action, mark the proposal executed,
     * log the decision, return the updated proposal row.
     *
     * @return array<string,mixed>
     */
    public static function approve(int $proposalId, int $tenantId, int $reviewerId, ?string $note = null): array
    {
        $proposal = self::findProposal($proposalId, $tenantId);
        if (!$proposal) {
            throw new \RuntimeException("Proposal {$proposalId} not found");
        }
        if ($proposal['status'] !== 'pending_review') {
            throw new \RuntimeException("Proposal {$proposalId} is not pending review (status={$proposal['status']})");
        }

        $payload = $proposal['proposal_data'];
        self::dispatchAction((string) $proposal['proposal_type'], $payload, $proposal, $tenantId);

        DB::table('agent_proposals')->where('id', $proposalId)->update([
            'status'      => 'approved',
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'applied_at'  => now(),
            'executed_at' => now(),
            'updated_at'  => now(),
        ]);

        DB::table('agent_runs')
            ->where('id', $proposal['run_id'])
            ->increment('proposals_applied');

        self::logDecision($proposalId, $tenantId, 'approve', $reviewerId, $note);

        return self::findProposal($proposalId, $tenantId) ?? [];
    }

    /**
     * Reject a proposal — no action is dispatched, status flipped to rejected.
     */
    public static function reject(int $proposalId, int $tenantId, int $reviewerId, ?string $note = null): void
    {
        $proposal = self::findProposal($proposalId, $tenantId);
        if (!$proposal) {
            throw new \RuntimeException("Proposal {$proposalId} not found");
        }

        DB::table('agent_proposals')->where('id', $proposalId)->update([
            'status'      => 'rejected',
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        self::logDecision($proposalId, $tenantId, 'reject', $reviewerId, $note);
    }

    /**
     * Edit-and-approve: replace proposal_data with `editedPayload`, then
     * dispatch the action.
     *
     * @param array<string,mixed> $editedPayload
     * @return array<string,mixed>
     */
    public static function editAndApprove(
        int $proposalId,
        int $tenantId,
        int $reviewerId,
        array $editedPayload,
        ?string $note = null,
    ): array {
        $proposal = self::findProposal($proposalId, $tenantId);
        if (!$proposal) {
            throw new \RuntimeException("Proposal {$proposalId} not found");
        }
        if ($proposal['status'] !== 'pending_review') {
            throw new \RuntimeException("Proposal {$proposalId} is not pending review");
        }

        DB::table('agent_proposals')->where('id', $proposalId)->update([
            'proposal_data' => json_encode($editedPayload),
            'updated_at'    => now(),
        ]);

        // Refresh proposal with edited data
        $proposal['proposal_data'] = $editedPayload;
        self::dispatchAction((string) $proposal['proposal_type'], $editedPayload, $proposal, $tenantId);

        DB::table('agent_proposals')->where('id', $proposalId)->update([
            'status'      => 'approved',
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'applied_at'  => now(),
            'executed_at' => now(),
            'updated_at'  => now(),
        ]);

        DB::table('agent_runs')
            ->where('id', $proposal['run_id'])
            ->increment('proposals_applied');

        self::logDecision($proposalId, $tenantId, 'edit', $reviewerId, $note, $editedPayload);

        return self::findProposal($proposalId, $tenantId) ?? [];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<string,mixed>|null
     */
    private static function findProposal(int $proposalId, int $tenantId): ?array
    {
        $row = DB::table('agent_proposals')
            ->where('id', $proposalId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$row) {
            return null;
        }
        $arr = (array) $row;
        $arr['proposal_data'] = json_decode((string) $row->proposal_data, true) ?? [];
        return $arr;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $proposal
     */
    private static function dispatchAction(string $type, array $payload, array $proposal, int $tenantId): void
    {
        switch ($type) {
            case 'create_tandem':
                if (!Schema::hasTable('caring_support_relationships')) {
                    return;
                }
                $supporterId = (int) ($payload['supporter_id'] ?? 0);
                $recipientId = (int) ($payload['recipient_id'] ?? 0);
                if (!$supporterId || !$recipientId) {
                    return;
                }
                $exists = DB::table('caring_support_relationships')
                    ->where('tenant_id', $tenantId)
                    ->where('supporter_id', $supporterId)
                    ->where('recipient_id', $recipientId)
                    ->exists();
                if (!$exists) {
                    DB::table('caring_support_relationships')->insertGetId([
                        'tenant_id'    => $tenantId,
                        'supporter_id' => $supporterId,
                        'recipient_id' => $recipientId,
                        'status'       => 'pending',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
                break;

            case 'send_nudge':
            case 'send_activity_summary':
                $userId = (int) ($proposal['subject_user_id'] ?? 0);
                if (!$userId) {
                    return;
                }
                $title = (string) ($payload['title'] ?? 'NEXUS');
                $body  = (string) ($payload['body']  ?? '');
                $extra = (array)  ($payload['extra'] ?? []);
                if (class_exists(FCMPushService::class) && method_exists(FCMPushService::class, 'sendToUsers')) {
                    FCMPushService::sendToUsers([$userId], $title, $body, $extra);
                }
                break;

            case 'route_help_request':
                if (!Schema::hasTable('caring_help_requests')) {
                    return;
                }
                $requestId = (int) ($payload['request_id'] ?? 0);
                $assignedTo = (int) ($payload['coordinator_id'] ?? ($proposal['target_user_id'] ?? 0));
                if (!$requestId || !$assignedTo) {
                    return;
                }
                DB::table('caring_help_requests')
                    ->where('id', $requestId)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'assigned_to' => $assignedTo,
                        'updated_at'  => now(),
                    ]);
                break;

            default:
                Log::warning("AgentExecutor: unknown proposal_type '{$type}'", [
                    'proposal_id' => $proposal['id'] ?? null,
                ]);
        }
    }

    /**
     * @param array<string,mixed>|null $editedPayload
     */
    private static function logDecision(
        int $proposalId,
        int $tenantId,
        string $decision,
        int $decidedBy,
        ?string $note,
        ?array $editedPayload = null,
    ): void {
        if (!Schema::hasTable('agent_decisions')) {
            return;
        }

        DB::table('agent_decisions')->insert([
            'proposal_id'    => $proposalId,
            'tenant_id'      => $tenantId,
            'decision'       => $decision,
            'decided_by'     => $decidedBy,
            'decision_note'  => $note,
            'edited_payload' => $editedPayload !== null ? json_encode($editedPayload) : null,
            'decided_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
