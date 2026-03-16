<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * SkillTaxonomyController -- Skill taxonomy and user skills.
 *
 * Delegates to legacy: SkillTaxonomyApiController
 */
class SkillTaxonomyController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET skills/categories */
    public function getCategories(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->getCategories();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET skills/search */
    public function search(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->search();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET skills/members */
    public function getMembersWithSkill(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->getMembersWithSkill();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** GET users/me/skills */
    public function getMySkills(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->getMySkills();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** POST users/me/skills */
    public function addSkill(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->addSkill();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** DELETE users/me/skills/id */
    public function removeSkill(int $id): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\SkillTaxonomyApiController();
            $controller->removeSkill($id);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function getCategoryById($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'getCategoryById', [$id]);
    }


    public function createCategory(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'createCategory');
    }


    public function updateCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'updateCategory', [$id]);
    }


    public function deleteCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'deleteCategory', [$id]);
    }


    public function updateSkill($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'updateSkill', [$id]);
    }


    public function getUserSkills($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SkillTaxonomyApiController::class, 'getUserSkills', [$id]);
    }

}
