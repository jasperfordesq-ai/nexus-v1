// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Segments
 * Full CRUD for audience segments used in targeted newsletter campaigns.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  useDisclosure,
} from '@heroui/react';
import Filter from 'lucide-react/icons/filter';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Users from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, ConfirmModal, type Column } from '../../components';

interface Segment {
  id: number;
  name: string;
  description: string;
  is_active: number | boolean;
  match_type: string;
  rules: string | unknown[];
  subscriber_count: number;
  created_at: string;
  updated_at: string;
}

export function Segments() {
  const { t } = useTranslation('admin');
  const { tenantPath } = useTenant();
  usePageTitle(t('newsletters.page_title'));
  const navigate = useNavigate();
  const [items, setItems] = useState<Segment[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteTarget, setDeleteTarget] = useState<Segment | null>(null);
  const [deleting, setDeleting] = useState(false);
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSegments();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Segment[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await adminNewsletters.deleteSegment(deleteTarget.id);
      setItems(prev => prev.filter(s => s.id !== deleteTarget.id));
      onDeleteClose();
    } catch {
      // Error handled silently
    }
    setDeleting(false);
    setDeleteTarget(null);
  }, [deleteTarget, onDeleteClose]);

  const columns: Column<Segment>[] = [
    {
      key: 'name',
      label: t('segment_form.label_segment_name'),
      sortable: true,
      render: (item) => (
        <div>
          <p className="font-medium text-foreground">{item.name}</p>
          {item.description && (
            <p className="text-xs text-default-400 mt-0.5 line-clamp-1">{item.description}</p>
          )}
        </div>
      ),
    },
    {
      key: 'is_active',
      label: t('newsletter_segments.col_status'),
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={item.is_active ? 'success' : 'default'}
        >
          {item.is_active ? t('segment_form.status_active') : t('segment_form.status_inactive')}
        </Chip>
      ),
    },
    {
      key: 'match_type',
      label: t('segment_form.label_match_logic'),
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {item.match_type === 'any' ? t('newsletter_segments.match_any') : t('newsletter_segments.match_all')}
        </Chip>
      ),
    },
    {
      key: 'subscriber_count',
      label: t('newsletter_segments.col_members'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1.5">
          <Users size={14} className="text-default-400" />
          <span>{(item.subscriber_count || 0).toLocaleString()}</span>
        </div>
      ),
    },
    {
      key: 'created_at',
      label: t('newsletter_segments.col_created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (item) => (
        <div className="flex justify-end">
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={t('newsletters.col_actions')}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label={t('newsletters.col_actions')}>
              <DropdownItem
                key="edit"
                startContent={<Pencil size={14} />}
                onPress={() => navigate(tenantPath(`/admin/newsletters/segments/edit/${item.id}`))}
              >
                {t('newsletters.edit')}
              </DropdownItem>
              <DropdownItem
                key="delete"
                startContent={<Trash2 size={14} />}
                className="text-danger"
                color="danger"
                onPress={() => {
                  setDeleteTarget(item);
                  onDeleteOpen();
                }}
              >
                {t('newsletters.delete')}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        </div>
      ),
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title={t('newsletter_segments.title')}
          description={t('newsletter_segments.description')}
          actions={
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters/segments/create'))}
            >
              {t('segment_form.btn_create_segment')}
            </Button>
          }
        />
        <EmptyState
          icon={Filter}
          title={t('newsletter_segments.empty_title')}
          description={t('newsletter_segments.empty_description')}
          actionLabel={t('newsletter_segments.create_first_segment')}
          onAction={() => navigate(tenantPath('/admin/newsletters/segments/create'))}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('newsletter_segments.title')}
        description={t('newsletter_segments.description')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
            >
              {t('newsletters.refresh')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters/segments/create'))}
            >
              {t('segment_form.btn_create_segment')}
            </Button>
          </div>
        }
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />

      <ConfirmModal
        isOpen={isDeleteOpen}
        onClose={() => {
          onDeleteClose();
          setDeleteTarget(null);
        }}
        onConfirm={handleDelete}
        title={t('newsletter_segments.delete_title')}
        message={t('newsletter_segments.delete_confirm', { name: deleteTarget?.name })}
        confirmLabel={t('newsletter_segments.delete_confirm_label')}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Segments;
