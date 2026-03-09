// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter List
 * Displays all newsletters with status filtering, send, duplicate, and CRUD actions.
 * Parity: PHP Admin\NewsletterController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Chip,
} from '@heroui/react';
import { Mail, Plus, RefreshCw, MoreVertical, Edit, Trash2, Copy, Send, BarChart3, Activity } from 'lucide-react';
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
  total_recipients: number;
  open_rate: number;
  click_rate: number;
  sent_at: string | null;
  created_at: string;
  is_recurring: boolean;
  ab_test_enabled: boolean;
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
  const [sendTarget, setSendTarget] = useState<NewsletterItem | null>(null);
  const [sendingId, setSendingId] = useState<number | null>(null);

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
        toast.success(`Newsletter "${deleteTarget.subject || deleteTarget.name}" deleted`);
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
      const res = await adminNewsletters.duplicateNewsletter(item.id);
      if (res.success) {
        toast.success('Newsletter duplicated as draft');
        loadData();
      } else {
        toast.error('Failed to duplicate newsletter');
      }
    } catch {
      toast.error('Failed to duplicate newsletter');
    }
  };

  const handleSendNow = async () => {
    if (!sendTarget) return;
    setSendingId(sendTarget.id);
    try {
      const res = await adminNewsletters.sendNewsletter(sendTarget.id);
      if (res.success) {
        const data = res.data as { queued?: number; message?: string };
        toast.success(data.message || 'Newsletter queued for sending');
        setSendTarget(null);
        loadData();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to send newsletter');
      }
    } catch {
      toast.error('Failed to send newsletter');
    }
    setSendingId(null);
  };

  const columns: Column<NewsletterItem>[] = [
    {
      key: 'subject', label: 'Subject', sortable: true,
      render: (item) => (
        <div className="min-w-0">
          <p className="font-medium truncate">{item.subject || item.name}</p>
          <div className="flex gap-1 mt-1">
            {item.ab_test_enabled && <Chip size="sm" color="warning" variant="flat">A/B</Chip>}
            {item.is_recurring && <Chip size="sm" color="secondary" variant="flat">Recurring</Chip>}
          </div>
        </div>
      ),
    },
    {
      key: 'status', label: 'Status', sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'recipients_count', label: 'Recipients',
      render: (item) => <span>{((item.total_recipients || item.recipients_count) || 0).toLocaleString()}</span>,
    },
    {
      key: 'open_rate', label: 'Open Rate',
      render: (item) => <span>{item.open_rate ? `${item.open_rate}%` : '--'}</span>,
    },
    {
      key: 'click_rate', label: 'Click Rate',
      render: (item) => <span>{item.click_rate ? `${item.click_rate}%` : '--'}</span>,
    },
    {
      key: 'created_at', label: 'Date', sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.sent_at
            ? new Date(item.sent_at).toLocaleDateString()
            : item.created_at
              ? new Date(item.created_at).toLocaleDateString()
              : '--'}
        </span>
      ),
    },
    {
      key: 'actions' as keyof NewsletterItem, label: 'Actions',
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label="Newsletter actions"><MoreVertical size={16} /></Button>
          </DropdownTrigger>
          <DropdownMenu aria-label="Newsletter actions" onAction={(key) => {
            if (key === 'edit') navigate(tenantPath(`/admin/newsletters/edit/${item.id}`));
            else if (key === 'stats') navigate(tenantPath(`/admin/newsletters/${item.id}/stats`));
            else if (key === 'activity') navigate(tenantPath(`/admin/newsletters/${item.id}/activity`));
            else if (key === 'duplicate') handleDuplicate(item);
            else if (key === 'send') setSendTarget(item);
            else if (key === 'resend') setResendTarget(item.id);
            else if (key === 'delete') setDeleteTarget(item);
          }}>
            <DropdownItem key="edit" startContent={<Edit size={14} />}>Edit</DropdownItem>
            <DropdownItem
              key="send"
              startContent={<Send size={14} />}
              className={item.status === 'draft' || item.status === 'scheduled' ? '' : 'hidden'}
            >
              Send Now
            </DropdownItem>
            <DropdownItem
              key="stats"
              startContent={<BarChart3 size={14} />}
              className={item.status === 'sent' || item.status === 'sending' ? '' : 'hidden'}
            >
              Stats
            </DropdownItem>
            <DropdownItem
              key="activity"
              startContent={<Activity size={14} />}
              className={item.status === 'sent' ? '' : 'hidden'}
            >
              Activity Log
            </DropdownItem>
            <DropdownItem key="duplicate" startContent={<Copy size={14} />}>Duplicate</DropdownItem>
            <DropdownItem
              key="resend"
              startContent={<Send size={14} />}
              className={item.status === 'sent' ? '' : 'hidden'}
            >
              Resend to Non-Openers
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
          message={`Are you sure you want to delete "${deleteTarget.subject || deleteTarget.name}"? This cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleting}
        />
      )}

      {sendTarget && (
        <ConfirmModal
          isOpen={!!sendTarget}
          onClose={() => setSendTarget(null)}
          onConfirm={handleSendNow}
          title="Send Newsletter Now"
          message={`Are you sure you want to send "${sendTarget.subject || sendTarget.name}" to all targeted recipients? This action cannot be undone.`}
          confirmLabel="Send Now"
          confirmColor="primary"
          isLoading={sendingId === sendTarget.id}
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
