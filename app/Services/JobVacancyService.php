<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
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

        $tenantId = TenantContext::getId();

        $query = DB::table('job_vacancies as jv')
            ->leftJoin('users as u', 'jv.user_id', '=', 'u.id')
            ->where('jv.tenant_id', $tenantId)
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
        $job = DB::table('job_vacancies')->where('tenant_id', TenantContext::getId())->where('id', $id)->first();
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
            'tenant_id'   => TenantContext::getId(),
            'title'       => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'type'        => $data['type'] ?? 'volunteer',
            'commitment'  => $data['commitment'] ?? 'flexible',
            'location'    => $data['location'] ?? null,
            'status'      => 'open',
            'user_id'     => $userId,
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

    /**
     * Delegates to legacy JobVacancyService::delete().
     */
    public function delete(int $id, int $adminId): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) {
            return (bool) DB::table('job_vacancies')->where('tenant_id', TenantContext::getId())->where('id', $id)->delete();
        }
        return \Nexus\Services\JobVacancyService::delete($id, $adminId);
    }

    /**
     * Delegates to legacy JobVacancyService::featureJob().
     */
    public function featureJob(int $id, int $adminId, int $days = 7): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::featureJob($id, $adminId, $days);
    }

    /**
     * Delegates to legacy JobVacancyService::unfeatureJob().
     */
    public function unfeatureJob(int $id, int $adminId): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::unfeatureJob($id, $adminId);
    }

    /**
     * Delegates to legacy JobVacancyService::getApplications().
     */
    public function getApplications(int $jobId, int $adminId): ?array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return null; }
        return \Nexus\Services\JobVacancyService::getApplications($jobId, $adminId);
    }

    /**
     * Delegates to legacy JobVacancyService::updateApplicationStatus().
     */
    public function updateApplicationStatus(int $applicationId, int $adminId, string $status, ?string $notes = null): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::updateApplicationStatus($applicationId, $adminId, $status, $notes);
    }

    /**
     * Delegates to legacy JobVacancyService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::getErrors();
    }

    // =========================================================================
    // Legacy delegation methods — used by JobVacanciesController
    // =========================================================================

    /**
     * Delegates to legacy JobVacancyService::getById() with optional userId.
     */
    public function legacyGetById(int $id, ?int $userId = null): ?array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) {
            return $this->getById($id);
        }
        return \Nexus\Services\JobVacancyService::getById($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::incrementViews().
     */
    public function incrementViews(int $id, ?int $userId = null): void
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return; }
        \Nexus\Services\JobVacancyService::incrementViews($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::update().
     */
    public function update(int $id, int $userId, array $data): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::update($id, $userId, $data);
    }

    /**
     * Delegates to legacy JobVacancyService::apply() with message string.
     */
    public function legacyApply(int $jobId, int $userId, ?string $message = null): ?int
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) {
            return $this->apply($jobId, $userId, ['cover_letter' => $message]);
        }
        return \Nexus\Services\JobVacancyService::apply($jobId, $userId, $message);
    }

    /**
     * Delegates to legacy JobVacancyService::getSavedJobs().
     */
    public function getSavedJobs(int $userId, array $filters = []): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::getSavedJobs($userId, $filters);
    }

    /**
     * Delegates to legacy JobVacancyService::saveJob().
     */
    public function saveJob(int $id, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::saveJob($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::unsaveJob().
     */
    public function unsaveJob(int $id, int $userId): void
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return; }
        \Nexus\Services\JobVacancyService::unsaveJob($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::getMyApplications().
     */
    public function getMyApplications(int $userId, array $filters = []): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::getMyApplications($userId, $filters);
    }

    /**
     * Delegates to legacy JobVacancyService::getMyPostings().
     */
    public function getMyPostings(int $userId, int $tenantId, array $params = []): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::getMyPostings($userId, $tenantId, $params);
    }

    /**
     * Delegates to legacy JobVacancyService::getAlerts().
     */
    public function getAlerts(int $userId): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::getAlerts($userId);
    }

    /**
     * Delegates to legacy JobVacancyService::subscribeAlert().
     */
    public function subscribeAlert(int $userId, array $data): ?int
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return null; }
        return \Nexus\Services\JobVacancyService::subscribeAlert($userId, $data);
    }

    /**
     * Delegates to legacy JobVacancyService::deleteAlert().
     */
    public function deleteAlert(int $id, int $userId): void
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return; }
        \Nexus\Services\JobVacancyService::deleteAlert($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::unsubscribeAlert().
     */
    public function unsubscribeAlert(int $id, int $userId): void
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return; }
        \Nexus\Services\JobVacancyService::unsubscribeAlert($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::resubscribeAlert().
     */
    public function resubscribeAlert(int $id, int $userId): void
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return; }
        \Nexus\Services\JobVacancyService::resubscribeAlert($id, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::calculateMatchPercentage().
     */
    public function calculateMatchPercentage(int $userId, int $jobId): array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return []; }
        return \Nexus\Services\JobVacancyService::calculateMatchPercentage($userId, $jobId);
    }

    /**
     * Delegates to legacy JobVacancyService::getQualificationAssessment().
     */
    public function getQualificationAssessment(int $userId, int $jobId): ?array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return null; }
        return \Nexus\Services\JobVacancyService::getQualificationAssessment($userId, $jobId);
    }

    /**
     * Delegates to legacy JobVacancyService::getApplicationHistory().
     */
    public function getApplicationHistory(int $applicationId, int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return null; }
        return \Nexus\Services\JobVacancyService::getApplicationHistory($applicationId, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::getAnalytics().
     */
    public function getAnalytics(int $jobId, int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return null; }
        return \Nexus\Services\JobVacancyService::getAnalytics($jobId, $userId);
    }

    /**
     * Delegates to legacy JobVacancyService::renewJob().
     */
    public function renewJob(int $id, int $userId, int $days = 30): bool
    {
        if (!class_exists('\Nexus\Services\JobVacancyService')) { return false; }
        return \Nexus\Services\JobVacancyService::renewJob($id, $userId, $days);
    }
}
