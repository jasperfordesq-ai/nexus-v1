// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter List
 * Displays all newsletters with status filtering and CRUD actions.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
} from '@heroui/react';
import { Mail, Plus, RefreshCw, MoreVertical, Edit, Trash2, Copy, Send } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, StatusBadge, ConfirmModal, type Column } from '../../components';
import { NewsletterResend } from './NewsletterResend';

interface NewsletterItem {
  id: number;
  name: string;
  subject: string;
  status: string;
  recipients_count: number;
  open_rate: number;
  click_rate: number;
  sent_at: string | null;
  created_at: string;
}

export function NewsletterList() {
  usePageTitle('Admin - Newsletters');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [items, setItems] = useState<NewsletterItem[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [deleteTarget, setDeleteTarget] = useState<NewsletterItem | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [resendTarget, setResendTarget] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.list({ page });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const p = payload as { data?: NewsletterItem[]; meta?: { total?: number } };
          setItems(p.data || []);
          setTotal(p.meta?.total || 0);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, [page]);

  useEffect(() => { loadData(); }, [loadData]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminNewsletters.delete(deleteTarget.id);
      if (res.success) {
        toast.success(`Newsletter "${deleteTarget.name}" deleted`);
        setDeleteTarget(null);
        loadData();
      } else {
        toast.error('Failed to delete newsletter');
      }
    } catch {
      toast.error('Failed to delete newsletter');
    }
    setDeleting(false);
  };

  const handleDuplicate = async (item: NewsletterItem) => {
    try {
      const res = await adminNewsletters.get(item.id);
      if (res.success && res.data) {
        const d = res.data as Record<string, unknown>;
        const dupRes = await adminNewsletters.create({
          name: `${d.name || item.name} (Copy)`,
          subject: (d.subject as string) || item.subject,
          content: (d.content as string) || '',
          status: 'draft',
        });
        if (dupRes.success) {
          toast.success('Newsletter duplicated');
          loadData();
        }
      }
    } catch {
      toast.error('Failed to duplicate newsletter');
    }
  };

  const columns: Column<NewsletterItem>[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'subject', label: 'Subject', sortable: true },
    {
      key: 'status', label: 'Status', sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'recipients_count', label: 'Recipients',
      render: (item) => <span>{(item.recipients_count || 0).toLocaleString()}</span>,
    },
    {
      key: 'open_rate', label: 'Open Rate',
      render: (item) => <span>{item.open_rate ? `${item.open_rate}%` : '--'}</span>,
    },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions' as keyof NewsletterItem, label: 'Actions',
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light"><MoreVertical size={16} /></Button>
          </DropdownTrigger>
          <DropdownMenu aria-label="Newsletter actions" onAction={(key) => {
            if (key === 'edit') navigate(tenantPath(`/admin/newsletters/edit/${item.id}`));
            else if (key === 'duplicate') handleDuplicate(item);
            else if (key === 'resend') setResendTarget(item.id);
            else if (key === 'delete') setDeleteTarget(item);
          }}>
            <DropdownItem key="edit" startContent={<Edit size={14} />}>Edit</DropdownItem>
            <DropdownItem key="duplicate" startContent={<Copy size={14} />}>Duplicate</DropdownItem>
            <DropdownItem
              key="resend"
              startContent={<Send size={14} />}
              classNames={{ base: item.status === 'sent' ? '' : 'hidden' }}
            >
              Resend
            </DropdownItem>
            <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">Delete</DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Newsletters"
        description="Email campaign management"
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate(tenantPath('/admin/newsletters/create'))}>Create Newsletter</Button>
          </div>
        }
      />
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search newsletters..."
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        onRefresh={loadData}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8 text-default-400">
            <Mail size={40} />
            <p>No newsletters found</p>
            <p className="text-xs">Create your first newsletter to get started</p>
          </div>
        }
      />

      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title="Delete Newsletter"
          message={`Are you sure you want to delete "${deleteTarget.name}"? This cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleting}
        />
      )}

      {resendTarget && (
        <NewsletterResend
          isOpen={!!resendTarget}
          onClose={() => setResendTarget(null)}
          newsletterId={resendTarget}
          onSuccess={loadData}
        />
      )}
    </div>
  );
}

export default NewsletterList;
