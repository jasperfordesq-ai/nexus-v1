<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SkillTaxonomyService - Hierarchical skill categories and user skills
 *
 * Provides:
 * - Skill category CRUD (hierarchical tree)
 * - User skill assignment with proficiency levels
 * - Autocomplete/search for skills
 * - Taxonomy browsing (tree structure)
 */
class SkillTaxonomyService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    // =========================================================================
    // CATEGORY MANAGEMENT
    // =========================================================================

    /**
     * Get the full taxonomy tree for a tenant
     *
     * @param bool $activeOnly Only include active categories
     * @return array Nested tree structure
     */
    public static function getTree(bool $activeOnly = true): array
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM skill_categories WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY display_order ASC, name ASC";

        $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        return self::buildTree($rows);
    }

    /**
     * Get flat list of categories (for dropdowns/autocomplete)
     */
    public static function getCategories(?int $parentId = null): array
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT id, name, slug, parent_id, icon, description
                FROM skill_categories
                WHERE tenant_id = ? AND is_active = 1";
        $params = [$tenantId];

        if ($parentId !== null) {
            $sql .= " AND parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY display_order ASC, name ASC";

        return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single category by ID
     */
    public static function getCategoryById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT * FROM skill_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Create a new skill category
     */
    public static function createCategory(array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $name = trim($data['name'] ?? '');
        if (empty($name) || strlen($name) > 100) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name is required (max 100 chars)', 'field' => 'name'];
            return null;
        }

        $slug = self::generateSlug($name);
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

        // Verify parent exists if provided
        if ($parentId !== null) {
            $parent = Database::query(
                "SELECT id FROM skill_categories WHERE id = ? AND tenant_id = ?",
                [$parentId, $tenantId]
            )->fetch();
            if (!$parent) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Parent category not found', 'field' => 'parent_id'];
                return null;
            }
        }

        // Check for duplicate slug at same level
        $existing = Database::query(
            "SELECT id FROM skill_categories WHERE tenant_id = ? AND slug = ? AND (parent_id IS NULL AND ? IS NULL OR parent_id = ?)",
            [$tenantId, $slug, $parentId, $parentId]
        )->fetch();

        if ($existing) {
            $slug .= '-' . time();
        }

        Database::query(
            "INSERT INTO skill_categories (tenant_id, name, slug, parent_id, description, icon, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $tenantId,
                $name,
                $slug,
                $parentId,
                trim($data['description'] ?? ''),
                $data['icon'] ?? null,
                (int)($data['display_order'] ?? 0),
            ]
        );

        return (int)Database::lastInsertId();
    }

    /**
     * Update a category
     */
    public static function updateCategory(int $id, array $data): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $existing = Database::query(
            "SELECT id FROM skill_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Category not found'];
            return false;
        }

        $sets = [];
        $params = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name) || strlen($name) > 100) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Name is required (max 100 chars)', 'field' => 'name'];
                return false;
            }
            $sets[] = "name = ?";
            $params[] = $name;
            $sets[] = "slug = ?";
            $params[] = self::generateSlug($name);
        }

        if (array_key_exists('parent_id', $data)) {
            $sets[] = "parent_id = ?";
            $params[] = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        }

        if (isset($data['description'])) {
            $sets[] = "description = ?";
            $params[] = trim($data['description']);
        }

        if (isset($data['icon'])) {
            $sets[] = "icon = ?";
            $params[] = $data['icon'];
        }

        if (isset($data['display_order'])) {
            $sets[] = "display_order = ?";
            $params[] = (int)$data['display_order'];
        }

        if (isset($data['is_active'])) {
            $sets[] = "is_active = ?";
            $params[] = (int)(bool)$data['is_active'];
        }

        if (empty($sets)) {
            return true; // Nothing to update
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE skill_categories SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete a category (soft: deactivate, or hard: remove if no skills assigned)
     */
    public static function deleteCategory(int $id, bool $hard = false): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if ($hard) {
            // Check if any skills reference this category
            $count = Database::query(
                "SELECT COUNT(*) as cnt FROM user_skills WHERE category_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            if ((int)($count['cnt'] ?? 0) > 0) {
                self::$errors[] = ['code' => 'HAS_DEPENDENCIES', 'message' => 'Category has assigned skills; deactivate instead'];
                return false;
            }

            // Move child categories to parent (or null)
            $cat = Database::query(
                "SELECT parent_id FROM skill_categories WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            Database::query(
                "UPDATE skill_categories SET parent_id = ? WHERE parent_id = ? AND tenant_id = ?",
                [$cat['parent_id'] ?? null, $id, $tenantId]
            );

            Database::query(
                "DELETE FROM skill_categories WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
        } else {
            Database::query(
                "UPDATE skill_categories SET is_active = 0 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
        }

        return true;
    }

    // =========================================================================
    // USER SKILLS
    // =========================================================================

    /**
     * Get skills for a user
     */
    public static function getUserSkills(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT us.*, sc.name as category_name, sc.slug as category_slug,
                    (SELECT COUNT(*) FROM skill_endorsements se WHERE se.endorsed_id = us.user_id AND se.skill_name = us.skill_name AND se.tenant_id = us.tenant_id) as endorsement_count
             FROM user_skills us
             LEFT JOIN skill_categories sc ON us.category_id = sc.id
             WHERE us.user_id = ? AND us.tenant_id = ?
             ORDER BY us.skill_name ASC",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add a skill to a user
     */
    public static function addUserSkill(int $userId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $skillName = trim($data['skill_name'] ?? '');
        if (empty($skillName) || strlen($skillName) > 100) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Skill name is required (max 100 chars)', 'field' => 'skill_name'];
            return null;
        }

        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $proficiency = $data['proficiency'] ?? 'intermediate';
        $validProficiencies = ['beginner', 'intermediate', 'advanced', 'expert'];
        if (!in_array($proficiency, $validProficiencies)) {
            $proficiency = 'intermediate';
        }

        // Check for duplicate
        $existing = Database::query(
            "SELECT id FROM user_skills WHERE user_id = ? AND tenant_id = ? AND skill_name = ?",
            [$userId, $tenantId, $skillName]
        )->fetch();

        if ($existing) {
            self::$errors[] = ['code' => 'DUPLICATE', 'message' => 'You already have this skill', 'field' => 'skill_name'];
            return null;
        }

        Database::query(
            "INSERT INTO user_skills (user_id, tenant_id, category_id, skill_name, proficiency, is_offering, is_requesting)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $tenantId,
                $categoryId,
                $skillName,
                $proficiency,
                (int)(bool)($data['is_offering'] ?? true),
                (int)(bool)($data['is_requesting'] ?? false),
            ]
        );

        return (int)Database::lastInsertId();
    }

    /**
     * Update a user skill
     */
    public static function updateUserSkill(int $userId, int $skillId, array $data): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $existing = Database::query(
            "SELECT id FROM user_skills WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$skillId, $userId, $tenantId]
        )->fetch();

        if (!$existing) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Skill not found'];
            return false;
        }

        $sets = [];
        $params = [];

        if (isset($data['category_id'])) {
            $sets[] = "category_id = ?";
            $params[] = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        }

        if (isset($data['proficiency'])) {
            $valid = ['beginner', 'intermediate', 'advanced', 'expert'];
            if (in_array($data['proficiency'], $valid)) {
                $sets[] = "proficiency = ?";
                $params[] = $data['proficiency'];
            }
        }

        if (isset($data['is_offering'])) {
            $sets[] = "is_offering = ?";
            $params[] = (int)(bool)$data['is_offering'];
        }

        if (isset($data['is_requesting'])) {
            $sets[] = "is_requesting = ?";
            $params[] = (int)(bool)$data['is_requesting'];
        }

        if (empty($sets)) {
            return true;
        }

        $params[] = $skillId;
        $params[] = $userId;
        $params[] = $tenantId;

        Database::query(
            "UPDATE user_skills SET " . implode(', ', $sets) . " WHERE id = ? AND user_id = ? AND tenant_id = ?",
            $params
        );

        return true;
    }

    /**
     * Remove a user skill
     */
    public static function removeUserSkill(int $userId, int $skillId): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "DELETE FROM user_skills WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$skillId, $userId, $tenantId]
        );

        return true;
    }

    /**
     * Search/autocomplete skills across the tenant
     */
    public static function searchSkills(string $query, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();
        $searchTerm = '%' . $query . '%';

        // Search from existing user skills (most popular first)
        return Database::query(
            "SELECT skill_name, category_id, COUNT(*) as user_count
             FROM user_skills
             WHERE tenant_id = ? AND skill_name LIKE ?
             GROUP BY skill_name, category_id
             ORDER BY user_count DESC, skill_name ASC
             LIMIT ?",
            [$tenantId, $searchTerm, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a user's skills as a name→weight map based on proficiency level.
     *
     * Weights: beginner=0.6, intermediate=1.0, advanced=1.3, expert=1.6
     * Used by ranking/matching algorithms to give stronger signal to expert skills.
     *
     * @return array<string, float>  ['skill_name_lowercase' => weight]
     */
    public static function getProficiencyWeightedSkills(int $userId, int $tenantId): array
    {
        static $cache = [];
        $key = "{$tenantId}:{$userId}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $weights = ['beginner' => 0.6, 'intermediate' => 1.0, 'advanced' => 1.3, 'expert' => 1.6];

        try {
            $rows = Database::query(
                "SELECT skill_name, proficiency FROM user_skills
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $cache[$key] = [];
        }

        $result = [];
        foreach ($rows as $row) {
            $name   = strtolower(trim($row['skill_name']));
            $result[$name] = $weights[$row['proficiency']] ?? 1.0;
        }

        return $cache[$key] = $result;
    }

    /**
     * Get skills breakdown for a category (with user counts)
     */
    public static function getCategorySkills(int $categoryId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT skill_name, COUNT(*) as user_count,
                    SUM(is_offering) as offering_count,
                    SUM(is_requesting) as requesting_count
             FROM user_skills
             WHERE tenant_id = ? AND category_id = ?
             GROUP BY skill_name
             ORDER BY user_count DESC",
            [$tenantId, $categoryId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get members who have a specific skill (by name), with proficiency & flags.
     *
     * @return array<array{id:int, first_name:string, last_name:string, avatar:?string, proficiency:string, is_offering:int, is_requesting:int}>
     */
    public static function getMembersWithSkill(string $skillName, int $limit = 30): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.avatar,
                    us.proficiency AS proficiency_level,
                    us.is_offering, us.is_requesting
             FROM user_skills us
             JOIN users u ON u.id = us.user_id AND u.tenant_id = us.tenant_id
             WHERE us.tenant_id = ? AND us.skill_name = ? AND u.status = 'active'
             ORDER BY FIELD(us.proficiency, 'expert', 'advanced', 'intermediate', 'beginner'), u.first_name
             LIMIT ?",
            [$tenantId, $skillName, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // SEED / DEFAULTS
    // =========================================================================

    /**
     * Seed default skill categories for a new tenant
     */
    public static function seedDefaults(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $defaults = [
            ['Technology', 'technology', null, 'Tech & IT skills', 0],
            ['Home & Garden', 'home-garden', null, 'Home maintenance, gardening', 1],
            ['Education & Tutoring', 'education-tutoring', null, 'Teaching and mentoring', 2],
            ['Health & Wellbeing', 'health-wellbeing', null, 'Health, fitness, wellness', 3],
            ['Arts & Creative', 'arts-creative', null, 'Art, music, crafts', 4],
            ['Professional Services', 'professional-services', null, 'Admin, legal, finance', 5],
            ['Transport & Errands', 'transport-errands', null, 'Driving, shopping, deliveries', 6],
            ['Community & Social', 'community-social', null, 'Befriending, community work', 7],
        ];

        foreach ($defaults as [$name, $slug, $parentId, $desc, $order]) {
            $existing = Database::query(
                "SELECT id FROM skill_categories WHERE tenant_id = ? AND slug = ?",
                [$tenantId, $slug]
            )->fetch();

            if (!$existing) {
                Database::query(
                    "INSERT INTO skill_categories (tenant_id, name, slug, parent_id, description, display_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$tenantId, $name, $slug, $parentId, $desc, $order]
                );
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function buildTree(array $rows, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($rows as $row) {
            $rowParent = $row['parent_id'] ? (int)$row['parent_id'] : null;
            if ($rowParent === $parentId) {
                $row['children'] = self::buildTree($rows, (int)$row['id']);
                $tree[] = $row;
            }
        }
        return $tree;
    }

    private static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return substr($slug, 0, 120);
    }
}
