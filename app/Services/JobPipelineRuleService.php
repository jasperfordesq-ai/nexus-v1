<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\JobApplication;
use App\Models\JobPipelineRule;
use App\Models\JobVacancy;
use App\Models\JobVacancyTeam;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class JobPipelineRuleService
{
    private static function canManageVacancy(object $vacancy, int $userId, int $tenantId): bool
    {
        if ((int) $vacancy->user_id === $userId) {
            return true;
        }

        $user = User::where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'role', 'is_admin', 'is_super_admin', 'is_tenant_super_admin', 'is_god']);

        if ($user) {
            $role = (string) ($user->role ?? '');
            if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
                || (bool) ($user->is_admin ?? false)
                || (bool) ($user->is_super_admin ?? false)
                || (bool) ($user->is_tenant_super_admin ?? false)
                || (bool) ($user->is_god ?? false)
            ) {
                return true;
            }
        }

        $vacancyId = (int) ($vacancy->id ?? 0);
        if ($vacancyId <= 0) {
            return false;
        }

        return JobVacancyTeam::where('tenant_id', $tenantId)
            ->where('vacancy_id', $vacancyId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * List rules for a vacancy (employer view).
     */
    public static function listForVacancy(int $vacancyId): array
    {
        $tenantId = TenantContext::getId();
        try {
            return JobPipelineRule::where('tenant_id', $tenantId)
                ->where('vacancy_id', $vacancyId)
                ->orderBy('trigger_stage')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('JobPipelineRuleService::listForVacancy failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a new rule for a vacancy. Only the job poster can create rules.
     */
    public static function create(int $vacancyId, int $ownerUserId, array $data): array|false
    {
        $tenantId = TenantContext::getId();
        try {
            $vacancy = JobVacancy::find($vacancyId);
            if (!$vacancy || (int) $vacancy->tenant_id !== $tenantId) return false;
            if (!self::canManageVacancy($vacancy, $ownerUserId, $tenantId)) return false;

            $rule = JobPipelineRule::create([
                'tenant_id'      => $tenantId,
                'vacancy_id'     => $vacancyId,
                'name'           => trim($data['name'] ?? 'Auto Rule'),
                'trigger_stage'  => $data['trigger_stage'] ?? 'applied',
                'condition_days' => (int) ($data['condition_days'] ?? 7),
                'action'         => in_array($data['action'] ?? '', ['move_stage','reject','notify_reviewer'])
                                    ? $data['action'] : 'move_stage',
                'action_target'  => $data['action_target'] ?? null,
                'is_active'      => true,
            ]);

            return $rule->toArray();
        } catch (\Throwable $e) {
            Log::error('JobPipelineRuleService::create failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete a rule (owner only).
     */
    public static function delete(int $ruleId, int $ownerUserId): bool
    {
        $tenantId = TenantContext::getId();
        try {
            $rule = JobPipelineRule::with('vacancy')->find($ruleId);
            if (!$rule || (int) $rule->tenant_id !== $tenantId) return false;
            if (!$rule->vacancy || !self::canManageVacancy($rule->vacancy, $ownerUserId, $tenantId)) return false;

            $rule->delete();
            return true;
        } catch (\Throwable $e) {
            Log::error('JobPipelineRuleService::delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Run all active pipeline rules for a vacancy.
     * Moves/rejects applications that have been in a stage longer than condition_days.
     */
    public static function runForVacancy(int $vacancyId): int
    {
        $tenantId = TenantContext::getId();
        $actioned = 0;

        try {
            $rules = JobPipelineRule::where('tenant_id', $tenantId)
                ->where('vacancy_id', $vacancyId)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                $cutoff = now()->subDays($rule->condition_days);

                $applications = JobApplication::where('tenant_id', $tenantId)
                    ->where('vacancy_id', $vacancyId)
                    ->where('status', $rule->trigger_stage)
                    ->where('updated_at', '<=', $cutoff)
                    ->get();

                foreach ($applications as $app) {
                    try {
                        if ($rule->action === 'move_stage' && $rule->action_target) {
                            $app->update(['status' => $rule->action_target, 'stage' => $rule->action_target]);
                            $actioned++;
                        } elseif ($rule->action === 'reject') {
                            $app->update(['status' => 'rejected', 'stage' => 'rejected']);
                            // Notify candidate in their preferred_language — this
                            // rule runs from cron, so the caller locale would
                            // otherwise always be the worker default.
                            $candidate = User::find((int) $app->user_id);
                            LocaleContext::withLocale($candidate, function () use ($app, $vacancyId) {
                                Notification::createNotification(
                                    (int) $app->user_id,
                                    __('svc_notifications.job_application.status_updated_generic'),
                                    "/jobs/{$vacancyId}",
                                    'job_application_status'
                                );
                            });
                            $actioned++;
                        } elseif ($rule->action === 'notify_reviewer') {
                            // Notify the job poster in their own preferred_language.
                            $vacancy = JobVacancy::find($vacancyId);
                            if ($vacancy) {
                                $poster = User::find((int) $vacancy->user_id);
                                LocaleContext::withLocale($poster, function () use ($vacancy, $vacancyId, $app, $rule) {
                                    Notification::createNotification(
                                        (int) $vacancy->user_id,
                                        __('svc_notifications.job_application.stuck_in_stage', ['id' => $app->id, 'stage' => $rule->trigger_stage, 'days' => $rule->condition_days]),
                                        "/jobs/{$vacancyId}/kanban",
                                        'job_application'
                                    );
                                });
                            }
                            $actioned++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('JobPipelineRuleService::runForVacancy app action failed', ['app_id' => $app->id, 'error' => $e->getMessage()]);
                    }
                }

                $rule->update(['last_run_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('JobPipelineRuleService::runForVacancy failed', ['error' => $e->getMessage()]);
        }

        return $actioned;
    }
}
