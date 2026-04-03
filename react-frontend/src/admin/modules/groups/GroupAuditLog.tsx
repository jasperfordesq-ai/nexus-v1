// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Audit Log
 * Displays an audit trail of actions taken within a specific group.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Chip,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import { ScrollText, ChevronDown, ChevronUp } from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { GlassCard } from '@/components/ui';
import { useTranslation } from 'react-i18next';

interface GroupAuditLogProps {
  groupId: number;
}

interface AuditEntry {
  id: number;
  action: string;
  user_id: number;
  details: Record<string, unknown> | string | null;
  ip_address: string | null;
  created_at: string;
}

const ACTION_COLORS: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'default'> = {
  created: 'success',
  updated: 'primary',
  deleted: 'danger',
  joined: 'success',
  left: 'warning',
  promoted: 'primary',
  demoted: 'warning',
  banned: 'danger',
  unbanned: 'success',
  settings_changed: 'primary',
  member_removed: 'danger',
  role_changed: 'primary',
  invitation_sent: 'primary',
  file_uploaded: 'success',
  file_deleted: 'danger',
};

function getActionColor(action: string): 'primary' | 'success' | 'warning' | 'danger' | 'default' {
  return ACTION_COLORS[action] ?? 'default';
}

function ExpandableDetails({ details }: { details: Record<string, unknown> | string | null }) {
  const [expanded, setExpanded] = useState(false);

  if (!details) return <span className="text-default-300">-</span>;

  const text =
    typeof details === 'string' ? details : JSON.stringify(details, null, 2);

  if (text.length <= 50) {
    return (
      <span className="text-xs text-default-500 font-mono break-all">{text}</span>
    );
  }

  return (
    <div>
      <button
        type="button"
        className="flex items-center gap-1 text-xs text-primary hover:text-primary-600 transition-colors"
        onClick={() => setExpanded((prev) => !prev)}
      >
        {expanded ? <ChevronUp size={12} /> : <ChevronDown size={12} />}
        {expanded ? 'Collapse' : 'Expand'}
      </button>
      {expanded && (
        <pre className="mt-1 text-xs text-default-500 font-mono bg-default-100 p-2 rounded-md overflow-x-auto max-h-48 whitespace-pre-wrap break-all">
          {text}
        </pre>
      )}
    </div>
  );
}

export function GroupAuditLog({ groupId }: GroupAuditLogProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionFilter, setActionFilter] = useState<string>('all');

  const loadAuditLog = useCallback(async () => {
    setLoading(true);
    try {
      const url =
        actionFilter && actionFilter !== 'all'
          ? `/v2/admin/groups/${groupId}/audit-log?action=${actionFilter}`
          : `/v2/admin/groups/${groupId}/audit-log`;

      const res = await api.get(url);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setEntries(payload);
        } else if (
          payload &&
          typeof payload === 'object' &&
          'data' in (payload as Record<string, unknown>)
        ) {
          const inner = (payload as Record<string, unknown>).data;
          setEntries(Array.isArray(inner) ? inner : []);
        } else {
          setEntries([]);
        }
      }
    } catch {
      toast.error(t('groups.audit_load_failed', 'Failed to load audit log'));
    } finally {
      setLoading(false);
    }
  }, [groupId, actionFilter, toast, t]);

  useEffect(() => {
    loadAuditLog();
  }, [loadAuditLog]);

  // Derive unique action types for the filter dropdown
  const uniqueActions = Array.from(new Set(entries.map((e) => e.action))).sort();

  return (
    <GlassCard className="p-5 space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <ScrollText size={18} className="text-primary" />
          <h3 className="text-base font-semibold text-foreground">
            {t('groups.audit_log_title', 'Audit Log')}
          </h3>
        </div>

        <Select
          label={t('groups.audit_filter_label', 'Filter by action')}
          selectedKeys={new Set([actionFilter])}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            if (typeof selected === 'string') setActionFilter(selected);
          }}
          variant="bordered"
          size="sm"
          className="max-w-[200px]"
        >
          <SelectItem key="all">
            {t('groups.audit_all_actions', 'All actions')}
          </SelectItem>
          {uniqueActions.map((action) => (
            <SelectItem key={action}>{action}</SelectItem>
          ))}
        </Select>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-10">
          <Spinner size="lg" />
        </div>
      ) : entries.length === 0 ? (
        <p className="text-sm text-default-400 text-center py-8">
          {t('groups.audit_empty', 'No audit entries found')}
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-default-200">
                <th className="text-left py-2 px-3 text-default-500 font-medium">
                  {t('groups.audit_col_date', 'Date')}
                </th>
                <th className="text-left py-2 px-3 text-default-500 font-medium">
                  {t('groups.audit_col_action', 'Action')}
                </th>
                <th className="text-left py-2 px-3 text-default-500 font-medium">
                  {t('groups.audit_col_user', 'User')}
                </th>
                <th className="text-left py-2 px-3 text-default-500 font-medium">
                  {t('groups.audit_col_details', 'Details')}
                </th>
                <th className="text-left py-2 px-3 text-default-500 font-medium">
                  {t('groups.audit_col_ip', 'IP Address')}
                </th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <tr
                  key={entry.id}
                  className="border-b border-default-100 hover:bg-default-50 transition-colors"
                >
                  <td className="py-2 px-3 text-xs text-default-500 whitespace-nowrap">
                    {new Date(entry.created_at).toLocaleString()}
                  </td>
                  <td className="py-2 px-3">
                    <Chip
                      size="sm"
                      variant="flat"
                      color={getActionColor(entry.action)}
                    >
                      {entry.action}
                    </Chip>
                  </td>
                  <td className="py-2 px-3 text-xs text-default-600">
                    #{entry.user_id}
                  </td>
                  <td className="py-2 px-3 max-w-[300px]">
                    <ExpandableDetails details={entry.details} />
                  </td>
                  <td className="py-2 px-3 text-xs text-default-400 font-mono">
                    {entry.ip_address ?? '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </GlassCard>
  );
}

export default GroupAuditLog;
