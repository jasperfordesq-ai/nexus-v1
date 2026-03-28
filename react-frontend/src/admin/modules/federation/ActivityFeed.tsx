// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Activity Feed
 * Rich timeline view of all cross-tenant federation activity for the current tenant.
 * Supports filtering by event type, date range, partner community, and search.
 * Uses cursor-based pagination with "Load more" and CSV export.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Card,
  CardBody,
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Checkbox,
  Tooltip,
  Spinner,
} from '@heroui/react';
import {
  Mail,
  CreditCard,
  UserPlus,
  Package,
  Handshake,
  Eye,
  Download,
  Search,
  X,
  ArrowDown,
  Activity,
  RefreshCw,
  Inbox,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ActivityItem {
  id: number;
  type: string;
  category: string;
  level: string;
  description: string;
  detail: string | null;
  actor_name: string | null;
  actor_user_id: number | null;
  direction: 'inbound' | 'outbound';
  partner_tenant_id: number | null;
  partner_tenant_name: string | null;
  partner_tenant_slug: string | null;
  timestamp: string;
  data: Record<string, unknown>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Event type configuration
// ─────────────────────────────────────────────────────────────────────────────

interface EventTypeConfig {
  label: string;
  icon: typeof Mail;
  color: 'primary' | 'success' | 'secondary' | 'warning' | 'danger' | 'default';
  bgClass: string;
}

const EVENT_TYPE_MAP: Record<string, EventTypeConfig> = {
  cross_tenant_message: {
    label: 'Message Sent',
    icon: Mail,
    color: 'primary',
    bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400',
  },
  api_message_sent: {
    label: 'Message Sent',
    icon: Mail,
    color: 'primary',
    bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400',
  },
  cross_tenant_transaction: {
    label: 'Transaction',
    icon: CreditCard,
    color: 'success',
    bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400',
  },
  api_transaction_initiated: {
    label: 'Transaction',
    icon: CreditCard,
    color: 'success',
    bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400',
  },
  connection_request: {
    label: 'Connection',
    icon: UserPlus,
    color: 'secondary',
    bgClass: 'bg-secondary-100 text-secondary-600 dark:bg-secondary-900/30 dark:text-secondary-400',
  },
  listing_federated: {
    label: 'Listing Shared',
    icon: Package,
    color: 'warning',
    bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400',
  },
  listing_unfederated: {
    label: 'Listing Unshared',
    icon: Package,
    color: 'warning',
    bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400',
  },
  listing_viewed: {
    label: 'Listing Viewed',
    icon: Package,
    color: 'warning',
    bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400',
  },
  partnership_requested: {
    label: 'Partnership Requested',
    icon: Handshake,
    color: 'primary',
    bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400',
  },
  partnership_approved: {
    label: 'Partnership Approved',
    icon: Handshake,
    color: 'success',
    bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400',
  },
  partnership_rejected: {
    label: 'Partnership Rejected',
    icon: Handshake,
    color: 'danger',
    bgClass: 'bg-danger-100 text-danger-600 dark:bg-danger-900/30 dark:text-danger-400',
  },
  partnership_status_changed: {
    label: 'Partnership Changed',
    icon: Handshake,
    color: 'warning',
    bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400',
  },
  partnership_revoked: {
    label: 'Partnership Terminated',
    icon: Handshake,
    color: 'danger',
    bgClass: 'bg-danger-100 text-danger-600 dark:bg-danger-900/30 dark:text-danger-400',
  },
  cross_tenant_profile_view: {
    label: 'Profile Viewed',
    icon: Eye,
    color: 'default',
    bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400',
  },
  federated_search: {
    label: 'Federated Search',
    icon: Search,
    color: 'default',
    bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400',
  },
};

const EVENT_TYPE_OPTIONS = [
  { key: 'cross_tenant_message', label: 'Messages' },
  { key: 'cross_tenant_transaction', label: 'Transactions' },
  { key: 'connection_request', label: 'Connections' },
  { key: 'listing_federated', label: 'Listings' },
  { key: 'partnership_requested', label: 'Partnership Requests' },
  { key: 'partnership_approved', label: 'Partnership Approved' },
  { key: 'partnership_status_changed', label: 'Partnership Changes' },
  { key: 'cross_tenant_profile_view', label: 'Profile Views' },
  { key: 'federated_search', label: 'Searches' },
];

function getEventConfig(type: string): EventTypeConfig {
  return (
    EVENT_TYPE_MAP[type] ?? {
      label: type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
      icon: Activity,
      color: 'default' as const,
      bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400',
    }
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Relative time helper
// ─────────────────────────────────────────────────────────────────────────────

function relativeTime(dateStr: string): string {
  const now = Date.now();
  const then = new Date(dateStr).getTime();
  const diffMs = now - then;
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHour = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHour / 24);

  if (diffSec < 60) return 'just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  if (diffHour < 24) return `${diffHour}h ago`;
  if (diffDay < 7) return `${diffDay}d ago`;
  return new Date(dateStr).toLocaleDateString();
}

// ─────────────────────────────────────────────────────────────────────────────
// Timeline Item Component
// ─────────────────────────────────────────────────────────────────────────────

function TimelineItem({ item }: { item: ActivityItem }) {
  const config = getEventConfig(item.type);
  const Icon = config.icon;

  const absoluteTime = new Date(item.timestamp).toLocaleString();

  // Build description string
  let desc = item.description;
  if (item.actor_name && item.partner_tenant_name) {
    desc = `${item.actor_name} - ${item.description}`;
    if (item.direction === 'inbound') {
      desc += ` (from ${item.partner_tenant_name})`;
    } else {
      desc += ` (to ${item.partner_tenant_name})`;
    }
  } else if (item.actor_name) {
    desc = `${item.actor_name} - ${item.description}`;
  }

  return (
    <div className="relative flex gap-4 pb-6 last:pb-0">
      {/* Timeline line */}
      <div className="absolute left-5 top-10 bottom-0 w-px bg-default-200 dark:bg-default-700 last:hidden" />

      {/* Icon circle */}
      <div
        className={`relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${config.bgClass}`}
      >
        <Icon size={18} />
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0 pt-0.5">
        <div className="flex flex-wrap items-center gap-2 mb-1">
          <Chip size="sm" variant="flat" color={config.color}>
            {config.label}
          </Chip>
          <Chip
            size="sm"
            variant="flat"
            color={item.direction === 'inbound' ? 'primary' : 'secondary'}
          >
            {item.direction === 'inbound' ? 'Inbound' : 'Outbound'}
          </Chip>
          {item.level === 'critical' && (
            <Chip size="sm" variant="flat" color="danger">
              Critical
            </Chip>
          )}
          {item.level === 'warning' && (
            <Chip size="sm" variant="flat" color="warning">
              Warning
            </Chip>
          )}
        </div>

        <p className="text-sm text-default-700 dark:text-default-300">{desc}</p>

        {item.detail && (
          <p className="text-xs text-default-400 mt-0.5 truncate">{item.detail}</p>
        )}

        {item.partner_tenant_name && (
          <div className="flex items-center gap-1.5 mt-1">
            <div className="h-4 w-4 rounded-full bg-default-200 dark:bg-default-700 flex items-center justify-center">
              <span className="text-[9px] font-bold text-default-500">
                {item.partner_tenant_name.charAt(0).toUpperCase()}
              </span>
            </div>
            <span className="text-xs text-default-500">{item.partner_tenant_name}</span>
          </div>
        )}

        <Tooltip content={absoluteTime}>
          <span className="text-xs text-default-400 mt-1 inline-block cursor-default">
            {relativeTime(item.timestamp)}
          </span>
        </Tooltip>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function ActivityFeed() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));

  // Data
  const [items, setItems] = useState<ActivityItem[]>([]);
  const [total, setTotal] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);

  // Filters
  const [search, setSearch] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [selectedTypes, setSelectedTypes] = useState<Set<string>>(new Set());
  const [partnerFilter, setPartnerFilter] = useState('');

  // Partner tenant list (derived from results)
  const [knownPartners, setKnownPartners] = useState<
    Array<{ id: number; name: string }>
  >([]);

  const eventTypeParam = useMemo(() => {
    if (selectedTypes.size === 0) return undefined;
    return Array.from(selectedTypes).join(',');
  }, [selectedTypes]);

  const loadItems = useCallback(
    async (append = false, cursor?: string | null) => {
      if (append) {
        setLoadingMore(true);
      } else {
        setLoading(true);
      }

      try {
        const res = await adminFederation.getActivityFeed({
          limit: 25,
          cursor: cursor ?? undefined,
          event_type: eventTypeParam,
          partner_tenant_id: partnerFilter ? Number(partnerFilter) : undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
          search: search || undefined,
        });

        if (res.success && res.data) {
          const payload = res.data as unknown;
          let feedData: {
            items: ActivityItem[];
            total: number;
            has_more: boolean;
            next_cursor: string | null;
          };

          if (payload && typeof payload === 'object' && 'data' in payload) {
            feedData = (payload as { data: typeof feedData }).data;
          } else {
            feedData = payload as typeof feedData;
          }

          const newItems = feedData.items ?? [];

          if (append) {
            setItems((prev) => [...prev, ...newItems]);
          } else {
            setItems(newItems);
          }

          setTotal(feedData.total ?? 0);
          setHasMore(feedData.has_more ?? false);
          setNextCursor(feedData.next_cursor ?? null);

          // Track known partner communities for filter dropdown
          const newPartners = new Map(knownPartners.map((p) => [p.id, p]));
          for (const item of newItems) {
            if (item.partner_tenant_id && item.partner_tenant_name) {
              newPartners.set(item.partner_tenant_id, {
                id: item.partner_tenant_id,
                name: item.partner_tenant_name,
              });
            }
          }
          setKnownPartners(Array.from(newPartners.values()));
        }
      } catch {
        if (!append) {
          setItems([]);
          setTotal(0);
          setHasMore(false);
        }
      }

      setLoading(false);
      setLoadingMore(false);
    },
    [eventTypeParam, partnerFilter, dateFrom, dateTo, search, knownPartners],
  );

  // Initial load and filter changes
  useEffect(() => {
    loadItems(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [eventTypeParam, partnerFilter, dateFrom, dateTo, search]);

  const handleLoadMore = () => {
    if (hasMore && nextCursor) {
      loadItems(true, nextCursor);
    }
  };

  const clearFilters = () => {
    setSearch('');
    setDateFrom('');
    setDateTo('');
    setSelectedTypes(new Set());
    setPartnerFilter('');
  };

  const hasFilters = !!(
    search ||
    dateFrom ||
    dateTo ||
    selectedTypes.size > 0 ||
    partnerFilter
  );

  const toggleEventType = (key: string) => {
    setSelectedTypes((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  };

  // CSV export
  const exportCsv = () => {
    if (items.length === 0) return;
    const headers = [
      'ID',
      'Timestamp',
      'Type',
      'Category',
      'Level',
      'Direction',
      'Description',
      'Actor',
      'Partner Community',
    ];
    const rows = items.map((item) => [
      item.id,
      item.timestamp,
      item.type,
      item.category,
      item.level,
      item.direction,
      `"${(item.description || '').replace(/"/g, '""')}"`,
      item.actor_name ?? '',
      item.partner_tenant_name ?? '',
    ]);
    const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `federation-activity-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  // Stats derived from total
  const statsMessages = items.filter(
    (i) => i.type === 'cross_tenant_message' || i.type === 'api_message_sent',
  ).length;
  const statsTransactions = items.filter(
    (i) =>
      i.type === 'cross_tenant_transaction' || i.type === 'api_transaction_initiated',
  ).length;
  const statsPartnerships = items.filter((i) =>
    i.type.startsWith('partnership_'),
  ).length;

  return (
    <div>
      <PageHeader
        title={t('federation.activity_feed_title', 'Federation Activity Feed')}
        description={t(
          'federation.activity_feed_desc',
          'Cross-tenant federation activity for your community',
        )}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={() => loadItems(false)}
              isLoading={loading}
            >
              {t('federation.refresh', 'Refresh')}
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<Download size={16} />}
              onPress={exportCsv}
              isDisabled={items.length === 0}
            >
              Export CSV
            </Button>
          </div>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label={t('federation.label_total_activity', 'Total Activity')}
          value={total}
          icon={Activity}
          color="primary"
          loading={loading && total === 0}
        />
        <StatCard
          label={t('federation.label_messages', 'Messages')}
          value={statsMessages}
          icon={Mail}
          color="primary"
          loading={loading && total === 0}
        />
        <StatCard
          label={t('federation.label_transactions', 'Transactions')}
          value={statsTransactions}
          icon={CreditCard}
          color="success"
          loading={loading && total === 0}
        />
        <StatCard
          label={t('federation.label_partnership_events', 'Partnership Events')}
          value={statsPartnerships}
          icon={Handshake}
          color="secondary"
          loading={loading && total === 0}
        />
      </div>

      {/* Filters */}
      <Card shadow="sm" className="mb-6">
        <CardBody>
          <div className="flex flex-wrap gap-3 items-end">
            <Input
              label={t('federation.label_search', 'Search')}
              placeholder="User name, description..."
              size="sm"
              className="max-w-[220px]"
              value={search}
              onValueChange={setSearch}
              startContent={<Search size={14} className="text-default-400" />}
              isClearable
              onClear={() => setSearch('')}
            />

            <Input
              label={t('federation.label_from_date', 'From')}
              type="date"
              size="sm"
              className="max-w-[170px]"
              value={dateFrom}
              onValueChange={setDateFrom}
            />

            <Input
              label={t('federation.label_to_date', 'To')}
              type="date"
              size="sm"
              className="max-w-[170px]"
              value={dateTo}
              onValueChange={setDateTo}
            />

            {knownPartners.length > 0 && (
              <Select
                label={t('federation.label_partner_community', 'Partner Community')}
                size="sm"
                className="max-w-[220px]"
                selectedKeys={partnerFilter ? [partnerFilter] : []}
                onSelectionChange={(keys) => {
                  setPartnerFilter(String(Array.from(keys)[0] || ''));
                }}
              >
                {knownPartners.map((p) => (
                  <SelectItem key={String(p.id)}>{p.name}</SelectItem>
                ))}
              </Select>
            )}

            {hasFilters && (
              <Button
                size="sm"
                variant="light"
                color="danger"
                startContent={<X size={14} />}
                onPress={clearFilters}
              >
                Clear
              </Button>
            )}
          </div>

          {/* Event type checkboxes */}
          <div className="flex flex-wrap gap-3 mt-3">
            <span className="text-xs text-default-500 self-center">Event types:</span>
            {EVENT_TYPE_OPTIONS.map((opt) => (
              <Checkbox
                key={opt.key}
                size="sm"
                isSelected={selectedTypes.has(opt.key)}
                onValueChange={() => toggleEventType(opt.key)}
              >
                {opt.label}
              </Checkbox>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Timeline */}
      {loading && items.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex items-center justify-center py-16">
            <Spinner size="lg" />
          </CardBody>
        </Card>
      ) : items.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-16 text-center">
            <Inbox size={48} className="text-default-300 mb-3" />
            <p className="text-default-500 text-lg font-medium mb-1">
              {t('federation.no_activity_title', 'No federation activity yet')}
            </p>
            <p className="text-default-400 text-sm">
              {t(
                'federation.no_activity_desc',
                'Activity will appear here once your community interacts with federation partners.',
              )}
            </p>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody className="p-6">
            {items.map((item) => (
              <TimelineItem key={item.id} item={item} />
            ))}

            {hasMore && (
              <div className="flex justify-center mt-6 pt-4 border-t border-default-100">
                <Button
                  variant="flat"
                  startContent={<ArrowDown size={16} />}
                  onPress={handleLoadMore}
                  isLoading={loadingMore}
                >
                  {t('federation.load_more', 'Load more')}
                </Button>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default ActivityFeed;
