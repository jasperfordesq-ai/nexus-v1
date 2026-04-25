// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Message Review Page
 * Review flagged and unreviewed messages requiring broker oversight.
 * Click any row to view the full message content and mark it reviewed.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Tabs,
  Tab,
  Chip,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Divider,
  Textarea,
} from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MessageSquare from 'lucide-react/icons/message-square';
import Eye from 'lucide-react/icons/eye';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '@/admin/components';
import type { BrokerMessage, BrokerMessageDetail } from '@/admin/api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Severity chip colour mapping
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

  // List state
  const [items, setItems] = useState<BrokerMessage[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<string>('unreviewed');
  const [loading, setLoading] = useState(true);

  // Inline-review loading (row button)
  const [reviewLoading, setReviewLoading] = useState<number | null>(null);

  // Detail modal state
  const [detailItem, setDetailItem] = useState<BrokerMessage | null>(null);
  const [detail, setDetail] = useState<BrokerMessageDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailReviewNotes, setDetailReviewNotes] = useState('');
  const [detailReviewLoading, setDetailReviewLoading] = useState(false);

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

  // ── Inline mark-reviewed ──────────────────────────────────────────────────

  const handleMarkReviewed = useCallback(
    async (id: number, notes?: string) => {
      setReviewLoading(id);
      try {
        const res = await adminBroker.reviewMessage(id, notes);
        if (res?.success) {
          toast.success(t('messages.reviewed_success'));
          loadMessages();
          return true;
        } else {
          toast.error(t('common.error'));
          return false;
        }
      } catch {
        toast.error(t('common.error'));
        return false;
      } finally {
        setReviewLoading(null);
      }
    },
    [loadMessages, toast, t],
  );

  // ── Open detail modal ────────────────────────────────────────────────────

  const openDetail = useCallback(async (item: BrokerMessage) => {
    setDetailItem(item);
    setDetail(null);
    setDetailReviewNotes('');
    setDetailLoading(true);
    try {
      const res = await adminBroker.showMessage(item.id);
      if (res.success && res.data) {
        setDetail(res.data as BrokerMessageDetail);
      }
    } catch {
      // Show basic info from list item even if detail fetch fails
    } finally {
      setDetailLoading(false);
    }
  }, []);

  const closeDetail = useCallback(() => {
    setDetailItem(null);
    setDetail(null);
    setDetailReviewNotes('');
  }, []);

  // ── Mark reviewed from detail modal ──────────────────────────────────────

  const handleDetailReview = useCallback(async () => {
    if (!detailItem) return;
    setDetailReviewLoading(true);
    try {
      const res = await adminBroker.reviewMessage(detailItem.id, detailReviewNotes || undefined);
      if (res?.success) {
        toast.success(t('messages.reviewed_success'));
        closeDetail();
        loadMessages();
      } else {
        toast.error(t('common.error'));
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setDetailReviewLoading(false);
    }
  }, [detailItem, detailReviewNotes, closeDetail, loadMessages, toast, t]);

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
              {t(`status.${item.flag_severity}`, { defaultValue: item.flag_severity })}
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
            {item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}
          </span>
        ),
      },
      {
        key: 'actions',
        label: t('messages.col_actions'),
        render: (item) => (
          <div className="flex items-center gap-1.5">
            <Button
              size="sm"
              variant="flat"
              color="default"
              startContent={<Eye size={14} />}
              onPress={() => openDetail(item)}
            >
              {t('messages.view')}
            </Button>
            {!item.reviewed_at && (
              <Button
                size="sm"
                variant="flat"
                color="primary"
                isLoading={reviewLoading === item.id}
                isDisabled={reviewLoading !== null}
                onPress={() => handleMarkReviewed(item.id)}
              >
                {t('messages.mark_reviewed')}
              </Button>
            )}
            {item.reviewed_at && (
              <Chip size="sm" variant="flat" color="success">
                {t('status.reviewed')}
              </Chip>
            )}
          </div>
        ),
      },
    ],
    [t, reviewLoading, handleMarkReviewed, openDetail],
  );

  // ── Render ────────────────────────────────────────────────────────────────

  const isReviewed = !!(detailItem?.reviewed_at);

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
        aria-label={t('messages.filter_aria')}
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

      {/* ── Message Detail Modal ────────────────────────────────────────── */}
      <Modal
        isOpen={!!detailItem}
        onClose={closeDetail}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <MessageSquare size={18} className="text-primary shrink-0" />
            <span>{t('messages.detail_title')}</span>
          </ModalHeader>

          <ModalBody className="gap-4">
            {detailLoading && (
              <p className="text-sm text-default-400 text-center py-8">{t('common.loading')}</p>
            )}

            {!detailLoading && detailItem && (
              <>
                {/* Meta: sender / receiver / date */}
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">
                      {t('messages.detail_from')}
                    </p>
                    <p className="font-medium text-foreground">{detailItem.sender_name}</p>
                  </div>
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">
                      {t('messages.detail_to')}
                    </p>
                    <p className="font-medium text-foreground">{detailItem.receiver_name}</p>
                  </div>
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">
                      {t('messages.detail_date')}
                    </p>
                    <p className="text-foreground">
                      {new Date(detailItem.sent_at ?? detailItem.created_at).toLocaleString()}
                    </p>
                  </div>
                  {(detailItem.flag_reason || detailItem.copy_reason) && (
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-0.5">
                        {t('messages.detail_reason')}
                      </p>
                      <p className="text-foreground">
                        {detailItem.flag_reason || detailItem.copy_reason}
                      </p>
                    </div>
                  )}
                  {detailItem.flag_severity && (
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-0.5">
                        {t('messages.detail_severity')}
                      </p>
                      {(() => {
                        const { color, variant } = severityColor(detailItem.flag_severity);
                        return (
                          <Chip size="sm" variant={variant} color={color} className="capitalize">
                            {t(`status.${detailItem.flag_severity}`, { defaultValue: detailItem.flag_severity })}
                          </Chip>
                        );
                      })()}
                    </div>
                  )}
                </div>

                <Divider />

                {/* Message body */}
                <div>
                  <p className="text-xs text-default-400 uppercase font-medium mb-2">
                    {t('messages.detail_content')}
                  </p>
                  <div className="rounded-lg bg-default-50 p-4 text-sm text-foreground whitespace-pre-wrap leading-relaxed min-h-[80px]">
                    {detail?.copy?.message_body || detailItem.message_body || '--'}
                  </div>
                </div>

                {/* Thread context if available */}
                {detail?.thread && detail.thread.length > 0 && (
                  <>
                    <Divider />
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-2">
                        Conversation ({detail.thread.length})
                      </p>
                      <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                        {detail.thread.map((msg) => (
                          <div
                            key={msg.id}
                            className="rounded-md bg-default-50 px-3 py-2 text-sm"
                          >
                            <span className="font-medium text-foreground mr-2">{msg.sender_name}</span>
                            <span className="text-default-500 text-xs">
                              {new Date(msg.created_at).toLocaleString()}
                            </span>
                            <p className="mt-1 text-default-700 whitespace-pre-wrap">{msg.body}</p>
                          </div>
                        ))}
                      </div>
                    </div>
                  </>
                )}

                {/* Review status */}
                {isReviewed ? (
                  <>
                    <Divider />
                    <div className="flex items-center gap-2 text-sm text-success">
                      <Chip size="sm" color="success" variant="flat">{t('status.reviewed')}</Chip>
                      <span className="text-default-500">
                        {new Date(detailItem.reviewed_at!).toLocaleString()}
                      </span>
                    </div>
                  </>
                ) : (
                  <>
                    <Divider />
                    <Textarea
                      label={t('messages.detail_review_notes')}
                      placeholder={t('messages.detail_review_notes')}
                      value={detailReviewNotes}
                      onValueChange={setDetailReviewNotes}
                      minRows={2}
                      variant="bordered"
                    />
                  </>
                )}
              </>
            )}
          </ModalBody>

          <ModalFooter>
            <Button variant="flat" onPress={closeDetail}>
              {t('common.cancel')}
            </Button>
            {!isReviewed && (
              <Button
                color="primary"
                startContent={<Eye size={16} />}
                isLoading={detailReviewLoading}
                isDisabled={detailReviewLoading}
                onPress={handleDetailReview}
              >
                {t('messages.mark_reviewed')}
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
