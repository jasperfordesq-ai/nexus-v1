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

import { useState, useCallback, useEffect, useMemo, useRef } from 'react';
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
  Skeleton,
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
import { formatRelativeTime } from '@/lib/helpers';
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

// Event type i18n keys — labels resolved at render time via t()
const EVENT_TYPE_I18N_KEYS: Record<string, string> = {
  cross_tenant_message: 'federation.event_type_message_sent',
  api_message_sent: 'federation.event_type_message_sent',
  cross_tenant_transaction: 'federation.event_type_transaction',
  api_transaction_initiated: 'federation.event_type_transaction',
  connection_request: 'federation.event_type_connection',
  listing_federated: 'federation.event_type_listing_shared',
  listing_unfederated: 'federation.event_type_listing_unshared',
  listing_viewed: 'federation.event_type_listing_viewed',
  partnership_requested: 'federation.event_type_partnership_requested',
  partnership_approved: 'federation.event_type_partnership_approved',
  partnership_rejected: 'federation.event_type_partnership_rejected',
  partnership_status_changed: 'federation.event_type_partnership_changed',
  partnership_revoked: 'federation.event_type_partnership_terminated',
  cross_tenant_profile_view: 'federation.event_type_profile_viewed',
  federated_search: 'federation.event_type_federated_search',
};

