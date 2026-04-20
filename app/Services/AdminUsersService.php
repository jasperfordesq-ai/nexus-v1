<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminUsersService — Laravel DI-based service for admin user management.
 *
 * Manages user listing, banning, unbanning, and statistics for admin dashboards.
 */
class AdminUsersService
{
    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Get all users for a tenant with filtering and pagination.
     */
    public function getAll(int $tenantId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $query = $this->user->newQuery()->where('tenant_id', $tenantId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name', 'email', 'status', 'role', 'created_at', 'last_active_at'])
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Ban a user.
     */
    public function ban(int $userId, int $tenantId, ?string $reason = null): bool
    {
        $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name'])->first();

        $affected = $this->user->newQuery()
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'banned', 'ban_reason' => $reason, 'updated_at' => now()]);

        if ($affected > 0 && $user && !empty($user->email)) {
            try {
                TenantContext::setById($tenantId);
                $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                $community = TenantContext::getName();
                $builder = EmailTemplateBuilder::make()
                    ->theme('danger')
                    ->title(__('emails_misc.user_ban.banned_title'))
                    ->greeting($firstName)
                    ->paragraph(__('emails_misc.user_ban.banned_body', ['community' => htmlspecialchars($community, ENT_QUOTES, 'UTF-8')]));
                if (!empty($reason)) {
                    $builder->paragraph('<strong>' . __('emails_misc.user_ban.banned_reason_label') . ':</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));
                }
                $html = $builder->render();
                if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.user_ban.banned_subject'), $html)) {
                    Log::warning('[AdminUsersService] ban email failed', ['user_id' => $userId]);
                }
            } catch (\Throwable $e) {
                Log::warning('[AdminUsersService] ban email error: ' . $e->getMessage());
            }
        }

        return $affected > 0;
    }

    /**
     * Unban a user.
     */
    public function unban(int $userId, int $tenantId): bool
    {
        $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name'])->first();

        $affected = $this->user->newQuery()
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'banned')
            ->update(['status' => 'active', 'ban_reason' => null, 'updated_at' => now()]);

        if ($affected > 0 && $user && !empty($user->email)) {
            try {
                TenantContext::setById($tenantId);
                $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                $community = TenantContext::getName();
                $frontendUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix();
                $html = EmailTemplateBuilder::make()
                    ->title(__('emails_misc.user_ban.unbanned_title'))
                    ->greeting($firstName)
                    ->paragraph(__('emails_misc.user_ban.unbanned_body', ['community' => htmlspecialchars($community, ENT_QUOTES, 'UTF-8')]))
                    ->render();
                if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.user_ban.unbanned_subject'), $html)) {
                    Log::warning('[AdminUsersService] unban email failed', ['user_id' => $userId]);
                }
            } catch (\Throwable $e) {
                Log::warning('[AdminUsersService] unban email error: ' . $e->getMessage());
            }
        }

        return $affected > 0;
    }

    /**
     * Get user statistics for admin dashboard.
     */
    public function getStats(int $tenantId): array
    {
        $byStatus = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $activeLastWeek = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('last_active_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total'            => array_sum(array_map('intval', $byStatus)),
            'by_status'        => $byStatus,
            'active_last_week' => $activeLastWeek,
        ];
    }
}
