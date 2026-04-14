// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Message Review Page
 * Review flagged and unreviewed messages requiring broker oversight.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Tabs, Tab, Chip, Button } from '@heroui/react';
import { RefreshCw, MessageSquare, Eye } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '@/admin/components';
import type { BrokerMessage } from '@/admin/api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Severity chip color mapping
// ─────────────────────────────────────────────────────────────────────────────

type ChipColor = 'default' | 'warning' | 'danger';
type ChipVariant = 'flat' | 'solid';

function severityColor(severity?: string): { color: ChipColor; variant: ChipVariant } {
  switch (severity?.toLowerCase()) {
    case 'medium':
      return { color: 'warning', variant: 'flat' };
    case 'high':
      return { color: 'danger', variant: 'flat' };
    case 'critical':
      return { color: 'danger', variant: 'solid' };
    default:
      return { color: 'default', variant: 'flat' };
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Filter key to API param mapping
// ─────────────────────────────────────────────────────────────────────────────

function filterParam(tab: string): string | undefined {
  if (tab === 'unreviewed') return 'unreviewed';
  if (tab === 'flagged') return 'flagged';
  return undefined; // "all" sends no filter
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function MessageReviewPage() {
  const { t } = useTranslation('broker');
  usePageTitle(t('messages.title'));
  const toast = useToast();

  // Data state
  const [items, setItems] = useState<BrokerMessage[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<string>('unreviewed');
  const [loading, setLoading] = useState(true);

  // Action state
  const [reviewLoading, setReviewLoading] = useState<number | null>(null);

  // ── Data fetching ─────────────────────────────────────────────────────────

  const loadMessages = useCallback(async () => {
    setLoading(true);
    try {
      const params: { page?: number; filter?: string } = { page };
      const f = filterParam(filter);
      if (f) params.filter = f;

      const res = await adminBroker.getMessages(params);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: BrokerMessage[]; meta?: { total: number } };
          setItems(paged.data || []);
          setTotal(paged.meta?.total ?? 0);
        }
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [page, filter, toast, t]);

  useEffect(() => {
    loadMessages();
  }, [loadMessages]);

  // ── Tab change ────────────────────────────────────────────────────────────

  const handleTabChange = useCallback((key: React.Key) => {
    setFilter(String(key));
    setPage(1);
  }, []);

  // ── Mark reviewed ─────────────────────────────────────────────────────────

  const handleMarkReviewed = useCallback(
    async (id: number) => {
      setReviewLoading(id);
      try {
        const res = await adminBroker.reviewMessage(id);
        if (res?.success) {
          toast.success(t('messages.reviewed_success'));
          loadMessages();
        } else {
          toast.error(t('common.error'));
        }
      } catch {
        toast.error(t('common.error'));
      } finally {
        setReviewLoading(null);
      }
    },
    [loadMessages, toast, t],
  );

  // ── Truncate helper ───────────────────────────────────────────────────────

  const truncate = (text: string | undefined, max = 60) => {
    if (!text) return '--';
    return text.length > max ? text.slice(0, max) + '...' : text;
  };

  // ── Columns ───────────────────────────────────────────────────────────────

  const columns: Column<BrokerMessage>[] = useMemo(
    () => [
      {
        key: 'sender_name',
        label: t('messages.col_sender'),
        sortable: true,
        render: (item) => (
          <span className="font-medium text-foreground">{item.sender_name}</span>
        ),
      },
      {
        key: 'receiver_name',
        label: t('messages.col_receiver'),
        sortable: true,
        render: (item) => (
          <span className="text-sm text-default-600">{item.receiver_name}</span>
        ),
      },
      {
        key: 'message_body',
        label: t('messages.col_preview'),
        render: (item) => (
          <span className="text-sm text-default-600">
            {truncate(item.message_body)}
          </span>
        ),
      },
      {
        key: 'copy_reason',
        label: t('messages.col_reason'),
        render: (item) => (
          <span className="text-sm text-default-500">
            {item.copy_reason || item.flag_reason || '--'}
          </span>
        ),
      },
      {
        key: 'flag_severity',
        label: t('messages.col_severity'),
        render: (item) => {
          if (!item.flag_severity) return <span className="text-default-400">--</span>;
          const { color, variant } = severityColor(item.flag_severity);
          return (
            <Chip size="sm" variant={variant} color={color} className="capitalize">
              {item.flag_severity}
            </Chip>
          );
        },
      },
      {
        key: 'created_at',
        label: t('messages.col_date'),
        sortable: true,
        render: (item) => (
          <span className="text-sm text-default-500">
            {item.created_at
              ? new Date(item.created_at).toLocaleDateString()
              : '--'}
          </span>
        ),
      },
      {
        key: 'actions',
        label: t('messages.col_actions'),
        render: (item) =>
          item.reviewed_at ? (
            <Chip size="sm" variant="flat" color="success" className="capitalize">
              {t('status.reviewed')}
            </Chip>
          ) : (
            <Button
              size="sm"
              variant="flat"
              color="primary"
              startContent={<Eye size={14} />}
              isLoading={reviewLoading === item.id}
              isDisabled={reviewLoading !== null}
              onPress={() => handleMarkReviewed(item.id)}
            >
              {t('messages.mark_reviewed')}
            </Button>
          ),
      },
    ],
    [t, reviewLoading, handleMarkReviewed],
  );

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('messages.title')}
        description={t('messages.description')}
        actions={
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            onPress={loadMessages}
            aria-label={t('common.refresh')}
          >
            <RefreshCw size={16} />
          </Button>
        }
      />

      {/* Filter tabs */}
      <Tabs
        aria-label="Message filter"
        selectedKey={filter}
        onSelectionChange={handleTabChange}
        className="mb-4"
      >
        <Tab key="unreviewed" title={t('messages.filter_unreviewed')} />
        <Tab key="all" title={t('messages.filter_all')} />
        <Tab key="flagged" title={t('messages.filter_flagged')} />
      </Tabs>

      {/* Data table */}
      {!loading && items.length === 0 ? (
        <EmptyState
          icon={MessageSquare}
          title={t('messages.no_messages')}
          description={t('messages.description')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          totalItems={total}
          page={page}
          pageSize={20}
          onPageChange={setPage}
          onRefresh={loadMessages}
        />
      )}
    </div>
  );
}
