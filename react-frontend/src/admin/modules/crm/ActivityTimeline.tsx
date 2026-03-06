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
  Card, CardBody, Button, Input, Select, SelectItem,
  Chip, Spinner, Pagination, Avatar,
} from '@heroui/react';
import {
  Activity, Search, Filter, User, StickyNote, ClipboardList,
  LogIn, UserPlus, FileText, ArrowRightLeft, RefreshCw,
} from 'lucide-react';
import { useSearchParams, Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { PageHeader } from '../../components';

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

const ACTIVITY_TYPES = [
  { key: 'login', label: 'Login' },
  { key: 'signup', label: 'Signup' },
  { key: 'listing_created', label: 'Listing Created' },
  { key: 'exchange_completed', label: 'Exchange Completed' },
  { key: 'note_added', label: 'Note Added' },
  { key: 'task_created', label: 'Task Created' },
  { key: 'profile_updated', label: 'Profile Updated' },
  { key: 'group_joined', label: 'Group Joined' },
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

const DATE_RANGE_OPTIONS = [
  { key: '7', label: 'Last 7 days' },
  { key: '30', label: 'Last 30 days' },
  { key: '90', label: 'Last 90 days' },
  { key: '', label: 'All time' },
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

function getActivityLabel(type: string): string {
  const found = ACTIVITY_TYPES.find(t => t.key === type);
  return found ? found.label : type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
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

function formatRelativeTime(dateStr: string): string {
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return formatDateTime(dateStr);
}

export function ActivityTimeline() {
  usePageTitle('Admin - Activity Timeline');
  const { tenantPath } = useTenant();
  const [searchParams] = useSearchParams();

  // State
  const [entries, setEntries] = useState<TimelineEntry[]>([]);
  const [meta, setMeta] = useState<TimelineMeta>({ total: 0, page: 1, limit: ITEMS_PER_PAGE, pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filterUserId, setFilterUserId] = useState<string>(searchParams.get('user_id') || '');
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
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object') {
          const p = payload as { data?: TimelineEntry[]; meta?: TimelineMeta };
          setEntries(p.data || []);
          if (p.meta) setMeta(p.meta);
        }
      }
    } catch {
      setEntries([]);
    }
    setLoading(false);
  }, [page, filterUserId, filterType, filterDays]);

  useEffect(() => { loadTimeline(); }, [loadTimeline]);

  const handleClearFilters = () => {
    setFilterUserId('');
    setFilterType('');
    setFilterDays('30');
    setPage(1);
  };

  const hasActiveFilters = filterUserId || filterType || filterDays !== '30';

  return (
    <div className="max-w-6xl mx-auto">
      <PageHeader
        title="Activity Timeline"
        description="Chronological log of member activities across the platform"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => loadTimeline()}
            isDisabled={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3 mb-6">
        <Input
          label="User ID"
          placeholder="Filter by user ID"
          className="w-40"
          size="sm"
          type="number"
          startContent={<Search size={14} />}
          value={filterUserId}
          onValueChange={(val) => {
            setFilterUserId(val);
            setPage(1);
          }}
        />

        <Select
          label="Activity Type"
          placeholder="All types"
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
          {ACTIVITY_TYPES.map(t => (
            <SelectItem key={t.key}>{t.label}</SelectItem>
          ))}
        </Select>

        <Select
          label="Date Range"
          className="w-44"
          size="sm"
          selectedKeys={[filterDays]}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string ?? '';
            setFilterDays(val);
            setPage(1);
          }}
        >
          {DATE_RANGE_OPTIONS.map(opt => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>

        {hasActiveFilters && (
          <Button
            size="sm"
            variant="flat"
            onPress={handleClearFilters}
          >
            Clear Filters
          </Button>
        )}
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label="Loading activity..." />
        </div>
      ) : entries.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center py-16 text-center">
            <Activity size={48} className="text-default-300 mb-4" />
            <p className="text-default-500 text-lg font-medium">No activity found</p>
            <p className="text-default-400 text-sm mt-1">
              {hasActiveFilters
                ? 'Try adjusting your filters or expanding the date range'
                : 'Member activity will appear here as it happens'}
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
                            {formatRelativeTime(entry.created_at)}
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
