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

    /**
     * Delegates to legacy SkillTaxonomyService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::getErrors();
    }

    /**
     * Delegates to legacy SkillTaxonomyService::getTree().
     */
    public function getTree(bool $activeOnly = true): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::getTree($activeOnly);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::getCategoryById().
     */
    public function getCategoryById(int $id): ?array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return null; }
        return \Nexus\Services\SkillTaxonomyService::getCategoryById($id);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::createCategory().
     */
    public function createCategory(array $data): ?int
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return null; }
        return \Nexus\Services\SkillTaxonomyService::createCategory($data);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::updateCategory().
     */
    public function updateCategory(int $id, array $data): bool
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return false; }
        return \Nexus\Services\SkillTaxonomyService::updateCategory($id, $data);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::deleteCategory().
     */
    public function deleteCategory(int $id, bool $hard = false): bool
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return false; }
        return \Nexus\Services\SkillTaxonomyService::deleteCategory($id, $hard);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::getUserSkills().
     */
    public function getUserSkills(int $userId): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::getUserSkills($userId);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::addUserSkill().
     */
    public function addUserSkill(int $userId, array $data): ?int
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return null; }
        return \Nexus\Services\SkillTaxonomyService::addUserSkill($userId, $data);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::updateUserSkill().
     */
    public function updateUserSkill(int $userId, int $skillId, array $data): bool
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return false; }
        return \Nexus\Services\SkillTaxonomyService::updateUserSkill($userId, $skillId, $data);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::removeUserSkill().
     */
    public function removeUserSkill(int $userId, int $skillId): bool
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return false; }
        return \Nexus\Services\SkillTaxonomyService::removeUserSkill($userId, $skillId);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::searchSkills().
     */
    public function searchSkills(string $query, int $limit = 20): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::searchSkills($query, $limit);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::getCategorySkills().
     */
    public function getCategorySkills(int $categoryId): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::getCategorySkills($categoryId);
    }

    /**
     * Delegates to legacy SkillTaxonomyService::getMembersWithSkill().
     */
    public function getMembersWithSkill(string $skillName, int $limit = 30): array
    {
        if (!class_exists('\Nexus\Services\SkillTaxonomyService')) { return []; }
        return \Nexus\Services\SkillTaxonomyService::getMembersWithSkill($skillName, $limit);
    }
}
