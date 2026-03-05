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
import {
  Button,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  useDisclosure,
} from '@heroui/react';
import { Filter, Plus, RefreshCw, MoreVertical, Pencil, Trash2, Users } from 'lucide-react';
import { usePageTitle } from '@/hooks';
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
  usePageTitle('Admin - Segments');
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
      label: 'Segment Name',
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
      label: 'Status',
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={item.is_active ? 'success' : 'default'}
        >
          {item.is_active ? 'Active' : 'Inactive'}
        </Chip>
      ),
    },
    {
      key: 'match_type',
      label: 'Match Type',
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {item.match_type === 'any' ? 'ANY (OR)' : 'ALL (AND)'}
        </Chip>
      ),
    },
    {
      key: 'subscriber_count',
      label: 'Members',
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
      label: 'Created',
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
              <Button isIconOnly size="sm" variant="light" aria-label="Actions">
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label="Segment actions">
              <DropdownItem
                key="edit"
                startContent={<Pencil size={14} />}
                onPress={() => navigate(`/admin/newsletters/segments/edit/${item.id}`)}
              >
                Edit
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
                Delete
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
          title="Segments"
          description="Audience segments for targeted campaigns"
          actions={
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate('/admin/newsletters/segments/create')}
            >
              Create Segment
            </Button>
          }
        />
        <EmptyState
          icon={Filter}
          title="No Segments Created"
          description="Create audience segments to target specific groups of subscribers with tailored content."
          actionLabel="Create Your First Segment"
          onAction={() => navigate('/admin/newsletters/segments/create')}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Segments"
        description="Audience segments for targeted campaigns"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
            >
              Refresh
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate('/admin/newsletters/segments/create')}
            >
              Create Segment
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
        title="Delete Segment"
        message={`Are you sure you want to delete "${deleteTarget?.name}"? This action cannot be undone. Newsletters using this segment will no longer have a target audience.`}
        confirmLabel="Delete Segment"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default Segments;
