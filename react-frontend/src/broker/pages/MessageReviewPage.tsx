// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Message Review
 * Review broker message copies with flagged/unreviewed filtering.
 * Parity: PHP BrokerControlsController::messages()
 *
 * Broker port retains the row-level "Quick view" detail modal that lets
 * brokers triage messages without leaving the list page, on top of the
 * admin's Review / Flag actions and navigation to the detail page.
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Tabs,
  Tab,
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Select,
  SelectItem,
  Divider,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Flag from 'lucide-react/icons/flag';
import Eye from 'lucide-react/icons/eye';
import MessageSquare from 'lucide-react/icons/message-square';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { DataTable, PageHeader, type Column } from '@/admin/components';
import type { BrokerMessage, BrokerMessageDetail } from '@/admin/api/types';

type SeverityChipColor = 'default' | 'warning' | 'danger';
type SeverityChipVariant = 'flat' | 'solid';

function severityColor(severity?: string): { color: SeverityChipColor; variant: SeverityChipVariant } {
  switch (severity?.toLowerCase()) {
    case 'medium':
    case 'warning':
      return { color: 'warning', variant: 'flat' };
    case 'high':
    case 'concern':
      return { color: 'danger', variant: 'flat' };
    case 'critical':
    case 'urgent':
      return { color: 'danger', variant: 'solid' };
    default:
      return { color: 'default', variant: 'flat' };
  }
}

