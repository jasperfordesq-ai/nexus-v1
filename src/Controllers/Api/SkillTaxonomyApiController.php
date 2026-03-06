<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\SkillTaxonomyService;

/**
 * SkillTaxonomyApiController - API for skill taxonomy & user skills
 *
 * Endpoints:
 * - GET    /api/v2/skills/categories          - Get taxonomy tree
 * - GET    /api/v2/skills/categories/{id}     - Get category details
 * - POST   /api/v2/skills/categories          - Create category (admin)
 * - PUT    /api/v2/skills/categories/{id}     - Update category (admin)
 * - DELETE /api/v2/skills/categories/{id}     - Delete category (admin)
 * - GET    /api/v2/skills/search              - Autocomplete skill search
 * - GET    /api/v2/users/me/skills            - Get own skills
 * - GET    /api/v2/users/{id}/skills          - Get user's skills
 * - POST   /api/v2/users/me/skills            - Add skill
 * - PUT    /api/v2/users/me/skills/{id}       - Update skill
 * - DELETE /api/v2/users/me/skills/{id}       - Remove skill
 */
class SkillTaxonomyApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // =============================================
    // CATEGORY ENDPOINTS
    // =============================================

    /**
     * GET /api/v2/skills/categories
     */
    public function getCategories(): void
    {
        $this->rateLimit('skills_categories', 30, 60);

        $parentId = $this->query('parent_id');
        $format = $this->query('format', 'tree');

        if ($format === 'flat') {
            $categories = SkillTaxonomyService::getCategories(
                $parentId !== null ? (int)$parentId : null
            );
            $this->respondWithData($categories);
        }

        $tree = SkillTaxonomyService::getTree();
        $this->respondWithData($tree);
    }

    /**
     * GET /api/v2/skills/categories/{id}
     */
    public function getCategoryById(int $id): void
    {
        $category = SkillTaxonomyService::getCategoryById($id);

        if (!$category) {
            $this->respondWithError('NOT_FOUND', 'Category not found', null, 404);
        }

        // Include skills in this category
        $category['skills'] = SkillTaxonomyService::getCategorySkills($id);

        $this->respondWithData($category);
    }

    /**
     * POST /api/v2/skills/categories (admin)
     */
    public function createCategory(): void
    {
        $this->getUserId(); // Must be authenticated
        $this->verifyCsrf();
        $this->rateLimit('skills_category_create', 10, 60);

        $data = $this->getAllInput();
        $id = SkillTaxonomyService::createCategory($data);

        if ($id === null) {
            $this->respondWithErrors(SkillTaxonomyService::getErrors(), 422);
        }

        $category = SkillTaxonomyService::getCategoryById($id);
        $this->respondWithData($category, null, 201);
    }

    /**
     * PUT /api/v2/skills/categories/{id} (admin)
     */
    public function updateCategory(int $id): void
    {
        $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('skills_category_update', 10, 60);

        $data = $this->getAllInput();
        $success = SkillTaxonomyService::updateCategory($id, $data);

        if (!$success) {
            $this->respondWithErrors(SkillTaxonomyService::getErrors(), 422);
        }

        $category = SkillTaxonomyService::getCategoryById($id);
        $this->respondWithData($category);
    }

    /**
     * DELETE /api/v2/skills/categories/{id} (admin)
     */
    public function deleteCategory(int $id): void
    {
        $this->getUserId();
        $this->verifyCsrf();

        $hard = $this->queryBool('hard', false);
        $success = SkillTaxonomyService::deleteCategory($id, $hard);

        if (!$success) {
            $this->respondWithErrors(SkillTaxonomyService::getErrors(), 422);
        }

        $this->respondWithData(['message' => 'Category deleted']);
    }

    // =============================================
    // SKILL SEARCH
    // =============================================

    /**
     * GET /api/v2/skills/search
     */
    public function search(): void
    {
        $this->rateLimit('skills_search', 60, 60);

        $query = $this->query('q', '');
        if (strlen($query) < 1) {
            $this->respondWithData([]);
        }

        $limit = $this->queryInt('limit', 20, 1, 50);
        $results = SkillTaxonomyService::searchSkills($query, $limit);

        $this->respondWithData($results);
    }

    /**
     * GET /api/v2/skills/members?skill=SkillName
     */
    public function getMembersWithSkill(): void
    {
        $this->rateLimit('skills_members', 30, 60);

        $skillName = $this->query('skill', '');
        if (strlen($skillName) < 1) {
            $this->respondWithData([]);
        }

        $limit = $this->queryInt('limit', 30, 1, 50);
        $members = SkillTaxonomyService::getMembersWithSkill($skillName, $limit);

        $this->respondWithData($members);
    }

    // =============================================
    // USER SKILL ENDPOINTS
    // =============================================

    /**
     * GET /api/v2/users/me/skills
     */
    public function getMySkills(): void
    {
        $userId = $this->getUserId();
        $skills = SkillTaxonomyService::getUserSkills($userId);
        $this->respondWithData($skills);
    }

    /**
     * GET /api/v2/users/{id}/skills
     */
    public function getUserSkills(int $id): void
    {
        $this->rateLimit('user_skills_view', 30, 60);
        $skills = SkillTaxonomyService::getUserSkills($id);
        $this->respondWithData($skills);
    }

    /**
     * POST /api/v2/users/me/skills
     */
    public function addSkill(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('skills_add', 20, 60);

        $data = $this->getAllInput();
        $skillId = SkillTaxonomyService::addUserSkill($userId, $data);

        if ($skillId === null) {
            $this->respondWithErrors(SkillTaxonomyService::getErrors(), 422);
        }

        $skills = SkillTaxonomyService::getUserSkills($userId);
        $this->respondWithData($skills, null, 201);
    }

    /**
     * PUT /api/v2/users/me/skills/{id}
     */
    public function updateSkill(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('skills_update', 20, 60);

        $data = $this->getAllInput();
        $success = SkillTaxonomyService::updateUserSkill($userId, $id, $data);

        if (!$success) {
            $this->respondWithErrors(SkillTaxonomyService::getErrors(), 422);
        }

        $skills = SkillTaxonomyService::getUserSkills($userId);
        $this->respondWithData($skills);
    }

    /**
     * DELETE /api/v2/users/me/skills/{id}
     */
    public function removeSkill(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        SkillTaxonomyService::removeUserSkill($userId, $id);
        $this->respondWithData(['message' => 'Skill removed']);
    }
}