const EVENT_TYPE_STYLES: Record<string, Omit<EventTypeConfig, 'label'>> = {
  cross_tenant_message: { icon: Mail, color: 'primary', bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' },
  api_message_sent: { icon: Mail, color: 'primary', bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' },
  cross_tenant_transaction: { icon: CreditCard, color: 'success', bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400' },
  api_transaction_initiated: { icon: CreditCard, color: 'success', bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400' },
  connection_request: { icon: UserPlus, color: 'secondary', bgClass: 'bg-secondary-100 text-secondary-600 dark:bg-secondary-900/30 dark:text-secondary-400' },
  listing_federated: { icon: Package, color: 'warning', bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400' },
  listing_unfederated: { icon: Package, color: 'warning', bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400' },
  listing_viewed: { icon: Package, color: 'warning', bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400' },
  partnership_requested: { icon: Handshake, color: 'primary', bgClass: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' },
  partnership_approved: { icon: Handshake, color: 'success', bgClass: 'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400' },
  partnership_rejected: { icon: Handshake, color: 'danger', bgClass: 'bg-danger-100 text-danger-600 dark:bg-danger-900/30 dark:text-danger-400' },
  partnership_status_changed: { icon: Handshake, color: 'warning', bgClass: 'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400' },
  partnership_revoked: { icon: Handshake, color: 'danger', bgClass: 'bg-danger-100 text-danger-600 dark:bg-danger-900/30 dark:text-danger-400' },
  cross_tenant_profile_view: { icon: Eye, color: 'default', bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400' },
  federated_search: { icon: Search, color: 'default', bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400' },
};

// Filter option i18n keys — resolved at render time via t()
const EVENT_TYPE_OPTION_KEYS = [
  { key: 'cross_tenant_message', i18nKey: 'federation.filter_messages' },
  { key: 'cross_tenant_transaction', i18nKey: 'federation.filter_transactions' },
  { key: 'connection_request', i18nKey: 'federation.filter_connections' },
  { key: 'listing_federated', i18nKey: 'federation.filter_listings' },
  { key: 'partnership_requested', i18nKey: 'federation.filter_partnership_requests' },
  { key: 'partnership_approved', i18nKey: 'federation.filter_partnership_approved' },
  { key: 'partnership_status_changed', i18nKey: 'federation.filter_partnership_changes' },
  { key: 'cross_tenant_profile_view', i18nKey: 'federation.filter_profile_views' },
  { key: 'federated_search', i18nKey: 'federation.filter_searches' },
];

function getEventConfig(type: string, t: (key: string, defaultValue?: string) => string): EventTypeConfig {
  const i18nKey = EVENT_TYPE_I18N_KEYS[type];
  const style = EVENT_TYPE_STYLES[type];
  if (style && i18nKey) {
    return {
      label: t(i18nKey),
      ...style,
    };
  }
  return {
    label: type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
    icon: Activity,
    color: 'default' as const,
    bgClass: 'bg-default-100 text-default-600 dark:bg-default-800 dark:text-default-400',
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Timeline Item Component
// ─────────────────────────────────────────────────────────────────────────────

function TimelineItem({ item }: { item: ActivityItem }) {
  const { t } = useTranslation('admin');
  const config = getEventConfig(item.type, ((key: string) => t(key)) as (key: string, defaultValue?: string) => string);
  const Icon = config.icon;

  const absoluteTime = new Date(item.timestamp).toLocaleString();

  // Build description string
  let desc = item.description;
  if (item.actor_name && item.partner_tenant_name) {
    desc = `${item.actor_name} - ${item.description}`;
    if (item.direction === 'inbound') {
      desc += ` (${t('federation.direction_from', { name: item.partner_tenant_name })})`;
    } else {
      desc += ` (${t('federation.direction_to', { name: item.partner_tenant_name })})`;
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
            {item.direction === 'inbound' ? t('federation.inbound') : t('federation.outbound')}
          </Chip>
          {item.level === 'critical' && (
            <Chip size="sm" variant="flat" color="danger">
              {t('federation.level_critical')}
            </Chip>
          )}
          {item.level === 'warning' && (
            <Chip size="sm" variant="flat" color="warning">
              {t('federation.level_warning')}
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
            {formatRelativeTime(item.timestamp)}
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
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [selectedTypes, setSelectedTypes] = useState<Set<string>>(new Set());
  const [partnerFilter, setPartnerFilter] = useState('');

  // Partner tenant list (derived from results) — use ref to avoid infinite loop
  const knownPartnersRef = useRef(new Map<number, { id: number; name: string }>());
  const [knownPartners, setKnownPartners] = useState<
    Array<{ id: number; name: string }>
  >([]);

  // AbortController for cancelling in-flight requests
  const abortControllerRef = useRef<AbortController | null>(null);

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const eventTypeParam = useMemo(() => {
    if (selectedTypes.size === 0) return undefined;
    return Array.from(selectedTypes).join(',');
  }, [selectedTypes]);

  const loadItems = useCallback(
    async (append = false, cursor?: string | null) => {
      // Abort any in-flight request
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
      const controller = new AbortController();
      abortControllerRef.current = controller;

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
          search: debouncedSearch || undefined,
        });

        // If a newer request was started, discard this result
        if (abortControllerRef.current !== controller) return;

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

          // Track known partner communities for filter dropdown (using ref)
          for (const item of newItems) {
            if (item.partner_tenant_id && item.partner_tenant_name) {
              knownPartnersRef.current.set(item.partner_tenant_id, {
                id: item.partner_tenant_id,
                name: item.partner_tenant_name,
              });
            }
          }
          setKnownPartners(Array.from(knownPartnersRef.current.values()));
        }
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') return;
        if (!append) {
          setItems([]);
          setTotal(0);
          setHasMore(false);
        }
      }

      setLoading(false);
      setLoadingMore(false);
    },
    [eventTypeParam, partnerFilter, dateFrom, dateTo, debouncedSearch],
  );

  // Initial load and filter changes
  useEffect(() => {
    loadItems(false);
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reload on filter change; loadItems excluded to avoid loop
  }, [eventTypeParam, partnerFilter, dateFrom, dateTo, debouncedSearch]);

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
  // NOTE: This only exports the currently loaded items, not the full server-side dataset.
  // To export all items, the user would need to load all pages first.
  const exportCsv = () => {
    if (items.length === 0) return;
    const headers = [
      t('federation.csv_id'),
      t('federation.csv_timestamp'),
      t('federation.csv_type'),
      t('federation.csv_category'),
      t('federation.csv_level'),
      t('federation.csv_direction'),
      t('federation.csv_description'),
      t('federation.csv_actor'),
      t('federation.csv_partner_community'),
    ];
    const csvEscape = (val: string | number | null | undefined): string => {
      const s = String(val ?? '');
      if (s.includes(',') || s.includes('"') || s.includes('\n')) {
        return `"${s.replace(/"/g, '""')}"`;
      }
      return s;
    };
    const rows = items.map((item) => [
      item.id,
      csvEscape(item.timestamp),
      csvEscape(item.type),
      csvEscape(item.category),
      csvEscape(item.level),
      csvEscape(item.direction),
      csvEscape(item.description),
      csvEscape(item.actor_name),
      csvEscape(item.partner_tenant_name),
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
            <div className="flex flex-col items-end gap-0.5">
              <Button
                variant="flat"
                size="sm"
                startContent={<Download size={16} />}
                onPress={exportCsv}
                isDisabled={items.length === 0}
              >
                {t('federation.export_csv', 'Export CSV')}
              </Button>
              <span className="text-xs text-default-400">
                {t('federation.export_loaded_only', 'Exports currently loaded items')}
              </span>
            </div>
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
          label={t('federation.label_messages_in_view', 'Messages (in view)')}
          value={statsMessages}
          icon={Mail}
          color="primary"
          loading={loading && total === 0}
        />
        <StatCard
          label={t('federation.label_transactions_in_view', 'Transactions (in view)')}
          value={statsTransactions}
          icon={CreditCard}
          color="success"
          loading={loading && total === 0}
        />
        <StatCard
          label={t('federation.label_partnership_events_in_view', 'Partnership Events (in view)')}
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
              placeholder={t('federation.search_placeholder', 'User name, description...')}
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
                {t('federation.clear', 'Clear')}
              </Button>
            )}
          </div>

          {/* Event type checkboxes */}
          <div className="flex flex-wrap gap-3 mt-3">
            <span className="text-xs text-default-500 self-center">{t('federation.event_types_label', 'Event types:')}</span>
            {EVENT_TYPE_OPTION_KEYS.map((opt) => (
              <Checkbox
                key={opt.key}
                size="sm"
                isSelected={selectedTypes.has(opt.key)}
                onValueChange={() => toggleEventType(opt.key)}
              >
                {t(opt.i18nKey)}
              </Checkbox>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Timeline */}
      {loading && items.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="space-y-4 py-4">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex items-start gap-3 p-3">
                <Skeleton className="h-10 w-10 rounded-full shrink-0" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-3/4 rounded-lg" />
                  <Skeleton className="h-3 w-1/2 rounded-lg" />
                </div>
                <Skeleton className="h-3 w-16 rounded-lg" />
              </div>
            ))}
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
