// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Message Review
 * Review broker message copies with flagged/unreviewed filtering.
 * Parity: PHP BrokerControlsController::messages()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
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
} from '@heroui/react';
import { ArrowLeft, CheckCircle, Flag } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { BrokerMessage } from '../../api/types';

export function MessageReview() {
  usePageTitle('Admin - Message Review');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<BrokerMessage[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState('unreviewed');
  const [reviewingId, setReviewingId] = useState<number | null>(null);

  // Flag modal state
  const [flagModalOpen, setFlagModalOpen] = useState(false);
  const [selectedMessageId, setSelectedMessageId] = useState<number | null>(null);
  const [flagReason, setFlagReason] = useState('');
  const [flagSeverity, setFlagSeverity] = useState<'concern' | 'serious' | 'urgent'>('concern');
  const [flagLoading, setFlagLoading] = useState(false);

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
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [page, filter]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleReview = async (id: number) => {
    setReviewingId(id);
    try {
      const res = await adminBroker.reviewMessage(id);
      if (res?.success) {
        toast.success('Message marked as reviewed');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to mark message as reviewed');
      }
    } catch {
      toast.error('Failed to mark message as reviewed');
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
      toast.error('A reason is required to flag a message');
      return;
    }
    setFlagLoading(true);
    try {
      const res = await adminBroker.flagMessage(selectedMessageId, flagReason, flagSeverity);
      if (res?.success) {
        toast.success('Message flagged successfully');
        setFlagModalOpen(false);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to flag message');
      }
    } catch {
      toast.error('Failed to flag message');
    } finally {
      setFlagLoading(false);
    }
  };

  const columns: Column<BrokerMessage>[] = [
    {
      key: 'sender_name',
      label: 'Sender',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.sender_name}</span>
      ),
    },
    {
      key: 'receiver_name',
      label: 'Receiver',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.receiver_name}</span>
      ),
    },
    {
      key: 'listing_title',
      label: 'Listing',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'flagged',
      label: 'Flagged',
      render: (item) => (
        item.flagged ? (
          <Chip
            size="sm"
            variant="flat"
            color="danger"
            startContent={<Flag size={12} />}
          >
            {item.flag_severity || 'Flagged'}
          </Chip>
        ) : (
          <span className="text-sm text-default-400">No</span>
        )
      ),
    },
    {
      key: 'reviewed_at',
      label: 'Status',
      render: (item) => (
        item.reviewed_at ? (
          <Chip size="sm" variant="flat" color="success">
            Reviewed
          </Chip>
        ) : (
          <Chip size="sm" variant="flat" color="warning">
            Unreviewed
          </Chip>
        )
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
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
              aria-label="Mark as reviewed"
            >
              Review
            </Button>
          )}
          {!item.flagged && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              startContent={<Flag size={14} />}
              onPress={() => openFlagModal(item.id)}
              aria-label="Flag message"
            >
              Flag
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Message Review"
        description="Review broker message copies and flagged conversations"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={(key) => { setFilter(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="unreviewed" title="Unreviewed" />
          <Tab key="flagged" title="Flagged" />
          <Tab key="reviewed" title="Reviewed" />
          <Tab key="all" title="All" />
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
            Flag Message
          </ModalHeader>
          <ModalBody>
            <Textarea
              label="Reason (required)"
              placeholder="Describe why this message is being flagged..."
              value={flagReason}
              onValueChange={setFlagReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <Select
              label="Severity"
              selectedKeys={[flagSeverity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'concern' | 'serious' | 'urgent';
                if (val) setFlagSeverity(val);
              }}
              variant="bordered"
            >
              <SelectItem key="concern">Concern</SelectItem>
              <SelectItem key="serious">Serious</SelectItem>
              <SelectItem key="urgent">Urgent</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setFlagModalOpen(false)}
              isDisabled={flagLoading}
            >
              Cancel
            </Button>
            <Button
              color="warning"
              onPress={handleFlag}
              isLoading={flagLoading}
              startContent={!flagLoading && <Flag size={14} />}
            >
              Flag Message
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MessageReview;
