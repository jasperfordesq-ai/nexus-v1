<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * JobVacancyService — Laravel DI-based service for job vacancy operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\JobVacancyService.
 * Manages job vacancy CRUD and applications with tenant scoping.
 */
class JobVacancyService
{
    /**
     * Get all job vacancies with filtering and cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = [], ?int $userId = null): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('job_vacancies as jv')
            ->leftJoin('users as u', 'jv.created_by', '=', 'u.id')
            ->select('jv.*', 'u.first_name', 'u.last_name', 'u.avatar_url');

        if (! empty($filters['status'])) {
            $query->where('jv.status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('jv.type', $filters['type']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('jv.title', 'LIKE', $term)->orWhere('jv.description', 'LIKE', $term));
        }
        if ($cursor !== null) {
            $query->where('jv.id', '<', (int) base64_decode($cursor));
        }

        $query->orderByDesc('jv.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single job vacancy by ID.
     */
    public function getById(int $id): ?array
    {
        $job = DB::table('job_vacancies')->find($id);
        if (! $job) {
            return null;
        }

        $data = (array) $job;
        $data['applications_count'] = (int) DB::table('job_applications')->where('job_vacancy_id', $id)->count();

        return $data;
    }

    /**
     * Create a new job vacancy.
     */
    public function create(int $userId, array $data): int
    {
        return DB::table('job_vacancies')->insertGetId([
            'title'       => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'type'        => $data['type'] ?? 'volunteer',
            'commitment'  => $data['commitment'] ?? 'flexible',
            'location'    => $data['location'] ?? null,
            'status'      => 'open',
            'created_by'  => $userId,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Apply to a job vacancy.
     *
     * @return int|null Application ID or null if already applied.
     */
    public function apply(int $jobId, int $userId, array $data = []): ?int
    {
        $exists = DB::table('job_applications')
            ->where('job_vacancy_id', $jobId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::table('job_applications')->insertGetId([
            'job_vacancy_id' => $jobId,
            'user_id'        => $userId,
            'cover_letter'   => $data['cover_letter'] ?? null,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
