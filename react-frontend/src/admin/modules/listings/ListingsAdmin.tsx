/**
 * Admin Listings / Content Directory
 * Unified view of all user-generated content with moderation actions.
 * Parity: PHP Admin\ListingController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import { Tabs, Tab, Button, Chip } from '@heroui/react';
import { CheckCircle, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminListings } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminListing } from '../../api/types';

const typeColors: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'default'> = {
  listing: 'primary',
  event: 'secondary',
  poll: 'warning',
  goal: 'success',
  resource: 'default',
  volunteer: 'danger',
};

export function ListingsAdmin() {
  usePageTitle('Admin - Content');
  const toast = useToast();

  const [items, setItems] = useState<AdminListing[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'delete';
    item: AdminListing;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadItems = useCallback(async () => {
    setLoading(true);
    const res = await adminListings.list({
      page,
      status: status === 'all' ? undefined : status,
      search: search || undefined,
    });
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setItems(data);
        setTotal(data.length);
      } else if (data && typeof data === 'object') {
        const pd = data as { data: AdminListing[]; meta?: { total: number } };
        setItems(pd.data || []);
        setTotal(pd.meta?.total || 0);
      }
    }
    setLoading(false);
  }, [page, status, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);
    const { type, item } = confirmAction;
    const res = type === 'approve'
      ? await adminListings.approve(item.id)
      : await adminListings.delete(item.id);

    if (res?.success) {
      toast.success(`Content ${type}d successfully`);
      loadItems();
    } else {
      toast.error(res?.error || `Failed to ${type} content`);
    }
    setActionLoading(false);
    setConfirmAction(null);
  };

  const columns: Column<AdminListing>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={typeColors[item.type] || 'default'} className="capitalize">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'user_name',
      label: 'Author',
      sortable: true,
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
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
          {item.status === 'pending' && (
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="success"
              onPress={() => setConfirmAction({ type: 'approve', item })}
              aria-label="Approve"
            >
              <CheckCircle size={14} />
            </Button>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmAction({ type: 'delete', item })}
            aria-label="Delete"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Content Directory"
        description="View and moderate all user-generated content"
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="pending" title="Pending" />
          <Tab key="active" title="Active" />
          <Tab key="inactive" title="Inactive" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search content..."
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmAction.type === 'approve' ? 'Approve Content' : 'Delete Content'}
          message={
            confirmAction.type === 'approve'
              ? `Approve "${confirmAction.item.title}"? It will become visible to all users.`
              : `Delete "${confirmAction.item.title}"? This cannot be undone.`
          }
          confirmLabel={confirmAction.type === 'approve' ? 'Approve' : 'Delete'}
          confirmColor={confirmAction.type === 'approve' ? 'primary' : 'danger'}
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default ListingsAdmin;
