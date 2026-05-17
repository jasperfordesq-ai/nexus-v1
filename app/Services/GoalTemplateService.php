<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalTemplate;

/**
 * GoalTemplateService — Eloquent-based service for goal templates.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class GoalTemplateService
{
    public function __construct(
        private readonly GoalTemplate $template,
    ) {}

    /**
     * List templates with cursor pagination.
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->template->newQuery()->where('is_public', true);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
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
     * Get available template categories.
     */
    public function getCategories(): array
    {
        return $this->template->newQuery()
            ->where('is_public', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get a template by ID.
     */
    public function getById(int $id): ?GoalTemplate
    {
        return $this->template->newQuery()->find($id);
    }

    /**
     * Create a template (admin only).
     */
    public function create(int $userId, array $data): GoalTemplate
    {
        $defaultTargetValue = isset($data['default_target_value'])
            ? max(1, (float) $data['default_target_value'])
            : 100.0;

        $template = $this->template->newInstance([
            'created_by'           => $userId,
            'title'                => trim($data['title']),
            'description'          => trim($data['description'] ?? ''),
            'category'             => $data['category'] ?? null,
            'default_target_value' => $defaultTargetValue,
            'default_milestones'   => $data['default_milestones'] ?? null,
            'is_public'            => $data['is_public'] ?? true,
        ]);

        $template->save();

        return $template;
    }

    /**
     * Create a goal from a template.
     */
    public function createGoalFromTemplate(int $templateId, int $userId, array $overrides = []): ?Goal
    {
        $template = $this->template->newQuery()->find($templateId);

        if (! $template) {
            return null;
        }

        $goal = Goal::create([
            'user_id'           => $userId,
            'title'             => trim($overrides['title'] ?? $template->title),
            'description'       => trim($overrides['description'] ?? $template->description ?? ''),
            'target_value'      => max(1, (float) ($overrides['target_value'] ?? $template->default_target_value ?? 100)),
            'current_value'     => 0,
            'deadline'          => $overrides['deadline'] ?? null,
            'is_public'         => $overrides['is_public'] ?? true,
            'checkin_frequency' => $overrides['checkin_frequency'] ?? 'weekly',
            'template_id'       => $template->id,
            'status'            => 'active',
        ]);
        app(GoalProgressService::class)->seedDefaultMilestones($goal, $template->default_milestones);
        app(GoalProgressService::class)->recordHistory($goal, 'created', __('api_controllers_3.goals.history_created'), [
            'target_value' => (float) $goal->target_value,
            'template_id' => $template->id,
        ]);

        return $goal->fresh(['user']);
    }
}
