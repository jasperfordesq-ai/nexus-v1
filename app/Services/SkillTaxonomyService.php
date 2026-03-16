<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * SkillTaxonomyService — Laravel DI-based service for skill taxonomy.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\SkillTaxonomyService.
 * Manages hierarchical skill categories and user skill assignments.
 */
class SkillTaxonomyService
{
    /**
     * Get skill categories, optionally filtered by parent.
     */
    public function getCategories(?int $parentId = null): array
    {
        $query = DB::table('skill_categories')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name');

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        }

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * Search skills by name (autocomplete).
     */
    public function search(string $term, int $limit = 20): array
    {
        return DB::table('skill_categories')
            ->where('is_active', true)
            ->where('name', 'LIKE', '%' . $term . '%')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Get skills assigned to a user.
     */
    public function getMySkills(int $userId): array
    {
        return DB::table('user_skills as us')
            ->leftJoin('skill_categories as sc', 'us.skill_id', '=', 'sc.id')
            ->where('us.user_id', $userId)
            ->select('us.*', 'sc.name as category_name', 'sc.icon')
            ->orderByDesc('us.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Add a skill to a user's profile.
     *
     * @return int|null Skill assignment ID or null if already assigned.
     */
    public function addSkill(int $userId, string $skillName, ?int $skillId = null, string $proficiency = 'intermediate'): ?int
    {
        $exists = DB::table('user_skills')
            ->where('user_id', $userId)
            ->where('skill_name', $skillName)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::table('user_skills')->insertGetId([
            'user_id'     => $userId,
            'skill_id'    => $skillId,
            'skill_name'  => trim($skillName),
            'proficiency' => $proficiency,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Remove a skill from a user's profile.
     */
    public function removeSkill(int $userId, int $userSkillId): bool
    {
        return DB::table('user_skills')
            ->where('id', $userSkillId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }
}
