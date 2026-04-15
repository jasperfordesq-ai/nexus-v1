// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * 404 Error Tracking
 * Monitor and manage 404 error occurrences across the platform.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Button, Spinner } from '@heroui/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState, DataTable, ConfirmModal, type Column } from '../../components';
import { adminTools } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
interface Error404Entry {
  id: number;
  url: string;
  referrer: string;
  hits: number;
  first_seen: string;
  last_seen: string;
}

export function Error404Tracking() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();

  const [errors, setErrors] = useState<Error404Entry[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [deleteTarget, setDeleteTarget] = useState<Error404Entry | null>(null);
  const [deleting, setDeleting] = useState(false);
  const mounted = useRef(true);

  const fetchErrors = useCallback(async (p = 1) => {
    setLoading(true);
    try {
      const res = await adminTools.get404Errors(p);
      if (!mounted.current) return;
      // api.ts wraps in { success, data, meta } — unwrapped payload is in .data
      const payload = (res as { data?: { items?: Error404Entry[]; total?: number; page?: number } }).data ?? res;
      const items = (payload as { items?: Error404Entry[] }).items ?? [];
      const total = (payload as { total?: number }).total ?? 0;
      const pg = (payload as { page?: number }).page ?? 1;
      setErrors(items);
      setTotalItems(total);
      setPage(pg);
    } catch {
      toast.error(t('advanced.failed_to_load_404_errors'));
    } finally {
      if (mounted.current) setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    mounted.current = true;
    fetchErrors(1);
    return () => { mounted.current = false; };
  }, [fetchErrors]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminTools.delete404Error(deleteTarget.id);

      if (res.success) {
        toast.success(t('advanced.404_entry_dismissed'));
        setDeleteTarget(null);
        await fetchErrors();
      } else {
        const error = (res as { error?: string }).error || t('advanced.failed_to_delete_404_entry');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_delete_404_entry'));
      console.error('404 error delete error:', err);
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Error404Entry>[] = [
    {
      key: 'url',
      label: t('advanced.col_url'),
      sortable: true,
      render: (item) => (
        <span className="font-mono text-sm break-all">{item.url}</span>
      ),
    },
    {
      key: 'referrer',
      label: t('advanced.col_referrer'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500 break-all">
          {item.referrer || '(direct)'}
        </span>
      ),
    },
    { key: 'hits', label: t('advanced.col_hits'), sortable: true },
    {
      key: 'last_seen',
      label: t('advanced.col_last_seen'),
      sortable: true,
      render: (item) => new Date(item.last_seen).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: '',
      render: (item) => (
        <Button
          isIconOnly
          variant="light"
          color="danger"
          size="sm"
          onPress={() => setDeleteTarget(item)}
          aria-label={t('advanced.dismiss_404_entry')}
        >
          <Trash2 size={16} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('advanced.error404_tracking_title')} description={t('advanced.error404_tracking_desc')} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('advanced.error404_tracking_title')} description={t('advanced.error404_tracking_desc')} />

      {errors.length === 0 ? (
        <EmptyState
          icon={AlertTriangle}
          title={t('advanced.no_404_errors')}
          description={t('advanced.desc_when_visitors_hit_missing_pages_they_wi')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={errors}
          searchable={false}
          onRefresh={() => fetchErrors(page)}
          totalItems={totalItems}
          page={page}
          pageSize={50}
          onPageChange={(p) => fetchErrors(p)}
        />
      )}

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={t('advanced.dismiss_404_title')}
        message={t('advanced.dismiss_404_message', { url: deleteTarget?.url })}
        confirmLabel={t('advanced.dismiss')}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Error404Tracking;
