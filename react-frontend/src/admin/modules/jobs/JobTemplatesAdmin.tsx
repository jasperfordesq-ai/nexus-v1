// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Job Templates Management
 * List, search, and delete job templates with pagination.
 */

import { useState, useCallback, useEffect } from 'react';
import { Chip, Button, Tooltip } from '@heroui/react';
import Copy from 'lucide-react/icons/copy';
import Trash2 from 'lucide-react/icons/trash-2';
import Globe from 'lucide-react/icons/globe';
import Lock from 'lucide-react/icons/lock';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface JobTemplate {
  id: number;
  tenant_id: number;
  user_id: number;
  name: string;
  description?: string;
  type: string;
  commitment: string;
  category?: string;
  skills_required?: string;
  is_remote: boolean;
  salary_type?: string;
  salary_currency: string;
  salary_min?: number;
  salary_max?: number;
  hours_per_week?: number;
  time_credits?: number;
  benefits?: string[];
  tagline?: string;
  is_public: boolean;
  use_count: number;
  creator_name: string | null;
  created_at: string;
  updated_at: string;
}

const PAGE_SIZE = 20;

const typeColors: Record<string, 'primary' | 'success' | 'secondary' | 'default'> = {
  paid: 'primary',
  volunteer: 'success',
  timebank: 'secondary',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function JobTemplatesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('jobs.templates_page_title'));
  const toast = useToast();

  const [items, setItems] = useState<JobTemplate[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<JobTemplate | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ── Load templates ────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        limit: String(PAGE_SIZE),
      });
      if (search) params.set('search', search);

      const res = await api.get<JobTemplate[]>(`/v2/admin/jobs/templates?${params.toString()}`);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data);
        setTotal(res.meta?.total ?? res.data.length);
      } else {
        setItems([]);
        setTotal(0);
        toast.error(t('jobs.templates_load_error'));
      }
    } catch {
      toast.error(t('jobs.templates_load_error'));
    } finally {
      setLoading(false);
    }
  }, [page, search, t, toast]);


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Delete ────────────────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/jobs/templates/${confirmDelete.id}`);
      if (res.success) {
        toast.success(t('jobs.templates_deleted'));
        setItems((prev) => prev.filter((tpl) => tpl.id !== confirmDelete.id));
        setTotal((prev) => prev - 1);
      } else {
        toast.error(t('jobs.templates_delete_error'));
      }
    } catch {
      toast.error(t('jobs.templates_delete_error'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Columns ───────────────────────────────────────────────────────────────

  const columns: Column<JobTemplate>[] = [
    {
      key: 'name',
      label: t('jobs.templates_col_name'),
      sortable: true,
      render: (tpl) => <span className="font-semibold">{tpl.name}</span>,
    },
    {
      key: 'creator_name',
      label: t('jobs.templates_col_creator'),
      sortable: true,
      render: (tpl) => tpl.creator_name ?? '—',
    },
    {
      key: 'type',
      label: t('jobs.templates_col_type'),
      sortable: true,
      render: (tpl) => (
        <Chip
          size="sm"
          variant="flat"
          color={typeColors[tpl.type] ?? 'default'}
          className="capitalize"
        >
          {tpl.type}
        </Chip>
      ),
    },
    {
      key: 'is_public',
      label: t('jobs.templates_col_visibility'),
      render: (tpl) =>
        tpl.is_public ? (
          <Tooltip content={t('jobs.templates_public')}>
            <Globe size={16} className="text-success" />
          </Tooltip>
        ) : (
          <Tooltip content={t('jobs.templates_private')}>
            <Lock size={16} className="text-default-400" />
          </Tooltip>
        ),
    },
    {
      key: 'use_count',
      label: t('jobs.templates_col_uses'),
      sortable: true,
      render: (tpl) => tpl.use_count,
    },
    {
      key: 'created_at',
      label: t('jobs.templates_col_created'),
      sortable: true,
      render: (tpl) =>
        new Date(tpl.created_at).toLocaleDateString(undefined, {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
        }),
    },
    {
      key: 'actions',
      label: t('jobs.templates_col_actions'),
      render: (tpl) => (
        <Tooltip content={t('jobs.templates_delete')} color="danger">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            color="danger"
            onPress={() => setConfirmDelete(tpl)}
            aria-label={t('jobs.templates_delete')}
          >
            <Trash2 size={16} />
          </Button>
        </Tooltip>
      ),
    },
  ];

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('jobs.templates_page_title')}
        description={t('jobs.templates_page_description')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadItems}
          >
            {t('jobs.templates_refresh')}
          </Button>
        }
      />

      {!loading && items.length === 0 && !search ? (
        <EmptyState
          icon={Copy}
          title={t('jobs.templates_empty')}
          description={t('jobs.templates_empty_description')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          keyField="id"
          isLoading={loading}
          searchable
          searchPlaceholder={t('jobs.templates_search')}
          totalItems={total}
          page={page}
          pageSize={PAGE_SIZE}
          onPageChange={setPage}
          onSearch={(q) => {
            setSearch(q);
            setPage(1);
          }}
          onRefresh={loadItems}
        />
      )}

      <ConfirmModal
        isOpen={!!confirmDelete}
        onClose={() => setConfirmDelete(null)}
        onConfirm={handleDelete}
        title={t('jobs.templates_delete_title')}
        message={t('jobs.templates_delete_confirm', {
          name: confirmDelete?.name ?? '',
        })}
        confirmLabel={t('jobs.templates_delete')}
        confirmColor="danger"
        isLoading={actionLoading}
      />
    </div>
  );
}

export default JobTemplatesAdmin;
