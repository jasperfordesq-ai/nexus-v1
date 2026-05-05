// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Onboarding Page
 * Displays a simplified onboarding funnel visualization (no recharts) and
 * a table of pending members awaiting approval.
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Card,
  CardBody,
  CardHeader,
  Progress,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
  Spinner,
} from '@heroui/react';
import ArrowDown from 'lucide-react/icons/arrow-down';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { formatServerDate } from '@/lib/serverTime';
import { adminCrm, adminUsers } from '@/admin/api/adminApi';
import type { AdminUser, CrmFunnelStage } from '@/admin/api/types';
import { DataTable, PageHeader, ConfirmModal } from '@/admin/components';
import type { Column } from '@/admin/components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface FunnelData {
  stages: CrmFunnelStage[];
}

const PAGE_SIZE = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function OnboardingPage() {
  const { t } = useTranslation('broker');
  const toast = useToast();
  const { tenantPath } = useTenant();
  usePageTitle(t('onboarding.page_title'));

  // Funnel state
  const [funnel, setFunnel] = useState<FunnelData | null>(null);
  const [funnelLoading, setFunnelLoading] = useState(true);

  // Pending members state
  const [members, setMembers] = useState<AdminUser[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [membersLoading, setMembersLoading] = useState(true);

  // Approve confirmation
  const [approveUser, setApproveUser] = useState<AdminUser | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ─── Fetch funnel ─────────────────────────────────────────────────────────

  const fetchFunnel = useCallback(async () => {
    setFunnelLoading(true);
    try {
      const res = await adminCrm.getFunnel();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'stages' in (payload as Record<string, unknown>)) {
          setFunnel(payload as FunnelData);
        }
      }
    } catch {
      // Silently fail — funnel is non-critical
    } finally {
      setFunnelLoading(false);
    }
  }, []);

  // ─── Fetch pending members ────────────────────────────────────────────────

  const fetchMembers = useCallback(async () => {
    setMembersLoading(true);
    try {
      const res = await adminUsers.list({
        status: 'pending',
        page,
        limit: PAGE_SIZE,
      });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setMembers(payload as AdminUser[]);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: AdminUser[]; meta?: { total: number } };
          setMembers(paged.data || []);
          setTotal(paged.meta?.total ?? 0);
        }
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setMembersLoading(false);
    }
  }, [page, toast, t]);

  useEffect(() => {
    fetchFunnel();
  }, [fetchFunnel]);

  useEffect(() => {
    fetchMembers();
  }, [fetchMembers]);

  // ─── Approve action ───────────────────────────────────────────────────────

  const handleApprove = useCallback(async () => {
    if (!approveUser) return;
    setActionLoading(true);
    try {
      const res = await adminUsers.approve(approveUser.id);
      if (res.success) {
        toast.success(t('members.approved_success'));
        setApproveUser(null);
        fetchMembers();
        // Approving a member shifts their funnel stage — refetch the funnel
        // counts so the visualisation stays consistent with the table.
        fetchFunnel();
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setActionLoading(false);
    }
  }, [approveUser, toast, t, fetchMembers, fetchFunnel]);

  // ─── Funnel visualization ─────────────────────────────────────────────────

  const stages = funnel?.stages ?? [];
  const maxCount = stages.length > 0 ? (stages[0]?.count ?? 1) : 1;

  // Stage labels mapped from translation keys
  const stageLabels: Record<string, string> = {
    Registered: t('onboarding.stage_registered'),
    'Email Verified': t('onboarding.stage_email_verified'),
    'Profile Complete': t('onboarding.stage_profile_complete'),
    'First Exchange': t('onboarding.stage_first_exchange'),
  };

  // ─── Table columns ────────────────────────────────────────────────────────

  const columns: Column<AdminUser>[] = useMemo(
    () => [
      {
        key: 'name',
        label: t('members.col_name'),
        sortable: true,
        render: (user: AdminUser) => (
          <span className="font-medium text-foreground">{user.name}</span>
        ),
      },
      {
        key: 'email',
        label: t('members.col_email'),
        sortable: true,
      },
      {
        key: 'created_at',
        label: t('members.col_joined'),
        sortable: true,
        render: (user: AdminUser) =>
          formatServerDate(user.created_at),
      },
      {
        key: 'actions',
        label: t('members.col_actions'),
        render: (user: AdminUser) => (
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly variant="light" size="sm" aria-label={t('members.col_actions')}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label={t('members.col_actions')}>
              <DropdownItem
                key="approve"
                onPress={() => setApproveUser(user)}
              >
                {t('members.approve')}
              </DropdownItem>
              <DropdownItem
                key="view"
                onPress={() =>
                  window.open(tenantPath(`/profile/${user.id}`), '_blank')
                }
              >
                {t('members.view_profile')}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ),
      },
    ],
    [t, tenantPath],
  );

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <PageHeader
        title={t('onboarding.title')}
        description={t('onboarding.description')}
      />

      {/* ── Onboarding Funnel ──────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('onboarding.funnel_title')}</h3>
        </CardHeader>
        <CardBody>
          {funnelLoading ? (
            <div className="flex items-center justify-center py-12">
              <Spinner size="lg" label={t('common.loading')} />
            </div>
          ) : stages.length === 0 ? (
            <p className="text-sm text-default-400 text-center py-8">
              {t('common.no_data')}
            </p>
          ) : (
            <div className="space-y-2">
              {stages.map((stage, index) => {
                const pct = maxCount > 0 ? (stage.count / maxCount) * 100 : 0;
                const prevStage = index > 0 ? stages[index - 1] : undefined;
                const conversionRate =
                  prevStage && prevStage.count > 0
                    ? Math.round((stage.count / prevStage.count) * 1000) / 10
                    : null;
                const label = stageLabels[stage.name] || stage.name;

                return (
                  <div key={stage.name}>
                    {/* Conversion arrow between stages */}
                    {index > 0 && (
                      <div className="flex items-center justify-center gap-2 py-1">
                        <ArrowDown size={14} className="text-default-300" />
                        {conversionRate !== null && (
                          <Chip size="sm" variant="flat" color="default">
                            {conversionRate}%
                          </Chip>
                        )}
                        <ArrowDown size={14} className="text-default-300" />
                      </div>
                    )}

                    {/* Stage card */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                      <div className="min-w-0 sm:w-40 sm:shrink-0">
                        <p className="text-sm font-medium text-foreground truncate">
                          {label}
                        </p>
                        <p className="text-xs text-default-400">
                          {stage.count.toLocaleString()}
                        </p>
                      </div>
                      <div className="min-w-0 flex-1">
                        <Progress
                          value={pct}
                          size="lg"
                          color={
                            pct > 60
                              ? 'success'
                              : pct > 30
                                ? 'warning'
                                : 'danger'
                          }
                          aria-label={`${label}: ${Math.round(pct)}%`}
                          classNames={{
                            track: 'h-6',
                            indicator: 'h-6',
                          }}
                        />
                      </div>
                      <div className="text-left sm:w-16 sm:text-right sm:shrink-0">
                        <span className="text-sm font-bold text-foreground">
                          {Math.round(pct)}%
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })}

              {/* Overall conversion */}
              {stages.length >= 2 && (() => {
                const first = stages[0];
                const last = stages[stages.length - 1];
                if (!first || !last) return null;
                const overall =
                  first.count > 0
                    ? ((last.count / first.count) * 100).toFixed(1)
                    : '0';
                return (
                  <div className="mt-4 pt-4 border-t border-divider text-center">
                    <p className="text-sm text-default-500">
                      {first.name} → {last.name}
                    </p>
                    <p className="text-2xl font-bold text-foreground mt-1">
                      {overall}%
                    </p>
                  </div>
                );
              })()}
            </div>
          )}
        </CardBody>
      </Card>

      {/* ── Pending Approvals Table ────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('onboarding.pending_approvals')}</h3>
        </CardHeader>
        <CardBody>
          <DataTable<AdminUser>
            columns={columns}
            data={members}
            keyField="id"
            isLoading={membersLoading}
            searchable={false}
            totalItems={total}
            page={page}
            pageSize={PAGE_SIZE}
            onPageChange={setPage}
            onRefresh={fetchMembers}
            emptyContent={
              <div className="flex flex-col items-center py-8">
                <p className="text-default-400">{t('onboarding.no_pending')}</p>
              </div>
            }
          />
        </CardBody>
      </Card>

      {/* Approve confirmation */}
      <ConfirmModal
        isOpen={!!approveUser}
        onClose={() => setApproveUser(null)}
        onConfirm={handleApprove}
        title={t('members.confirm_approve_title')}
        message={t('members.confirm_approve_message')}
        confirmLabel={t('members.approve')}
        cancelLabel={t('common.cancel')}
        confirmColor="primary"
        isLoading={actionLoading}
      />
    </div>
  );
}