export function MessageReview() {
  usePageTitle("Message Review - Broker");
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [searchParams, setSearchParams] = useSearchParams();

  // The active tab is driven by the URL so deep-links from the broker
  // dashboard stat cards land on the right filter.
  const ALLOWED_FILTERS = ['unreviewed', 'flagged', 'reviewed', 'all'] as const;
  type MessageFilter = (typeof ALLOWED_FILTERS)[number];
  const urlStatus = searchParams.get('status') as MessageFilter | null;
  const filter: MessageFilter =
    urlStatus && ALLOWED_FILTERS.includes(urlStatus) ? urlStatus : 'unreviewed';
  const setFilter = useCallback(
    (next: MessageFilter) => {
      setSearchParams(
        (prev) => {
          const params = new URLSearchParams(prev);
          if (next === 'unreviewed') {
            params.delete('status');
          } else {
            params.set('status', next);
          }
          return params;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  const [items, setItems] = useState<BrokerMessage[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [reviewingId, setReviewingId] = useState<number | null>(null);

  // Flag modal state
  const [flagModalOpen, setFlagModalOpen] = useState(false);
  const [selectedMessageId, setSelectedMessageId] = useState<number | null>(null);
  const [flagReason, setFlagReason] = useState('');
  const [flagSeverity, setFlagSeverity] = useState<'info' | 'warning' | 'concern' | 'urgent'>('concern');
  const [flagLoading, setFlagLoading] = useState(false);

  // Detail modal state (broker-only quick-view UX)
  const [detailItem, setDetailItem] = useState<BrokerMessage | null>(null);
  const [detail, setDetail] = useState<BrokerMessageDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailReviewNotes, setDetailReviewNotes] = useState('');
  const [detailReviewLoading, setDetailReviewLoading] = useState(false);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getMessages({
        page,
        filter: filter === 'all' ? undefined : filter,
      });
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as BrokerMessage[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error("Failed to load messages");
    } finally {
      setLoading(false);
    }
  }, [page, filter, toast])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleReview = async (id: number) => {
    setReviewingId(id);
    try {
      const res = await adminBroker.reviewMessage(id);
      if (res?.success) {
        toast.success("Message marked as reviewed");
        loadItems();
      } else {
        toast.error(res?.error || "Failed to mark message as reviewed");
      }
    } catch {
      toast.error("Failed to mark message as reviewed");
    } finally {
      setReviewingId(null);
    }
  };

  const openFlagModal = (id: number) => {
    setSelectedMessageId(id);
    setFlagReason('');
    setFlagSeverity('concern');
    setFlagModalOpen(true);
  };

  const handleFlag = async () => {
    if (!selectedMessageId) return;
    if (!flagReason.trim()) {
      toast.error("A reason is required to flag a message");
      return;
    }
    setFlagLoading(true);
    try {
      const res = await adminBroker.flagMessage(selectedMessageId, flagReason, flagSeverity);
      if (res?.success) {
        toast.success("Message flagged successfully");
        setFlagModalOpen(false);
        loadItems();
      } else {
        toast.error(res?.error || "Failed to flag message");
      }
    } catch {
      toast.error("Failed to flag message");
    } finally {
      setFlagLoading(false);
    }
  };

  // ── Quick-view detail modal (broker enhancement) ──────────────────────────

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
      // Fall back to list-row info if detail fetch fails
    } finally {
      setDetailLoading(false);
    }
  }, []);

  const closeDetail = useCallback(() => {
    setDetailItem(null);
    setDetail(null);
    setDetailReviewNotes('');
  }, []);

  const handleDetailReview = useCallback(async () => {
    if (!detailItem) return;
    setDetailReviewLoading(true);
    try {
      const res = await adminBroker.reviewMessage(detailItem.id, detailReviewNotes || undefined);
      if (res?.success) {
        toast.success("Message marked as reviewed");
        closeDetail();
        loadItems();
      } else {
        toast.error(res?.error || "Failed to mark message as reviewed");
      }
    } catch {
      toast.error("Failed to mark message as reviewed");
    } finally {
      setDetailReviewLoading(false);
    }
  }, [detailItem, detailReviewNotes, closeDetail, loadItems, toast]);

  const isDetailReviewed = !!(detailItem?.reviewed_at);

  const columns: Column<BrokerMessage>[] = [
    {
      key: 'sender_name',
      label: "Sender",
      sortable: true,
      render: (item) => (
        <Link
          to={tenantPath(`/broker/messages/${item.id}`)}
          className="font-medium text-primary hover:underline"
        >
          {item.sender_name}
        </Link>
      ),
    },
    {
      key: 'receiver_name',
      label: "Receiver",
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.receiver_name}</span>
      ),
    },
    {
      key: 'message_body',
      label: "Preview",
      render: (item) => (
        <span className="text-sm text-default-500 line-clamp-1 max-w-[200px]">
          {item.message_body ? item.message_body.substring(0, 80) + (item.message_body.length > 80 ? '…' : '') : '—'}
        </span>
      ),
    },
    {
      key: 'copy_reason',
      label: "Reason",
      render: (item) => (
        item.copy_reason ? (
          <Chip size="sm" variant="flat" color="default">
            {item.copy_reason.replace(/_/g, ' ')}
          </Chip>
        ) : <span className="text-sm text-default-400">—</span>
      ),
    },
    {
      key: 'flagged',
      label: "Flagged",
      render: (item) => {
        if (!item.flagged) return <span className="text-sm text-default-400">{"No"}</span>;
        const severityChipColor = {
          info: 'default' as const,
          warning: 'warning' as const,
          concern: 'danger' as const,
          urgent: 'danger' as const,
        }[item.flag_severity || 'concern'] ?? 'danger' as const;
        return (
          <Chip
            size="sm"
            variant="flat"
            color={severityChipColor}
            startContent={<Flag size={12} />}
          >
            {item.flag_severity || 'Flagged'}
          </Chip>
        );
      },
    },
    {
      key: 'reviewed_at',
      label: "Status",
      render: (item) => (
        item.reviewed_at ? (
          <Chip size="sm" variant="flat" color="success">
            {"Reviewed"}
          </Chip>
        ) : (
          <Chip size="sm" variant="flat" color="warning">
            {"Unreviewed"}
          </Chip>
        )
      ),
    },
    {
      key: 'created_at',
      label: "Date",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          {!item.reviewed_at && (
            <Button
              size="sm"
              variant="flat"
              color="success"
              startContent={<CheckCircle size={14} />}
              onPress={() => handleReview(item.id)}
              isLoading={reviewingId === item.id}
              aria-label={"Mark as Reviewed"}
            >
              {"Review"}
            </Button>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="default"
            onPress={() => openDetail(item)}
            aria-label={"Quick view"}
          >
            <Eye size={14} />
          </Button>
          {!item.flagged && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              startContent={<Flag size={14} />}
              onPress={() => openFlagModal(item.id)}
              aria-label={"Flag Message"}
            >
              {"Flag"}
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={"Message Review"}
        description={"Review messages flagged for moderation or safeguarding concerns"}
        actions={
          <Button
            as={Link}
            to={tenantPath('/broker')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {"Back"}
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={(key) => { setFilter(key as MessageFilter); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="unreviewed" title={"Unreviewed"} />
          <Tab key="flagged" title={"Flagged"} />
          <Tab key="reviewed" title={"Reviewed"} />
          <Tab key="all" title={"All"} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {/* Flag Message Modal */}
      <Modal
        isOpen={flagModalOpen}
        onClose={() => setFlagModalOpen(false)}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Flag size={20} className="text-warning" />
            {"Flag"}
          </ModalHeader>
          <ModalBody>
            <Textarea
              label={"Reason Required"}
              placeholder={"Describe Why This Message is Being Flagged..."}
              value={flagReason}
              onValueChange={setFlagReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <Select
              label={"Severity"}
              selectedKeys={[flagSeverity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'info' | 'warning' | 'concern' | 'urgent';
                if (val) setFlagSeverity(val);
              }}
              variant="bordered"
            >
              <SelectItem key="info">{"Severity Info"}</SelectItem>
              <SelectItem key="warning">{"Severity Warning"}</SelectItem>
              <SelectItem key="concern">{"Severity Concern"}</SelectItem>
              <SelectItem key="urgent">{"Severity Urgent"}</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setFlagModalOpen(false)}
              isDisabled={flagLoading}
            >
              {"Cancel"}
            </Button>
            <Button
              color="warning"
              onPress={handleFlag}
              isLoading={flagLoading}
              startContent={!flagLoading && <Flag size={14} />}
            >
              {"Flag"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Quick-view Message Detail Modal (broker UX enhancement) */}
      <Modal
        isOpen={!!detailItem}
        onClose={closeDetail}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <MessageSquare size={18} className="text-primary shrink-0" />
            <span>{"Message Detail"}</span>
          </ModalHeader>

          <ModalBody className="gap-4">
            {detailLoading && (
              <p className="text-sm text-default-400 text-center py-8">{"Loading..."}</p>
            )}

            {!detailLoading && detailItem && (
              <>
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">{"From"}</p>
                    <p className="font-medium text-foreground">{detailItem.sender_name}</p>
                  </div>
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">{"To"}</p>
                    <p className="font-medium text-foreground">{detailItem.receiver_name}</p>
                  </div>
                  <div>
                    <p className="text-xs text-default-400 uppercase font-medium mb-0.5">{"Date"}</p>
                    <p className="text-foreground">
                      {new Date(detailItem.sent_at ?? detailItem.created_at).toLocaleString()}
                    </p>
                  </div>
                  {(detailItem.flag_reason || detailItem.copy_reason) && (
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-0.5">{"Reason"}</p>
                      <p className="text-foreground">
                        {detailItem.flag_reason || detailItem.copy_reason}
                      </p>
                    </div>
                  )}
                  {detailItem.flag_severity && (
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-0.5">{"Severity"}</p>
                      {(() => {
                        const { color, variant } = severityColor(detailItem.flag_severity);
                        return (
                          <Chip size="sm" variant={variant} color={color} className="capitalize">
                            {detailItem.flag_severity}
                          </Chip>
                        );
                      })()}
                    </div>
                  )}
                </div>

                <Divider />

                <div>
                  <p className="text-xs text-default-400 uppercase font-medium mb-2">{"Content"}</p>
                  <div className="rounded-lg bg-default-50 p-4 text-sm text-foreground whitespace-pre-wrap leading-relaxed min-h-[80px]">
                    {detail?.copy?.message_body || detailItem.message_body || '--'}
                  </div>
                </div>

                {detail?.thread && detail.thread.length > 0 && (
                  <>
                    <Divider />
                    <div>
                      <p className="text-xs text-default-400 uppercase font-medium mb-2">
                        {"Conversation"} ({detail.thread.length})
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

                {isDetailReviewed ? (
                  <>
                    <Divider />
                    <div className="flex items-center gap-2 text-sm text-success">
                      <Chip size="sm" color="success" variant="flat">{"Reviewed"}</Chip>
                      <span className="text-default-500">
                        {new Date(detailItem.reviewed_at!).toLocaleString()}
                      </span>
                    </div>
                  </>
                ) : (
                  <>
                    <Divider />
                    <Textarea
                      label={"Review notes"}
                      placeholder={"Optional review notes"}
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
            <Button variant="flat" onPress={closeDetail} isDisabled={detailReviewLoading}>
              {"Cancel"}
            </Button>
            {!isDetailReviewed && detailItem && (
              <Button
                color="primary"
                startContent={<CheckCircle size={16} />}
                isLoading={detailReviewLoading}
                isDisabled={detailReviewLoading}
                onPress={handleDetailReview}
              >
                {"Mark as Reviewed"}
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MessageReview;
