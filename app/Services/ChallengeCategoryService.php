<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\ChallengeCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ChallengeCategoryService — Eloquent-based CRUD for challenge categories.
 *
 * Categories are a per-tenant taxonomy used to classify ideation challenges.
 * All queries are tenant-scoped via HasTenantScope trait on the model.
 */
class ChallengeCategoryService
{
    /** @var array<int, array{code: string, message: string, field?: string}> */
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function clearErrors(): void
    {
        $this->errors = [];
    }

    private function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        $this->errors[] = $error;
    }

    /**
     * List all categories for the current tenant.
     */
    public function getAll(): array
    {
        return ChallengeCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();
    }

    /**
     * Get a single category by ID.
     */
    public function getById(int $id): ?array
    {
        $cat = ChallengeCategory::find($id);
        return $cat ? $cat->toArray() : null;
    }

    /**
     * Create a new category.
     *
     * @return int|null Category ID on success
     */
    public function create(int $userId, array $data): ?int
    {
        $this->clearErrors();

        if (!$this->isAdmin($userId)) {
            $this->addError('RESOURCE_FORBIDDEN', 'Only admins can manage categories');
            return null;
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $this->addError('VALIDATION_REQUIRED_FIELD', 'Name is required', 'name');
            return null;
        }

        $slug = $this->generateSlug($name);
        $icon = !empty($data['icon']) ? trim($data['icon']) : null;
        $color = !empty($data['color']) ? trim($data['color']) : null;
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        // Check for duplicate slug in this tenant
        $existing = ChallengeCategory::where('slug', $slug)->first();
        if ($existing) {
            $this->addError('RESOURCE_CONFLICT', 'A category with this name already exists', 'name');
            return null;
        }

        try {
            $category = ChallengeCategory::create([
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $sortOrder,
            ]);

            return (int) $category->id;
        } catch (\Throwable $e) {
            Log::error('Category creation failed: ' . $e->getMessage());
            $this->addError('SERVER_INTERNAL_ERROR', 'Failed to create category');
            return null;
        }
    }

    /**
     * Update a category.
     */
    public function update(int $id, int $userId, array $data): bool
    {
        $this->clearErrors();

        if (!$this->isAdmin($userId)) {
            $this->addError('RESOURCE_FORBIDDEN', 'Only admins can manage categories');
            return false;
        }

        $category = ChallengeCategory::find($id);
        if (!$category) {
            $this->addError('RESOURCE_NOT_FOUND', 'Category not found');
            return false;
        }

        $updates = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                $this->addError('VALIDATION_REQUIRED_FIELD', 'Name cannot be empty', 'name');
                return false;
            }
            $slug = $this->generateSlug($name);

            $existing = ChallengeCategory::where('slug', $slug)
                ->where('id', '!=', $id)
                ->first();
            if ($existing) {
                $this->addError('RESOURCE_CONFLICT', 'A category with this name already exists', 'name');
                return false;
            }

            $updates['name'] = $name;
            $updates['slug'] = $slug;
        }

        if (array_key_exists('icon', $data)) {
            $updates['icon'] = !empty($data['icon']) ? trim($data['icon']) : null;
        }

        if (array_key_exists('color', $data)) {
            $updates['color'] = !empty($data['color']) ? trim($data['color']) : null;
        }

        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        if (empty($updates)) {
            return true;
        }

        try {
            $category->update($updates);
            return true;
        } catch (\Throwable $e) {
            Log::error('Category update failed: ' . $e->getMessage());
            $this->addError('SERVER_INTERNAL_ERROR', 'Failed to update category');
            return false;
        }
    }

    /**
     * Delete a category.
     */
    public function delete(int $id, int $userId): bool
    {
        $this->clearErrors();

        if (!$this->isAdmin($userId)) {
            $this->addError('RESOURCE_FORBIDDEN', 'Only admins can manage categories');
            return false;
        }

        $category = ChallengeCategory::find($id);
        if (!$category) {
            $this->addError('RESOURCE_NOT_FOUND', 'Category not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // Null out the category_id on challenges that use this category
            DB::table('ideation_challenges')
                ->where('category_id', $id)
                ->where('tenant_id', $tenantId)
                ->update(['category_id' => null]);

            $category->delete();

            return true;
        } catch (\Throwable $e) {
            Log::error('Category deletion failed: ' . $e->getMessage());
            $this->addError('SERVER_INTERNAL_ERROR', 'Failed to delete category');
            return false;
        }
    }

    /**
     * Generate a URL-safe slug from a name.
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Check if user has admin role.
     */
    private function isAdmin(int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['role']);

        return $user && in_array($user->role ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
