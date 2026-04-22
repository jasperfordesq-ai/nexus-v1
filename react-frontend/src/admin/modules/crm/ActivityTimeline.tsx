// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Activity Timeline
 * Unified chronological log of member activities for the CRM module.
 * Supports per-member filtering, activity type filtering, and date range selection.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, Button, Select, SelectItem,
  Chip, Spinner, Pagination, Avatar,
} from '@heroui/react';
import Activity from 'lucide-react/icons/activity';
import Filter from 'lucide-react/icons/filter';
import User from 'lucide-react/icons/user';
import StickyNote from 'lucide-react/icons/sticky-note';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import LogIn from 'lucide-react/icons/log-in';
import UserPlus from 'lucide-react/icons/user-plus';
import FileText from 'lucide-react/icons/file-text';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useSearchParams, Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { PageHeader, MemberSearchPicker, type MemberSearchMember } from '../../components';

import { useTranslation } from 'react-i18next';
interface TimelineEntry {
  id: number;
  user_id: number;
  user_name: string;
  user_avatar: string | null;
  activity_type: string;
  description: string;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

interface TimelineMeta {
  total: number;
  page: number;
  limit: number;
  pages: number;
}

const ACTIVITY_TYPE_KEYS = [
  'login', 'signup', 'listing_created', 'exchange_completed',
  'note_added', 'task_created', 'profile_updated', 'group_joined',
] as const;


const ACTIVITY_COLOR_MAP: Record<string, 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'secondary'> = {
  login: 'primary',
  signup: 'success',
  listing_created: 'warning',
  exchange_completed: 'success',
  note_added: 'secondary',
  task_created: 'default',
  profile_updated: 'primary',
  group_joined: 'warning',
};

const ACTIVITY_DOT_COLOR_MAP: Record<string, string> = {
  login: 'bg-primary',
  signup: 'bg-success',
  listing_created: 'bg-warning',
  exchange_completed: 'bg-success',
  note_added: 'bg-secondary',
  task_created: 'bg-default-400',
  profile_updated: 'bg-primary',
  group_joined: 'bg-warning',
};

const DATE_RANGE_KEYS = [
  { key: '7', labelKey: 'crm.date_range_7' },
  { key: '30', labelKey: 'crm.date_range_30' },
  { key: '90', labelKey: 'crm.date_range_90' },
  { key: '', labelKey: 'crm.date_range_all' },
] as const;

const ITEMS_PER_PAGE = 25;

function getActivityIcon(type: string) {
  switch (type) {
    case 'login':
      return <LogIn size={16} />;
    case 'signup':
      return <UserPlus size={16} />;
    case 'listing_created':
      return <FileText size={16} />;
    case 'exchange_completed':
      return <ArrowRightLeft size={16} />;
    case 'note_added':
      return <StickyNote size={16} />;
    case 'task_created':
      return <ClipboardList size={16} />;
    case 'profile_updated':
      return <User size={16} />;
    case 'group_joined':
      return <Activity size={16} />;
    default:
      return <Activity size={16} />;
  }
}

function formatDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatRelativeTime(dateStr: string, t: (key: string, opts?: Record<string, unknown>) => string): string {
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return "Just Now";
  if (diffMins < 60) return `Minutes Ago`;
  if (diffHours < 24) return `Hours Ago`;
  if (diffDays < 7) return `Days Ago`;
  return formatDateTime(dateStr);
}

export function ActivityTimeline() {
  const { t } = useTranslation('admin');
  usePageTitle("CRM");
  const { tenantPath } = useTenant();
  const [searchParams] = useSearchParams();

  const getActivityLabel = (type: string): string => {
    const key = `crm.activity_type_${type}`;
    const translated = t(key);
    return translated !== key ? translated : type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  };

  // State
  const [entries, setEntries] = useState<TimelineEntry[]>([]);
  const [meta, setMeta] = useState<TimelineMeta>({ total: 0, page: 1, limit: ITEMS_PER_PAGE, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filterUserId, setFilterUserId] = useState<string>(searchParams.get('user_id') || '');
  const [filterMember, setFilterMember] = useState<MemberSearchMember | null>(null);
  const [filterType, setFilterType] = useState<string>('');
  const [filterDays, setFilterDays] = useState<string>('30');

  // Data loading
  const loadTimeline = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminCrm.getTimeline({
        page,
        limit: ITEMS_PER_PAGE,
        user_id: filterUserId ? Number(filterUserId) : undefined,
        type: filterType || undefined,
        days: filterDays ? Number(filterDays) : undefined,
      });
      if (res.success) {
        setEntries(Array.isArray(res.data) ? res.data as TimelineEntry[] : []);
        setMeta({
          total: res.meta?.total || 0,
          page: res.meta?.current_page || 1,
          limit: res.meta?.per_page || ITEMS_PER_PAGE,
          pages: res.meta?.total_pages || 1,
        });
      }
    } catch {
      setEntries([]);
    }
    setLoading(false);
  }, [page, filterUserId, filterType, filterDays]);

  useEffect(() => { loadTimeline(); }, [loadTimeline]);

  const handleClearFilters = () => {
    setFilterUserId('');
    setFilterMember(null);
    setFilterType('');
    setFilterDays('30');
    setPage(1);
  };

  const hasActiveFilters = filterUserId || filterType || filterDays !== '30';

  return (
    <div className="max-w-6xl mx-auto">
      <PageHeader
        title={"Activity Timeline"}
        description={"Chronological timeline of member actions and coordinator interactions"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => loadTimeline()}
            isDisabled={loading}
          >
            {"Refresh"}
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3 mb-6">
        <MemberSearchPicker
          label={"Search Member"}
          placeholder={"Type a Name or Email to Search..."}
          noResultsText={"No members found found"}
          className="w-full sm:w-72"
          size="sm"
          value={filterUserId}
          selectedMember={filterMember}
          onSelectedMemberChange={setFilterMember}
          onValueChange={(val) => {
            setFilterUserId(val);
            setPage(1);
          }}
        />

        <Select
          label={"Activity Type"}
          placeholder={"All Types..."}
          className="w-52"
          size="sm"
          startContent={<Filter size={14} />}
          selectedKeys={filterType ? [filterType] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string || '';
            setFilterType(val);
            setPage(1);
          }}
        >
          {ACTIVITY_TYPE_KEYS.map(key => (
            <SelectItem key={key}>{t(`crm.activity_type_${key}`)}</SelectItem>
          ))}
        </Select>

        <Select
          label={"Date Range"}
          className="w-44"
          size="sm"
          selectedKeys={[filterDays]}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string ?? '';
            setFilterDays(val);
            setPage(1);
          }}
        >
          {DATE_RANGE_KEYS.map(opt => (
            <SelectItem key={opt.key}>{t(opt.labelKey)}</SelectItem>
          ))}
        </Select>

        {hasActiveFilters && (
          <Button
            size="sm"
            variant="flat"
            onPress={handleClearFilters}
          >
            {"Clear Filters"}
          </Button>
        )}
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label={"Loading Activity"} />
        </div>
      ) : entries.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center py-16 text-center">
            <Activity size={48} className="text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">{"No activity found"}</p>
            <p className="text-default-400 text-sm mt-1">
              {hasActiveFilters
                ? "No activity matches your current filters"
                : "No activity has been recorded yet"}
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col">
          {/* Timeline entries */}
          <div className="relative">
            {entries.map((entry, index) => {
              const isLast = index === entries.length - 1;
              const dotColor = ACTIVITY_DOT_COLOR_MAP[entry.activity_type] || 'bg-default-400';
              const chipColor = ACTIVITY_COLOR_MAP[entry.activity_type] || 'default';

              return (
                <div key={entry.id} className="relative flex gap-4 pb-6">
                  {/* Left timeline column — dot + connector line */}
                  <div className="flex flex-col items-center shrink-0 w-8">
                    <div
                      className={`w-3 h-3 rounded-full mt-1.5 shrink-0 ring-4 ring-background ${dotColor}`}
                    />
                    {!isLast && (
                      <div className="w-px flex-1 bg-default-200 dark:bg-default-100 mt-1" />
                    )}
                  </div>

                  {/* Entry content */}
                  <Card className="flex-1 shadow-sm">
                    <CardBody className="p-4">
                      <div className="flex items-start justify-between gap-3">
                        {/* Left: user + activity */}
                        <div className="flex items-start gap-3 min-w-0 flex-1">
                          <Avatar
                            src={entry.user_avatar || undefined}
                            name={entry.user_name}
                            size="sm"
                            className="shrink-0 mt-0.5"
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <Link
                                to={tenantPath(`/admin/users/${entry.user_id}/edit`)}
                                className="font-semibold text-foreground hover:text-primary transition-colors"
                              >
                                {entry.user_name}
                              </Link>
                              <Chip
                                size="sm"
                                variant="flat"
                                color={chipColor}
                                startContent={
                                  <span className="ml-1">{getActivityIcon(entry.activity_type)}</span>
                                }
                              >
                                {getActivityLabel(entry.activity_type)}
                              </Chip>
                            </div>
                            <p className="text-sm text-default-600 mt-1">
                              {entry.description}
                            </p>
                            {entry.metadata && Object.keys(entry.metadata).length > 0 && (
                              <div className="flex flex-wrap gap-1.5 mt-2">
                                {Object.entries(entry.metadata).map(([key, value]) => (
                                  <Chip key={key} size="sm" variant="bordered" className="text-xs">
                                    {key}: {String(value)}
                                  </Chip>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>

                        {/* Right: timestamp */}
                        <div className="text-right shrink-0">
                          <p className="text-xs text-default-400 whitespace-nowrap" title={formatDateTime(entry.created_at)}>
                            {formatRelativeTime(entry.created_at, t)}
                          </p>
                          <p className="text-xs text-default-300 mt-0.5">
                            #{entry.user_id}
                          </p>
                        </div>
                      </div>
                    </CardBody>
                  </Card>
                </div>
              );
            })}
          </div>

          {/* Pagination */}
          {meta.pages > 1 && (
            <div className="flex justify-center mt-4">
              <Pagination
                total={meta.pages}
                page={page}
                onChange={setPage}
                showControls
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default ActivityTimeline;
