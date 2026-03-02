// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Ideation / Challenges Management
 * List, search, filter, change status, and delete ideation challenges.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Chip,
  Button,
  Tabs,
  Tab,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import {
  Lightbulb,
  Eye,
  Trash2,
  MoreVertical,
  RefreshCw,
  CheckCircle,
  Archive,
  XCircle,
  FileEdit,
  Vote,
  ClipboardCheck,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Challenge {
  id: number;
  title: string;
  creator_name: string;
  ideas_count: number;
  status: 'draft' | 'open' | 'voting' | 'evaluating' | 'closed' | 'archived';
  start_date: string;
  end_date: string;
  created_at: string;
}

interface ChallengeMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

type ChallengeStatus = Challenge['status'];

const statusColors: Record<ChallengeStatus, 'success' | 'warning' | 'default' | 'secondary' | 'primary' | 'danger'> = {
  draft: 'default',
  open: 'success',
  voting: 'primary',
  evaluating: 'warning',
  closed: 'danger',
  archived: 'secondary',
};

// ─────────────────────────────────────────────────────────────────────────────
// ChallengeActions — extracted to module level to avoid remount on every render
// ─────────────────────────────────────────────────────────────────────────────

interface ChallengeActionsProps {
  challenge: Challenge;
  onStatusChange: (challenge: Challenge, status: ChallengeStatus) => void;
  onDelete: (challenge: Challenge) => void;
  onView: (challenge: Challenge) => void;
}

function ChallengeActions({ challenge, onStatusChange, onDelete, onView }: ChallengeActionsProps) {
  type ActionKey = 'view' | ChallengeStatus | 'delete';

  const handleAction = (key: React.Key) => {
    const action = key as ActionKey;
    if (action === 'view') {
      onView(challenge);
    } else if (action === 'delete') {
      onDelete(challenge);
    } else {
      onStatusChange(challenge, action);
    }
  };

  return (
    <Dropdown>
      <DropdownTrigger>
        <Button isIconOnly size="sm" variant="light" aria-label="Actions">
          <MoreVertical size={16} />
        </Button>
      </DropdownTrigger>
      <DropdownMenu aria-label="Challenge actions" onAction={handleAction}>
        <DropdownItem key="view" startContent={<Eye size={14} />}>
          View Details
        </DropdownItem>
        <DropdownItem
          key="draft"
          startContent={<FileEdit size={14} />}
          className={challenge.status !== 'draft' ? '' : 'hidden'}
        >
          Mark as Draft
        </DropdownItem>
        <DropdownItem
          key="open"
          startContent={<CheckCircle size={14} />}
          color="success"
          className={challenge.status !== 'open' ? 'text-success' : 'hidden'}
        >
          Mark as Open
        </DropdownItem>
        <DropdownItem
          key="voting"
          startContent={<Vote size={14} />}
          color="primary"
          className={challenge.status !== 'voting' ? 'text-primary' : 'hidden'}
        >
          Mark as Voting
        </DropdownItem>
        <DropdownItem
          key="evaluating"
          startContent={<ClipboardCheck size={14} />}
          color="warning"
          className={challenge.status !== 'evaluating' ? 'text-warning' : 'hidden'}
        >
          Mark as Evaluating
        </DropdownItem>
        <DropdownItem
          key="closed"
          startContent={<XCircle size={14} />}
          className={challenge.status !== 'closed' ? '' : 'hidden'}
        >
          Mark as Closed
        </DropdownItem>
        <DropdownItem
          key="archived"
          startContent={<Archive size={14} />}
          className={challenge.status !== 'archived' ? '' : 'hidden'}
        >
          Mark as Archived
        </DropdownItem>
        <DropdownItem
          key="delete"
          startContent={<Trash2 size={14} />}
          className="text-danger"
          color="danger"
        >
          Delete
        </DropdownItem>
      </DropdownMenu>
    </Dropdown>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function IdeationAdmin() {
  usePageTitle('Admin - Ideation');
  const toast = useToast();

  const [items, setItems] = useState<Challenge[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Challenge | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [detailItem, setDetailItem] = useState<Challenge | null>(null);

  // ── Load challenges ─────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        limit: '50',
      });
      if (search) params.set('search', search);
      if (status !== 'all') params.set('status', status);

      const res = await api.get(`/v2/admin/ideation?${params.toString()}`);
      if (res.success && res.data) {
        const payload = res.data as { items?: Challenge[]; meta?: ChallengeMeta };
        setItems(payload.items || []);
        setTotal(payload.meta?.total || 0);
      }
    } catch {
      toast.error('Failed to load ideation challenges');
    } finally {
      setLoading(false);
    }
  }, [page, search, status, toast]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Status change handler ───────────────────────────────────────────────

  const handleStatusChange = async (challenge: Challenge, newStatus: ChallengeStatus) => {
    try {
      const res = await api.post(`/v2/admin/ideation/${challenge.id}/status`, {
        status: newStatus,
      });
      if (res?.success) {
        toast.success(`Challenge "${challenge.title}" marked as ${newStatus}`);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to update challenge status');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  };

  // ── Delete handler ──────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/ideation/${confirmDelete.id}`);
      if (res?.success) {
        toast.success('Challenge deleted successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to delete challenge');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Table columns ───────────────────────────────────────────────────────

  const columns: Column<Challenge>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground line-clamp-1">{item.title}</span>
      ),
    },
    {
      key: 'creator_name',
      label: 'Creator',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.creator_name || 'Unknown'}</span>
      ),
    },
    {
      key: 'ideas_count',
      label: 'Ideas',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.ideas_count}</span>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={statusColors[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'start_date',
      label: 'Start Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.start_date ? new Date(item.start_date).toLocaleDateString() : '\u2014'}
        </span>
      ),
    },
    {
      key: 'end_date',
      label: 'End Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.end_date ? new Date(item.end_date).toLocaleDateString() : '\u2014'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <ChallengeActions
          challenge={item}
          onStatusChange={handleStatusChange}
          onDelete={setConfirmDelete}
          onView={setDetailItem}
        />
      ),
    },
  ];

  // ── Render ──────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title="Ideation Challenges"
        description="Manage community innovation challenges and ideas"
        actions={
          <div className="flex gap-2 items-center">
            <Chip variant="flat" startContent={<Lightbulb size={14} />}>
              {total} total
            </Chip>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={loadItems}
              aria-label="Refresh"
            >
              <RefreshCw size={14} />
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => {
            setStatus(key as string);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="draft" title="Draft" />
          <Tab key="open" title="Open" />
          <Tab key="voting" title="Voting" />
          <Tab key="evaluating" title="Evaluating" />
          <Tab key="closed" title="Closed" />
          <Tab key="archived" title="Archived" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search challenges..."
        onSearch={(q) => {
          setSearch(q);
          setPage(1);
        }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={50}
        onPageChange={setPage}
      />

      {/* Delete confirmation */}
      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Challenge"
          message={`Are you sure you want to delete "${confirmDelete.title}"? All associated ideas will also be removed. This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Detail view modal */}
      {detailItem && (
        <ConfirmModal
          isOpen={!!detailItem}
          onClose={() => setDetailItem(null)}
          onConfirm={() => setDetailItem(null)}
          title="Challenge Details"
          message=""
          confirmLabel="Close"
          confirmColor="primary"
        >
          <div className="space-y-3">
            <div>
              <span className="text-sm font-medium text-default-500">Title</span>
              <p className="text-foreground">{detailItem.title}</p>
            </div>
            <div className="flex gap-6">
              <div>
                <span className="text-sm font-medium text-default-500">Creator</span>
                <p className="text-foreground">{detailItem.creator_name || 'Unknown'}</p>
              </div>
              <div>
                <span className="text-sm font-medium text-default-500">Ideas</span>
                <p className="text-foreground">{detailItem.ideas_count}</p>
              </div>
            </div>
            <div className="flex gap-6">
              <div>
                <span className="text-sm font-medium text-default-500">Status</span>
                <p>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={statusColors[detailItem.status] || 'default'}
                    className="capitalize"
                  >
                    {detailItem.status}
                  </Chip>
                </p>
              </div>
              <div>
                <span className="text-sm font-medium text-default-500">Start Date</span>
                <p className="text-foreground">
                  {detailItem.start_date
                    ? new Date(detailItem.start_date).toLocaleDateString()
                    : '\u2014'}
                </p>
              </div>
              <div>
                <span className="text-sm font-medium text-default-500">End Date</span>
                <p className="text-foreground">
                  {detailItem.end_date
                    ? new Date(detailItem.end_date).toLocaleDateString()
                    : '\u2014'}
                </p>
              </div>
            </div>
            <div>
              <span className="text-sm font-medium text-default-500">Created</span>
              <p className="text-foreground">
                {new Date(detailItem.created_at).toLocaleString()}
              </p>
            </div>
          </div>
        </ConfirmModal>
      )}
    </div>
  );
}

export default IdeationAdmin;
