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

interface Error404Entry {
  id: number;
  url: string;
  referrer: string;
  hits: number;
  first_seen: string;
  last_seen: string;
}

export function Error404Tracking() {
  usePageTitle('Admin - 404 Error Tracking');
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
      toast.error('Failed to load 404 errors');
    } finally {
      if (mounted.current) setLoading(false);
    }
  }, [toast]);

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
        toast.success('404 entry dismissed');
        setDeleteTarget(null);
        await fetchErrors();
      } else {
        const error = (res as { error?: string }).error || 'Failed to delete 404 entry';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to delete 404 entry');
      console.error('404 error delete error:', err);
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Error404Entry>[] = [
    {
      key: 'url',
      label: 'URL',
      sortable: true,
      render: (item) => (
        <span className="font-mono text-sm break-all">{item.url}</span>
      ),
    },
    {
      key: 'referrer',
      label: 'Referrer',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500 break-all">
          {item.referrer || '(direct)'}
        </span>
      ),
    },
    { key: 'hits', label: 'Hits', sortable: true },
    {
      key: 'last_seen',
      label: 'Last Seen',
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
          aria-label="Dismiss 404 entry"
        >
          <Trash2 size={16} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="404 Error Tracking" description="Monitor missing pages and broken URLs" />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="404 Error Tracking" description="Monitor missing pages and broken URLs" />

      {errors.length === 0 ? (
        <EmptyState
          icon={AlertTriangle}
          title="No 404 Errors Tracked"
          description="When visitors hit missing pages, they will be logged here. You can then create redirects to fix them."
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
        title="Dismiss 404 Entry"
        message={`Dismiss the 404 error for "${deleteTarget?.url}"? It will be removed from tracking.`}
        confirmLabel="Dismiss"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Error404Tracking;
