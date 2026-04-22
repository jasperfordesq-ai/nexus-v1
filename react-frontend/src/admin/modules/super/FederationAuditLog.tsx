// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Audit Log
 * Dedicated federation-specific audit log with category/level filtering,
 * date range, search, pagination, and CSV export.
 */

import { useState, useCallback, useEffect } from 'react';
import { Select, SelectItem, Input, Button, Chip } from '@heroui/react';
import Download from 'lucide-react/icons/download';
import X from 'lucide-react/icons/x';
import Activity from 'lucide-react/icons/activity';
import Network from 'lucide-react/icons/network';
import Handshake from 'lucide-react/icons/handshake';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { usePageTitle } from '@/hooks';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, type Column } from '../../components';
import type { SuperAuditEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';
const PAGE_SIZE = 25;

const CATEGORIES = [
  { key: '', labelKey: 'super.cat_all_categories' },
  { key: 'system_controls', labelKey: 'super.cat_system_controls' },
  { key: 'partnerships', labelKey: 'super.cat_partnerships' },
  { key: 'whitelist', labelKey: 'super.cat_whitelist' },
  { key: 'features', labelKey: 'super.cat_features' },
  { key: 'lockdown', labelKey: 'super.cat_lockdown' },
];

const LEVELS = [
  { key: '', labelKey: 'super.level_all_levels' },
  { key: 'info', labelKey: 'super.level_info' },
  { key: 'warning', labelKey: 'super.level_warning' },
  { key: 'critical', labelKey: 'super.level_critical' },
];

const FEDERATION_ACTION_TYPES = [
  'federation_lockdown',
  'federation_updated',
  'federation_whitelist_add',
  'federation_whitelist_remove',
  'federation_partnership_suspend',
  'federation_partnership_terminate',
  'federation_feature_toggle',
  'federation_controls_updated',
];

function categorizeAction(actionType: string): string {
  if (actionType.includes('lockdown')) return 'lockdown';
  if (actionType.includes('partnership')) return 'partnerships';
  if (actionType.includes('whitelist')) return 'whitelist';
  if (actionType.includes('feature') || actionType.includes('toggle')) return 'features';
  if (actionType.includes('control') || actionType.includes('updated')) return 'system_controls';
  return 'system_controls';
}

function inferLevel(actionType: string): string {
  if (actionType.includes('lockdown') || actionType.includes('terminate')) return 'critical';
  if (actionType.includes('suspend') || actionType.includes('remove')) return 'warning';
  return 'info';
}

const levelColorMap: Record<string, 'primary' | 'warning' | 'danger'> = {
  info: 'primary',
  warning: 'warning',
  critical: 'danger',
};

const categoryColorMap: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  system_controls: 'primary',
  partnerships: 'secondary',
  whitelist: 'success',
  features: 'primary',
  lockdown: 'danger',
};

