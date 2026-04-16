<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\Goal;
use App\Models\GoalCheckin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GoalMilestoneEmailService;

/**
 * GoalService — Eloquent-based service for goal operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class GoalService
{
    public function __construct(
        private readonly Goal $goal,
    ) {}

    /**
     * Get goals with optional filtering and cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        } else {
            $query->where('is_public', true);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visibility']) && $filters['visibility'] !== 'all') {
            $query->where('is_public', $filters['visibility'] === 'public');
        }

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get public goals available for buddy offers (excludes user's own).
     */
    public function getPublicForBuddy(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
            ->where('is_public', true)
            ->where('status', 'active')
            ->where('user_id', '!=', $userId)
            ->whereNull('mentor_id');

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get goals where user is buddy/mentor.
     */
    public function getGoalsAsMentor(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
            ->where('mentor_id', $userId);

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single goal by ID.
     */
    public function getById(int $id): ?Goal
    {
        return $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
            ->find($id);
    }

    /**
     * Create a new goal.
     */
    public function create(int $userId, array $data): Goal
    {
        $goal = $this->goal->newInstance([
            'user_id'     => $userId,
            'title'       => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'deadline'    => $data['deadline'] ?? null,
            'is_public'   => $data['is_public'] ?? true,
            'status'      => 'active',
        ]);

        $goal->save();

        // Send goal-created email
        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->select(['email', 'name', 'first_name'])
                ->first();

            if ($user && !empty($user->email)) {
                $firstName = $user->first_name ?? $user->name ?? 'there';
                $goalTitle = htmlspecialchars($goal->title ?? '', ENT_QUOTES, 'UTF-8');
                $goalUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/goals/' . $goal->id;
                $community = TenantContext::getName();

                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails_goals.created.title'))
                    ->previewText(__('emails_goals.created.preview', ['title' => $goalTitle]))
                    ->greeting($firstName)
                    ->paragraph(__('emails_goals.created.body', ['community' => $community]))
                    ->highlight($goalTitle)
                    ->button(__('emails_goals.created.cta'), $goalUrl)
                    ->render();

                Mailer::forCurrentTenant()->send(
                    $user->email,
                    __('emails_goals.created.subject', ['title' => $goalTitle, 'community' => $community]),
                    $html
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[GoalService] created email failed: ' . $e->getMessage());
        }

        return $goal->fresh(['user']);
    }

    /**
     * Update a goal (only owner).
     */
    public function update(int $id, int $userId, array $data): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $allowed = ['title', 'description', 'deadline', 'is_public', 'status'];
        $goal->fill(collect($data)->only($allowed)->all());
        $goal->save();

        return $goal->fresh(['user']);
    }

    /**
     * Delete a goal (only owner).
     */
    public function delete(int $id, int $userId): bool
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return false;
        }

        // Capture data before deletion for the email
        $goalTitle = $goal->title ?? '';
        $goalId    = $goal->id;

        $deleted = (bool) $goal->delete();

        if ($deleted) {
            // Send goal-abandoned/deleted email
            try {
                $user = DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', TenantContext::getId())
                    ->select(['email', 'name', 'first_name'])
                    ->first();

                if ($user && !empty($user->email)) {
                    $firstName     = $user->first_name ?? $user->name ?? 'there';
                    $safeTitle     = htmlspecialchars($goalTitle, ENT_QUOTES, 'UTF-8');
                    $newGoalUrl    = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/goals';
                    $community     = TenantContext::getName();

                    $html = EmailTemplateBuilder::make()
                        ->theme('info')
                        ->title(__('emails_goals.abandoned.title'))
                        ->previewText(__('emails_goals.abandoned.preview'))
                        ->greeting($firstName)
                        ->paragraph(__('emails_goals.abandoned.body', ['title' => $safeTitle, 'community' => $community]))
                        ->paragraph(__('emails_goals.abandoned.note'))
                        ->button(__('emails_goals.abandoned.cta'), $newGoalUrl)
                        ->render();

                    Mailer::forCurrentTenant()->send(
                        $user->email,
                        __('emails_goals.abandoned.subject', ['title' => $safeTitle, 'community' => $community]),
                        $html
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[GoalService] abandoned email failed: ' . $e->getMessage());
            }
        }

        return $deleted;
    }

    /**
     * Increment goal progress.
     */
    public function incrementProgress(int $id, int $userId, float $increment): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $current = (float) ($goal->current_value ?? 0);
        $target  = (float) ($goal->target_value ?? 0);

        $oldPercent = $target > 0 ? min(100.0, ($current / $target) * 100) : 0.0;

        $goal->current_value = $current + $increment;

        if ($target > 0 && $goal->current_value >= $target) {
            $goal->status = 'completed';
        }

        $goal->save();

        $newPercent = $target > 0 ? min(100.0, ((float) $goal->current_value / $target) * 100) : 0.0;

        // Fire milestone emails (25 / 50 / 75 / 100%) — silenced to avoid disrupting the response
        try {
            GoalMilestoneEmailService::checkAndSendMilestone(
                TenantContext::getId(),
                $userId,
                $id,
                (string) ($goal->title ?? ''),
                $oldPercent,
                $newPercent
            );
        } catch (\Throwable $e) {
            Log::warning('[GoalService] milestone email failed', ['goal' => $id, 'error' => $e->getMessage()]);
        }

        return $goal->fresh(['user']);
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(int $id, int $userId): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $target = (float) ($goal->target_value ?? 1);
        $goal->current_value = $target;
        $goal->status = 'completed';
        $goal->save();

        // Send goal-completed email
        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->select(['email', 'name', 'first_name'])
                ->first();

            if ($user && !empty($user->email)) {
                $firstName = $user->first_name ?? $user->name ?? 'there';
                $goalTitle = htmlspecialchars($goal->title ?? '', ENT_QUOTES, 'UTF-8');
                $goalUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/goals/' . $goal->id;
                $community = TenantContext::getName();

                $html = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails_goals.completed.title'))
                    ->previewText(__('emails_goals.completed.preview', ['title' => $goalTitle]))
                    ->greeting($firstName)
                    ->paragraph(__('emails_goals.completed.body', ['community' => $community]))
                    ->highlight(__('emails_goals.completed.highlight'))
                    ->button(__('emails_goals.completed.cta'), $goalUrl)
                    ->render();

                Mailer::forCurrentTenant()->send(
                    $user->email,
                    __('emails_goals.completed.subject', ['title' => $goalTitle, 'community' => $community]),
                    $html
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[GoalService] completed email failed: ' . $e->getMessage());
        }

        return $goal;
    }

    /**
     * Offer to be buddy for a goal.
     */
    public function offerBuddy(int $goalId, int $userId): ?Goal
    {
        $goal = $this->goal->newQuery()->find($goalId);

        if (! $goal || ! $goal->is_public || $goal->mentor_id !== null) {
            return null;
        }

        if ((int) $goal->user_id === $userId) {
            return null;
        }

        $goal->mentor_id = $userId;
        $goal->save();

        return $goal->fresh(['user', 'mentor']);
    }
}
