<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GroupExchangeService — Native Laravel implementation for group time exchanges.
 *
 * Manages multi-participant time credit exchanges with equal, custom, or weighted splits.
 * Tables: group_exchanges, group_exchange_participants
 */
class GroupExchangeService
{
    public function __construct()
    {
    }

    /**
     * Create a new group exchange.
     */
    public function create(int $organizerId, array $data): ?int
    {
        $tenantId = TenantContext::getId();

        $id = DB::table('group_exchanges')->insertGetId([
            'tenant_id'    => $tenantId,
            'title'        => trim($data['title'] ?? ''),
            'description'  => trim($data['description'] ?? '') ?: null,
            'organizer_id' => $organizerId,
            'listing_id'   => $data['listing_id'] ?? null,
            'status'       => $data['status'] ?? 'draft',
            'split_type'   => $data['split_type'] ?? 'equal',
            'total_hours'  => (float) ($data['total_hours'] ?? 0),
            'broker_id'    => $data['broker_id'] ?? null,
            'broker_notes' => $data['broker_notes'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return (int) $id;
    }

    /**
     * Get a single exchange by ID with its participants.
     */
    public function get(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $exchange = DB::table('group_exchanges')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $exchange) {
            return null;
        }

        $participants = DB::table('group_exchange_participants as p')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->where('p.group_exchange_id', $id)
            ->select([
                'p.id as participant_id',
                'p.user_id',
                'p.role',
                'p.hours',
                'p.weight',
                'p.confirmed',
                'p.confirmed_at',
                'p.notes',
                'p.created_at',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
            ])
            ->get();

        $participantList = $participants->map(fn ($p) => [
            'id'           => (int) $p->participant_id,
            'user_id'      => (int) $p->user_id,
            'name'         => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
            'avatar_url'   => $p->avatar_url,
            'role'         => $p->role,
            'hours'        => (float) $p->hours,
            'weight'       => (float) $p->weight,
            'confirmed'    => (bool) $p->confirmed,
            'confirmed_at' => $p->confirmed_at,
            'notes'        => $p->notes,
        ])->all();

        return [
            'id'            => (int) $exchange->id,
            'tenant_id'     => (int) $exchange->tenant_id,
            'title'         => $exchange->title,
            'description'   => $exchange->description,
            'organizer_id'  => (int) $exchange->organizer_id,
            'listing_id'    => $exchange->listing_id ? (int) $exchange->listing_id : null,
            'status'        => $exchange->status,
            'split_type'    => $exchange->split_type,
            'total_hours'   => (float) $exchange->total_hours,
            'broker_id'     => $exchange->broker_id ? (int) $exchange->broker_id : null,
            'broker_notes'  => $exchange->broker_notes,
            'completed_at'  => $exchange->completed_at,
            'created_at'    => $exchange->created_at,
            'updated_at'    => $exchange->updated_at,
            'participants'  => $participantList,
        ];
    }

    /**
     * List exchanges for a user (as organizer or participant).
     *
     * @return array{items: array, has_more: bool}
     */
    public function listForUser(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = (int) ($filters['offset'] ?? 0);

        $query = DB::table('group_exchanges as e')
            ->where('e.tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('e.organizer_id', $userId)
                  ->orWhereExists(function ($sub) use ($userId) {
                      $sub->select(DB::raw(1))
                          ->from('group_exchange_participants')
                          ->whereColumn('group_exchange_participants.group_exchange_id', 'e.id')
                          ->where('group_exchange_participants.user_id', $userId);
                  });
            });

        if (! empty($filters['status'])) {
            $query->where('e.status', $filters['status']);
        }

        $query->orderByDesc('e.id');

        $exchanges = $query->offset($offset)->limit($limit + 1)->get();

        $hasMore = $exchanges->count() > $limit;
        if ($hasMore) {
            $exchanges->pop();
        }

        $items = $exchanges->map(fn ($e) => [
            'id'           => (int) $e->id,
            'title'        => $e->title,
            'description'  => $e->description,
            'organizer_id' => (int) $e->organizer_id,
            'status'       => $e->status,
            'split_type'   => $e->split_type,
            'total_hours'  => (float) $e->total_hours,
            'created_at'   => $e->created_at,
            'updated_at'   => $e->updated_at,
        ])->all();

        return [
            'items'    => $items,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Add a participant to an exchange.
     */
    public function addParticipant(int $exchangeId, int $userId, string $role, float $hours = 0, float $weight = 1.0): bool
    {
        // Check if already exists
        $exists = DB::table('group_exchange_participants')
            ->where('group_exchange_id', $exchangeId)
            ->where('user_id', $userId)
            ->where('role', $role)
            ->exists();

        if ($exists) {
            return false;
        }

        // Safeguarding: block adding participants who require broker approval
        // (set when a vulnerable person ticks safeguarding checkboxes during onboarding)
        $tenantId = \App\Core\TenantContext::getId();
        $hasSafeguardingRestriction = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('requires_broker_approval', 1)
            ->where(function ($q) {
                $q->whereNull('monitoring_expires_at')
                  ->orWhere('monitoring_expires_at', '>', now());
            })
            ->exists();

        if ($hasSafeguardingRestriction) {
            \Illuminate\Support\Facades\Log::info('[GroupExchange] Blocked: participant has safeguarding restrictions', [
                'exchange_id' => $exchangeId,
                'user_id' => $userId,
            ]);
            return false;
        }

        DB::table('group_exchange_participants')->insert([
            'group_exchange_id' => $exchangeId,
            'user_id'           => $userId,
            'role'              => $role,
            'hours'             => $hours,
            'weight'            => $weight,
            'confirmed'         => 0,
            'created_at'        => now(),
        ]);

        return true;
    }

    /**
     * Remove a participant from an exchange.
     */
    public function removeParticipant(int $exchangeId, int $userId): bool
    {
        $deleted = DB::table('group_exchange_participants')
            ->where('group_exchange_id', $exchangeId)
            ->where('user_id', $userId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Calculate the hour split for an exchange based on its split type.
     *
     * @return array<int, array{user_id: int, role: string, hours: float}>
     */
    public function calculateSplit(int $exchangeId): array
    {
        $tenantId = TenantContext::getId();

        $exchange = DB::table('group_exchanges')
            ->where('id', $exchangeId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $exchange) {
            return [];
        }

        $participants = DB::table('group_exchange_participants')
            ->where('group_exchange_id', $exchangeId)
            ->get();

        if ($participants->isEmpty()) {
            return [];
        }

        $totalHours = (float) $exchange->total_hours;
        $splitType = $exchange->split_type;

        $result = [];

        if ($splitType === 'custom') {
            // Custom: each participant already has their hours set
            foreach ($participants as $p) {
                $result[] = [
                    'user_id' => (int) $p->user_id,
                    'role'    => $p->role,
                    'hours'   => (float) $p->hours,
                ];
            }
        } elseif ($splitType === 'weighted') {
            // Weighted: distribute total_hours proportionally by weight within each role
            $byRole = $participants->groupBy('role');

            foreach ($byRole as $roleParticipants) {
                $totalWeight = $roleParticipants->sum('weight');
                if ($totalWeight <= 0) {
                    $totalWeight = $roleParticipants->count();
                }

                foreach ($roleParticipants as $p) {
                    $weight = (float) ($p->weight ?: 1.0);
                    $hours = ($weight / $totalWeight) * $totalHours;

                    $result[] = [
                        'user_id' => (int) $p->user_id,
                        'role'    => $p->role,
                        'hours'   => round($hours, 2),
                    ];
                }
            }
        } else {
            // Equal: split total_hours equally within each role
            $byRole = $participants->groupBy('role');

            foreach ($byRole as $roleParticipants) {
                $count = $roleParticipants->count();
                $hoursEach = $count > 0 ? round($totalHours / $count, 2) : 0;

                foreach ($roleParticipants as $p) {
                    $result[] = [
                        'user_id' => (int) $p->user_id,
                        'role'    => $p->role,
                        'hours'   => $hoursEach,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Update an exchange's fields.
     */
    public function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $allowed = ['title', 'description', 'split_type', 'total_hours', 'broker_id', 'broker_notes', 'listing_id'];
        $updates = collect($data)->only($allowed)->filter(fn ($v) => $v !== null)->all();

        if (empty($updates)) {
            return true;
        }

        $updates['updated_at'] = now();

        $affected = DB::table('group_exchanges')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        return $affected >= 0;
    }

    /**
     * Update the status of an exchange.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $tenantId = TenantContext::getId();

        $updates = [
            'status'     => $status,
            'updated_at' => now(),
        ];

        if ($status === 'completed') {
            $updates['completed_at'] = now();
        }

        $affected = DB::table('group_exchanges')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        return $affected > 0;
    }

    /**
     * Confirm a user's participation in an exchange.
     */
    public function confirmParticipation(int $exchangeId, int $userId): bool
    {
        $affected = DB::table('group_exchange_participants')
            ->where('group_exchange_id', $exchangeId)
            ->where('user_id', $userId)
            ->where('confirmed', 0)
            ->update([
                'confirmed'    => 1,
                'confirmed_at' => now(),
            ]);

        return $affected > 0;
    }

    /**
     * Complete an exchange: verify all participants confirmed, create wallet transactions.
     *
     * @return array{success: bool, error?: string, transaction_ids?: array}
     */
    public function complete(int $exchangeId): array
    {
        $tenantId = TenantContext::getId();

        $exchange = DB::table('group_exchanges')
            ->where('id', $exchangeId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $exchange) {
            return ['success' => false, 'error' => 'Exchange not found'];
        }

        if ($exchange->status === 'completed') {
            return ['success' => false, 'error' => 'Exchange is already completed'];
        }

        if ($exchange->status === 'cancelled') {
            return ['success' => false, 'error' => 'Exchange has been cancelled'];
        }

        // Check all participants have confirmed
        $unconfirmed = DB::table('group_exchange_participants')
            ->where('group_exchange_id', $exchangeId)
            ->where('confirmed', 0)
            ->count();

        if ($unconfirmed > 0) {
            return ['success' => false, 'error' => "Not all participants have confirmed ({$unconfirmed} remaining)"];
        }

        $split = $this->calculateSplit($exchangeId);
        if (empty($split)) {
            return ['success' => false, 'error' => 'No participants to split hours'];
        }

        $transactionIds = [];

        DB::transaction(function () use ($exchangeId, $exchange, $split, $tenantId, &$transactionIds) {
            // Create wallet transactions for each participant
            foreach ($split as $entry) {
                if ((float) $entry['hours'] <= 0) {
                    continue;
                }

                $txnType = $entry['role'] === 'provider' ? 'credit' : 'debit';

                $txnId = DB::table('wallet_transactions')->insertGetId([
                    'tenant_id'      => $tenantId,
                    'user_id'        => $entry['user_id'],
                    'amount'         => $entry['hours'],
                    'type'           => $txnType,
                    'description'    => "Group exchange: {$exchange->title}",
                    'reference_type' => 'group_exchange',
                    'reference_id'   => $exchangeId,
                    'created_at'     => now(),
                ]);

                $transactionIds[] = (int) $txnId;

                // Update user wallet balance
                $balanceChange = $txnType === 'credit' ? $entry['hours'] : -$entry['hours'];
                DB::table('users')
                    ->where('id', $entry['user_id'])
                    ->where('tenant_id', $tenantId)
                    ->increment('time_balance', $balanceChange);
            }

            // Mark exchange as completed
            DB::table('group_exchanges')
                ->where('id', $exchangeId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'updated_at'   => now(),
                ]);
        });

        return [
            'success'         => true,
            'transaction_ids' => $transactionIds,
        ];
    }
}
