// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';

import ChevronDown from 'lucide-react/icons/chevron-down';
import ChevronUp from 'lucide-react/icons/chevron-up';
import ScrollText from 'lucide-react/icons/scroll-text';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { getFormattingLocale } from '@/lib/helpers';
import {
  Button,
  Chip,
  GlassCard,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@/components/ui';

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

interface AuditPage {
  items: AuditEntry[];
  actions: string[];
  pagination: {
    page: number;
    has_more: boolean;
  };
}

export const ACTION_COLORS: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'default'> = {
  group_created: 'success',
  group_updated: 'primary',
  group_deleted: 'danger',
  group_featured: 'success',
  group_image_updated: 'primary',
  group_status_changed: 'warning',
  member_joined: 'success',
  member_join_requested: 'warning',
  member_join_rejected: 'warning',
  member_left: 'warning',
  member_kicked: 'danger',
  member_banned: 'danger',
  member_role_changed: 'primary',
  member_removed: 'danger',
  invite_revoked: 'warning',
  discussion_created: 'success',
  post_created: 'success',
  post_moderated: 'warning',
  challenge_created: 'success',
  challenge_completed: 'success',
  challenge_reward_awarded: 'primary',
  challenge_cancelled: 'warning',
  file_uploaded: 'success',
  media_uploaded: 'success',
  media_deleted: 'danger',
  announcement_deleted: 'danger',
  qa_question_deleted: 'danger',
  qa_answer_deleted: 'danger',
  qa_answer_accepted: 'success',
  wiki_page_deleted: 'danger',
  chatroom_deleted: 'danger',
  chatroom_message_deleted: 'danger',
  chatroom_message_pinned: 'primary',
  chatroom_message_unpinned: 'warning',
  team_task_deleted: 'danger',
  scheduled_post_cancelled: 'warning',
  webhook_deleted: 'danger',
  webhook_toggled: 'primary',
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
  role_changed: 'primary',
  invitation_sent: 'primary',
  file_deleted: 'danger',
};

export const ACTION_LABEL_KEYS: Record<string, string> = {
  group_created: 'groups.audit_action_created',
  group_updated: 'groups.audit_action_updated',
  group_deleted: 'groups.audit_action_deleted',
  group_featured: 'groups.audit_action_group_featured',
  group_image_updated: 'groups.audit_action_group_image_updated',
  group_status_changed: 'groups.audit_action_group_status_changed',
  member_joined: 'groups.audit_action_joined',
  member_join_requested: 'groups.audit_action_member_join_requested',
  member_join_rejected: 'groups.audit_action_member_join_rejected',
  member_left: 'groups.audit_action_left',
  member_kicked: 'groups.audit_action_member_kicked',
  member_banned: 'groups.audit_action_banned',
  member_role_changed: 'groups.audit_action_role_changed',
  member_removed: 'groups.audit_action_member_removed',
  invite_revoked: 'groups.audit_action_invite_revoked',
  discussion_created: 'groups.audit_action_discussion_created',
  post_created: 'groups.audit_action_post_created',
  post_moderated: 'groups.audit_action_post_moderated',
  challenge_created: 'groups.audit_action_challenge_created',
  challenge_completed: 'groups.audit_action_challenge_completed',
  challenge_reward_awarded: 'groups.audit_action_challenge_reward_awarded',
  challenge_cancelled: 'groups.audit_action_challenge_cancelled',
  file_uploaded: 'groups.audit_action_file_uploaded',
  media_uploaded: 'groups.audit_action_media_uploaded',
  media_deleted: 'groups.audit_action_media_deleted',
  announcement_deleted: 'groups.audit_action_announcement_deleted',
  qa_question_deleted: 'groups.audit_action_qa_question_deleted',
  qa_answer_deleted: 'groups.audit_action_qa_answer_deleted',
  qa_answer_accepted: 'groups.audit_action_qa_answer_accepted',
  wiki_page_deleted: 'groups.audit_action_wiki_page_deleted',
  chatroom_deleted: 'groups.audit_action_chatroom_deleted',
  chatroom_message_deleted: 'groups.audit_action_chatroom_message_deleted',
  chatroom_message_pinned: 'groups.audit_action_chatroom_message_pinned',
  chatroom_message_unpinned: 'groups.audit_action_chatroom_message_unpinned',
  team_task_deleted: 'groups.audit_action_team_task_deleted',
  scheduled_post_cancelled: 'groups.audit_action_scheduled_post_cancelled',
  webhook_deleted: 'groups.audit_action_webhook_deleted',
  webhook_toggled: 'groups.audit_action_webhook_toggled',
  created: 'groups.audit_action_created',
  updated: 'groups.audit_action_updated',
  deleted: 'groups.audit_action_deleted',
  joined: 'groups.audit_action_joined',
  left: 'groups.audit_action_left',
  promoted: 'groups.audit_action_promoted',
  demoted: 'groups.audit_action_demoted',
  banned: 'groups.audit_action_banned',
  unbanned: 'groups.audit_action_unbanned',
  settings_changed: 'groups.audit_action_settings_changed',
  role_changed: 'groups.audit_action_role_changed',
  invitation_sent: 'groups.audit_action_invitation_sent',
  file_deleted: 'groups.audit_action_file_deleted',
};

const SENSITIVE_DETAIL_KEY = /(?:password|passphrase|secret|token|authorization|cookie|api[_-]?key)/i;

function getActionLabel(action: string, t: TFunction): string {
  const key = ACTION_LABEL_KEYS[action];
  return key ? t(key) : t('groups.audit_action_unknown');
}

export function redactAuditDetails(value: unknown, replacement: string): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => redactAuditDetails(item, replacement));
  }
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value as Record<string, unknown>).map(([key, item]) => [
      key,
      SENSITIVE_DETAIL_KEY.test(key) ? replacement : redactAuditDetails(item, replacement),
    ]));
  }
  if (typeof value === 'string') {
    return value
      .replace(/(bearer\s+)[^\s,;"']+/gi, `$1${replacement}`)
      .replace(/((?:password|passphrase|secret|token|api[_-]?key)\s*[:=]\s*)[^\s,;"']+/gi, `$1${replacement}`);
  }
  return value;
}

function getActionColor(action: string) {
  return ACTION_COLORS[action] ?? 'default';
}

function parseAuditPage(payload: unknown): AuditPage {
  if (Array.isArray(payload)) {
    return {
      items: payload as AuditEntry[],
      actions: [],
      pagination: { page: 1, has_more: false },
    };
  }
  if (!payload || typeof payload !== 'object') {
    throw new Error('Invalid audit response');
  }

  const record = payload as Record<string, unknown>;
  if (Array.isArray(record.data)) {
    return {
      items: record.data as AuditEntry[],
      actions: [],
      pagination: { page: 1, has_more: false },
    };
  }
  const pagination = record.pagination && typeof record.pagination === 'object'
    ? record.pagination as Record<string, unknown>
    : {};

  return {
    items: Array.isArray(record.items) ? record.items as AuditEntry[] : [],
    actions: Array.isArray(record.actions)
      ? record.actions.filter((action): action is string => typeof action === 'string')
      : [],
    pagination: {
      page: typeof pagination.page === 'number' ? pagination.page : 1,
      has_more: pagination.has_more === true,
    },
  };
}

function ExpandableDetails({ details }: { details: AuditEntry['details'] }) {
  const [expanded, setExpanded] = useState(false);
  const { t } = useTranslation('admin_groups');

  if (!details) return <span className="text-muted">-</span>;
  const safeDetails = redactAuditDetails(details, t('groups.audit_redacted'));
  const text = typeof safeDetails === 'string' ? safeDetails : JSON.stringify(safeDetails, null, 2);

  if (text.length <= 50) {
    return <span className="break-all font-mono text-xs text-muted">{text}</span>;
  }

  return (
    <div>
      <Button
        variant="tertiary"
        size="sm"
        className="flex min-h-10 min-w-0 items-center gap-1 p-0 text-xs text-accent"
        aria-expanded={expanded}
        onPress={() => setExpanded((previous) => !previous)}
      >
        {expanded ? <ChevronUp size={12} aria-hidden="true" /> : <ChevronDown size={12} aria-hidden="true" />}
        {expanded ? t('groups.collapse') : t('groups.expand')}
      </Button>
      {expanded && (
        <pre className="mt-1 max-h-48 overflow-x-auto whitespace-pre-wrap break-all rounded-md bg-surface-secondary p-2 font-mono text-xs text-muted">
          {text}
        </pre>
      )}
    </div>
  );
}

export function GroupAuditLog({ groupId }: GroupAuditLogProps) {
  const { t } = useTranslation('admin_groups');
  const toast = useToast();
  const requestGeneration = useRef(0);

  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [actions, setActions] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const [actionFilter, setActionFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);

  const loadAuditLog = useCallback(async (requestedPage = 1, append = false) => {
    const generation = ++requestGeneration.current;
    if (append) setLoadingMore(true);
    else setLoading(true);
    setLoadFailed(false);

    try {
      const query = new URLSearchParams();
      if (actionFilter !== 'all') query.set('action', actionFilter);
      if (requestedPage > 1) query.set('page', String(requestedPage));
      const suffix = query.size > 0 ? `?${query.toString()}` : '';
      const response = await api.get(`/v2/admin/groups/${groupId}/audit-log${suffix}`);
      if (!response.success) throw new Error(t('groups.audit_load_failed'));

      const result = parseAuditPage(response.data);
      if (generation !== requestGeneration.current) return;
      setActions(result.actions);
      setPage(result.pagination.page);
      setHasMore(result.pagination.has_more);
      setEntries((current) => {
        const next = append ? [...current, ...result.items] : result.items;
        return Array.from(new Map(next.map((entry) => [entry.id, entry])).values());
      });
    } catch {
      if (generation !== requestGeneration.current) return;
      setLoadFailed(true);
      toast.error(t('groups.audit_load_failed'));
    } finally {
      if (generation === requestGeneration.current) {
        setLoading(false);
        setLoadingMore(false);
      }
    }
  }, [actionFilter, groupId, t, toast]);

  useEffect(() => {
    setEntries([]);
    setPage(1);
    setHasMore(false);
    void loadAuditLog(1, false);
  }, [loadAuditLog]);

  const filterActions = actions.length > 0
    ? actions
    : Array.from(new Set(entries.map((entry) => entry.action))).sort();

  return (
    <GlassCard className="space-y-5 p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <ScrollText size={18} className="text-accent" aria-hidden="true" />
          <h3 className="text-base font-semibold text-foreground">{t('groups.audit_log_title')}</h3>
        </div>

        <Select
          label={t('groups.audit_filter_label')}
          selectedKeys={new Set([actionFilter])}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            if (typeof selected === 'string') setActionFilter(selected);
          }}
          variant="secondary"
          size="sm"
          className="w-full sm:max-w-[220px]"
          items={[
            { key: 'all', label: t('groups.audit_all_actions') },
            ...filterActions.map((action) => ({ key: action, label: getActionLabel(action, t) })),
          ]}
        >
          {(item) => <SelectItem key={item.key} id={item.key}>{item.label}</SelectItem>}
        </Select>
      </div>

      {loading && entries.length === 0 ? (
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-10">
          <Spinner size="lg" />
        </div>
      ) : loadFailed && entries.length === 0 ? (
        <div role="alert" className="flex flex-col items-center gap-3 py-8 text-center">
          <p className="text-sm text-danger">{t('groups.audit_load_failed')}</p>
          <Button variant="tertiary" onPress={() => void loadAuditLog(1, false)}>
            {t('common.retry')}
          </Button>
        </div>
      ) : entries.length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">{t('groups.audit_empty')}</p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <Table aria-label={t('groups.audit_log_title')}>
              <TableHeader>
                <TableColumn>{t('groups.audit_col_date')}</TableColumn>
                <TableColumn>{t('groups.audit_col_action')}</TableColumn>
                <TableColumn>{t('groups.audit_col_user')}</TableColumn>
                <TableColumn>{t('groups.audit_col_details')}</TableColumn>
                <TableColumn>{t('groups.audit_col_ip')}</TableColumn>
              </TableHeader>
              <TableBody>
                {entries.map((entry) => (
                  <TableRow key={entry.id}>
                    <TableCell><span className="whitespace-nowrap text-xs text-muted">{new Date(entry.created_at).toLocaleString(getFormattingLocale())}</span></TableCell>
                    <TableCell>
                      <Chip size="sm" variant="soft" color={getActionColor(entry.action)}>
                        {getActionLabel(entry.action, t)}
                      </Chip>
                    </TableCell>
                    <TableCell><span className="text-xs text-muted">#{entry.user_id}</span></TableCell>
                    <TableCell><div className="max-w-[300px]"><ExpandableDetails details={entry.details} /></div></TableCell>
                    <TableCell><span className="font-mono text-xs text-muted">{entry.ip_address ?? '-'}</span></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          {hasMore && (
            <div className="flex justify-center">
              <Button
                variant="tertiary"
                isLoading={loadingMore}
                onPress={() => void loadAuditLog(page + 1, true)}
              >
                {t('common.load_more')}
              </Button>
            </div>
          )}
        </>
      )}
    </GlassCard>
  );
}

export default GroupAuditLog;
