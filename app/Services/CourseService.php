<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Course;
use Illuminate\Support\Str;

/**
 * CourseService — tenant-scoped course CRUD, listing, and search.
 * Tenant scoping is enforced automatically by the HasTenantScope trait on the
 * Course model; this service layers visibility/status filtering on top.
 */
class CourseService
{
    /**
     * Browse published courses with optional filters and offset pagination.
     *
     * @param array{search?:string,category_id?:int,level?:string,page?:int,per_page?:int,include_member_only?:bool} $filters
     * @return array{items:array,total:int,page:int,per_page:int}
     */
    public static function browse(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 12)));

        $query = Course::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name,avatar_url']);

        // Members-only courses are visible in the catalogue to logged-in members;
        // public-only browsing (e.g. anonymous) restricts to visibility=public.
        if (empty($filters['include_member_only'])) {
            $query->where('visibility', 'public');
        } else {
            $query->whereIn('visibility', ['public', 'members']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('summary', 'like', "%{$term}%");
            });
        }

        $total = (clone $query)->count();

        $items = $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Courses authored by a given user (any status) — for the instructor dashboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function authoredBy(int $userId): array
    {
        return Course::where('author_user_id', $userId)
            ->with('category:id,name,slug')
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();
    }

    public static function findById(int $id): ?Course
    {
        return Course::with(['category', 'author:id,name,avatar_url', 'sections.lessons'])->find($id);
    }

    public static function findBySlug(string $slug): ?Course
    {
        return Course::where('slug', $slug)
            ->with(['category', 'author:id,name,avatar_url', 'sections.lessons'])
            ->first();
    }

    public static function create(int $authorUserId, array $data): Course
    {
        $title = trim((string) ($data['title'] ?? ''));

        return Course::create([
            'author_user_id' => $authorUserId,
            'category_id' => $data['category_id'] ?? null,
            'title' => $title,
            'slug' => self::uniqueSlug($data['slug'] ?? $title),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'level' => $data['level'] ?? 'beginner',
            'visibility' => $data['visibility'] ?? 'members',
            'enrollment_type' => $data['enrollment_type'] ?? 'self_paced',
            'status' => 'draft',
            'moderation_status' => 'pending',
            'credit_cost' => $data['credit_cost'] ?? 0,
            'learner_credit_reward' => $data['learner_credit_reward'] ?? 0,
            'instructor_credit_reward' => $data['instructor_credit_reward'] ?? 0,
            'prerequisites' => $data['prerequisites'] ?? null,
        ]);
    }

    public static function update(Course $course, array $data): Course
    {
        $fields = [
            'category_id', 'title', 'summary', 'description', 'cover_image',
            'level', 'visibility', 'enrollment_type', 'credit_cost',
            'learner_credit_reward', 'instructor_credit_reward', 'prerequisites',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $course->{$field} = $data[$field];
            }
        }

        $course->save();

        return $course;
    }

    /**
     * Publish a course. Moderation gate: tenants with moderation enabled keep
     * the course pending until an admin approves; otherwise auto-approve.
     */
    public static function publish(Course $course, bool $autoApprove = true): Course
    {
        $course->status = 'published';
        if ($autoApprove) {
            $course->moderation_status = 'approved';
        }
        if (!$course->published_at) {
            $course->published_at = now();
        }
        $course->save();

        return $course;
    }

    public static function unpublish(Course $course): Course
    {
        $course->status = 'draft';
        $course->save();

        return $course;
    }

    public static function delete(Course $course): bool
    {
        return (bool) $course->delete();
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'course';
        $candidate = $slug;
        $i = 2;

        // withoutGlobalScope not needed — uniqueness is per-tenant, which is the
        // desired behaviour (slugs only need to be unique within a tenant).
        while (Course::where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
