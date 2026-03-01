// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Polls Management
 * List, search, view, and delete polls with pagination.
 */

import { useState, useCallback, useEffect } from 'react';
import { Chip, Button } from '@heroui/react';
import { BarChart3, Eye, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Poll {
  id: number;
  question: string;
  options_count: number;
  votes_count: number;
  creator_name: string;
  status: 'active' | 'ended';
  created_at: string;
}

interface PollsMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

const statusColors: Record<string, 'success' | 'default'> = {
  active: 'success',
  ended: 'default',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function PollsAdmin() {
  usePageTitle('Admin - Polls');
  const toast = useToast();

  const [items, setItems] = useState<Poll[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Poll | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [detailPoll, setDetailPoll] = useState<Poll | null>(null);

  // ── Load polls ──────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        limit: '50',
      });
      if (search) params.set('search', search);

      const res = await api.get(`/v2/admin/polls?${params.toString()}`);
      if (res.success && res.data) {
        const payload = res.data as { items?: Poll[]; meta?: PollsMeta };
        setItems(payload.items || []);
        setTotal(payload.meta?.total || 0);
      }
    } catch {
      toast.error('Failed to load polls');
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Delete handler ──────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/polls/${confirmDelete.id}`);
      if (res?.success) {
        toast.success('Poll deleted successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to delete poll');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Columns ─────────────────────────────────────────────────────────────

  const columns: Column<Poll>[] = [
    {
      key: 'question',
      label: 'Question',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground line-clamp-2">
          {item.question}
        </span>
      ),
    },
    {
      key: 'options_count',
      label: 'Options',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.options_count}</span>
      ),
    },
    {
      key: 'votes_count',
      label: 'Votes',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.votes_count}</span>
      ),
    },
    {
      key: 'creator_name',
      label: 'Creator',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.creator_name || 'Unknown'}
        </span>
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
      key: 'created_at',
      label: 'Created',
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
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => setDetailPoll(item)}
            aria-label="View poll details"
          >
            <Eye size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label="Delete poll"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  // ── Render ──────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title="Polls"
        description="View and manage community polls"
        actions={
          <Chip variant="flat" startContent={<BarChart3 size={14} />}>
            {total} total
          </Chip>
        }
      />

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search polls..."
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
          title="Delete Poll"
          message={`Are you sure you want to delete the poll "${confirmDelete.question}"? This will also remove all votes. This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Detail view modal */}
      {detailPoll && (
        <ConfirmModal
          isOpen={!!detailPoll}
          onClose={() => setDetailPoll(null)}
          onConfirm={() => setDetailPoll(null)}
          title="Poll Details"
          message=""
          confirmLabel="Close"
          confirmColor="primary"
        >
          <div className="space-y-3">
            <div>
              <span className="text-sm font-medium text-default-500">Question</span>
              <p className="text-foreground">{detailPoll.question}</p>
            </div>
            <div className="flex gap-6">
              <div>
                <span className="text-sm font-medium text-default-500">Options</span>
                <p className="text-foreground">{detailPoll.options_count}</p>
              </div>
              <div>
                <span className="text-sm font-medium text-default-500">Votes</span>
                <p className="text-foreground">{detailPoll.votes_count}</p>
              </div>
            </div>
            <div className="flex gap-6">
              <div>
                <span className="text-sm font-medium text-default-500">Creator</span>
                <p className="text-foreground">{detailPoll.creator_name || 'Unknown'}</p>
              </div>
              <div>
                <span className="text-sm font-medium text-default-500">Status</span>
                <p>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={statusColors[detailPoll.status] || 'default'}
                    className="capitalize"
                  >
                    {detailPoll.status}
                  </Chip>
                </p>
              </div>
            </div>
            <div>
              <span className="text-sm font-medium text-default-500">Created</span>
              <p className="text-foreground">
                {new Date(detailPoll.created_at).toLocaleString()}
              </p>
            </div>
          </div>
        </ConfirmModal>
      )}
    </div>
  );
}

export default PollsAdmin;