export function FederationAuditLog() {
  const { t } = useTranslation('admin');
  usePageTitle("Super Admin");

  const [logs, setLogs] = useState<SuperAuditEntry[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [level, setLevel] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [page, setPage] = useState(1);

  // Stats
  const [stats, setStats] = useState({ total: 0, federation: 0, partnerships: 0, emergency: 0 });

  const loadLogs = useCallback(async () => {
    setLoading(true);

    // Build action_type filter based on category and/or level.
    // When a level is selected, map it to the action_type patterns that
    // match that level so filtering happens server-side, keeping pagination
    // counts accurate.
    let actionTypeFilter: string | undefined;

    if (level) {
      // Derive matching action types for the selected level
      const matchingActions = FEDERATION_ACTION_TYPES.filter(
        (at) => inferLevel(at) === level,
      );
      if (matchingActions.length > 0) {
        // If a category is also selected, intersect the two sets
        const categoryActions = category
          ? matchingActions.filter((at) => categorizeAction(at) === category)
          : matchingActions;
        // Pass as comma-separated list; backend accepts first match in the
        // current implementation — use the most representative action type
        actionTypeFilter = (categoryActions.length > 0 ? categoryActions : matchingActions)[0];
      }
    } else if (category) {
      const matchingActions = FEDERATION_ACTION_TYPES.filter(
        (at) => categorizeAction(at) === category,
      );
      actionTypeFilter = matchingActions.length > 0 ? matchingActions[0] : category;
    }

    const res = await adminSuper.getAudit({
      search: search || undefined,
      action_type: actionTypeFilter,
      target_type: 'federation',
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      limit: PAGE_SIZE,
      offset: (page - 1) * PAGE_SIZE,
    });

    if (res.success && res.data) {
      const entries = Array.isArray(res.data) ? res.data : [];
      setLogs(entries);
      if (entries.length < PAGE_SIZE) {
        setTotalItems((page - 1) * PAGE_SIZE + entries.length);
      } else {
        setTotalItems(page * PAGE_SIZE + 1);
      }
    }
    setLoading(false);
  }, [search, category, level, dateFrom, dateTo, page]);

  // Load stats once
  useEffect(() => {
    (async () => {
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      const dateStr = thirtyDaysAgo.toISOString().slice(0, 10);

      const res = await adminSuper.getAudit({
        target_type: 'federation',
        date_from: dateStr,
        limit: 200,
        offset: 0,
      });
      if (res.success && res.data) {
        const all = Array.isArray(res.data) ? res.data : [];
        setStats({
          total: all.length,
          federation: all.filter((e) => categorizeAction(e.action_type) === 'system_controls' || categorizeAction(e.action_type) === 'features').length,
          partnerships: all.filter((e) => categorizeAction(e.action_type) === 'partnerships').length,
          emergency: all.filter((e) => inferLevel(e.action_type) === 'critical').length,
        });
      }
    })();
  }, []);

  useEffect(() => { loadLogs(); }, [loadLogs]);

  const resetAndFilter = () => {
    if (page !== 1) setPage(1);
  };

  const clearFilters = () => {
    setSearch('');
    setCategory('');
    setLevel('');
    setDateFrom('');
    setDateTo('');
    setPage(1);
  };

  const hasFilters = !!(search || category || level || dateFrom || dateTo);

  const exportCsv = () => {
    if (logs.length === 0) return;
    const headers = ["ID", "Timestamp", "Category", "Level", "Description", "Actor"];
    const rows = logs.map((entry) => [
      entry.id,
      entry.created_at,
      categorizeAction(entry.action_type),
      inferLevel(entry.action_type),
      `"${(entry.description || '').replace(/"/g, '""')}"`,
      entry.actor_name || `User #${entry.actor_id}`,
    ]);
    const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `federation-audit-log-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  const columns: Column<SuperAuditEntry>[] = [
    {
      key: 'created_at',
      label: "Timestamp",
      sortable: true,
      render: (entry) => (
        <span className="text-sm text-default-500">
          {new Date(entry.created_at).toLocaleString()}
        </span>
      ),
    },
    {
      key: 'category',
      label: "Category",
      render: (entry) => {
        const cat = categorizeAction(entry.action_type);
        return (
          <Chip size="sm" variant="flat" color={categoryColorMap[cat] || 'primary'}>
            {cat.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
          </Chip>
        );
      },
    },
    {
      key: 'level',
      label: "Level",
      render: (entry) => {
        const lvl = inferLevel(entry.action_type);
        return (
          <Chip size="sm" variant="flat" color={levelColorMap[lvl] || 'primary'}>
            {lvl.charAt(0).toUpperCase() + lvl.slice(1)}
          </Chip>
        );
      },
    },
    {
      key: 'description',
      label: "Description",
      render: (entry) => (
        <span className="text-sm">{entry.description || entry.action_type.replace(/_/g, ' ')}</span>
      ),
    },
    {
      key: 'actor',
      label: "Actor",
      render: (entry) => (
        <span className="text-sm text-default-500">
          {entry.actor_name || `User #${entry.actor_id}`}
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={"Federation Audit Log"}
        description={"View the audit log for all federation actions taken by super admins"}
        actions={
          <Button
            variant="flat"
            size="sm"
            startContent={<Download size={16} />}
            onPress={exportCsv}
            isDisabled={logs.length === 0}
          >
            {"Export CSV"}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label={"Total Actions 30d"}
          value={stats.total}
          icon={Activity}
          color="primary"
          loading={loading && stats.total === 0}
        />
        <StatCard
          label={"Federation Changes"}
          value={stats.federation}
          icon={Network}
          color="success"
          loading={loading && stats.total === 0}
        />
        <StatCard
          label={"Partnership Actions"}
          value={stats.partnerships}
          icon={Handshake}
          color="secondary"
          loading={loading && stats.total === 0}
        />
        <StatCard
          label={"Emergency Actions"}
          value={stats.emergency}
          icon={AlertTriangle}
          color="danger"
          loading={loading && stats.total === 0}
        />
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-4 items-end">
        <Select
          label={"Category"}
          size="sm"
          className="max-w-[200px]"
          selectedKeys={category ? [category] : []}
          onSelectionChange={(keys) => {
            setCategory(String(Array.from(keys)[0] || ''));
            resetAndFilter();
          }}
        >
          {CATEGORIES.filter((c) => c.key !== '').map((c) => (
            <SelectItem key={c.key}>{t(c.labelKey)}</SelectItem>
          ))}
        </Select>

        <Select
          label={"Level"}
          size="sm"
          className="max-w-[160px]"
          selectedKeys={level ? [level] : []}
          onSelectionChange={(keys) => {
            setLevel(String(Array.from(keys)[0] || ''));
            resetAndFilter();
          }}
        >
          {LEVELS.filter((l) => l.key !== '').map((l) => (
            <SelectItem key={l.key}>{t(l.labelKey)}</SelectItem>
          ))}
        </Select>

        <Input
          label={"From Date"}
          type="date"
          size="sm"
          className="max-w-[170px]"
          value={dateFrom}
          onValueChange={(v) => { setDateFrom(v); resetAndFilter(); }}
        />

        <Input
          label={"To Date"}
          type="date"
          size="sm"
          className="max-w-[170px]"
          value={dateTo}
          onValueChange={(v) => { setDateTo(v); resetAndFilter(); }}
        />

        <Input
          label={"Search"}
          size="sm"
          className="max-w-[200px]"
          value={search}
          onValueChange={(v) => { setSearch(v); resetAndFilter(); }}
          isClearable
          onClear={() => { setSearch(''); resetAndFilter(); }}
        />

        {hasFilters && (
          <Button
            size="sm"
            variant="light"
            color="danger"
            startContent={<X size={14} />}
            onPress={clearFilters}
          >
            {"Clear"}
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        data={logs}
        isLoading={loading}
        onRefresh={loadLogs}
        totalItems={totalItems}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
      />
    </div>
  );
}

export default FederationAuditLog;
