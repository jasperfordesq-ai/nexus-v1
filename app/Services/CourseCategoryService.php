<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\CourseCategory;
use Illuminate\Support\Str;

/**
 * CourseCategoryService — tenant-scoped course category CRUD.
 */
class CourseCategoryService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        return CourseCategory::orderBy('position')->orderBy('name')->get()->toArray();
    }

    public static function create(array $data): CourseCategory
    {
        $name = trim((string) ($data['name'] ?? ''));

        return CourseCategory::create([
            'name' => $name,
            'slug' => self::uniqueSlug($data['slug'] ?? $name),
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'position' => (int) ($data['position'] ?? 0),
        ]);
    }

    public static function update(int $id, array $data): ?CourseCategory
    {
        $category = CourseCategory::find($id);
        if (!$category) {
            return null;
        }

        $category->fill(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'position' => isset($data['position']) ? (int) $data['position'] : null,
        ], static fn ($v) => $v !== null));

        $category->save();

        return $category;
    }

    public static function delete(int $id): bool
    {
        $category = CourseCategory::find($id);
        if (!$category) {
            return false;
        }

        // Detach courses from the deleted category rather than cascading.
        \App\Models\Course::where('category_id', $id)->update(['category_id' => null]);

        return (bool) $category->delete();
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'category';
        $candidate = $slug;
        $i = 2;

        while (CourseCategory::where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
