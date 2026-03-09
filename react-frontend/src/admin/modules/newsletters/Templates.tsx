// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Templates — Full CRUD list with actions.
 * Manage reusable email templates for newsletter campaigns.
 * Parity: PHP Admin newsletter template management.
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
  Tabs,
  Tab,
} from '@heroui/react';
import {
  FileText,
  Plus,
  RefreshCw,
  MoreVertical,
  Pencil,
  Copy,
  Eye,
  Trash2,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import {
  DataTable,
  PageHeader,
  EmptyState,
  ConfirmModal,
  type Column,
} from '../../components';
import { TemplatePreview } from './TemplatePreview';

interface Template {
  id: number;
  name: string;
  description: string;
  category: string;
  is_active: number | boolean;
  subject: string;
  preview_text: string;
  content: string;
  usage_count?: number;
  created_at: string;
  updated_at: string;
}

const CATEGORY_LABELS: Record<string, string> = {
  starter: 'Starter',
  saved: 'Saved',
  custom: 'Custom',
};

const CATEGORY_COLORS: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'default'> = {
  starter: 'primary',
  saved: 'secondary',
  custom: 'success',
};

export function Templates() {
  usePageTitle('Admin - Templates');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [categoryFilter, setCategoryFilter] = useState<string>('all');

  // Delete modal state
  const [deleteTarget, setDeleteTarget] = useState<Template | null>(null);
  const [deleting, setDeleting] = useState(false);

  // Preview modal state
  const [previewTarget, setPreviewTarget] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getTemplates();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Template[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const filteredItems =
    categoryFilter === 'all'
      ? items
      : items.filter((t) => t.category === categoryFilter);

  async function handleDuplicate(template: Template) {
    try {
      const res = await adminNewsletters.duplicateTemplate(template.id);
      if (res.success) {
        toast.success(`Template duplicated as "${template.name} (Copy)"`);
        loadData();
      } else {
        toast.error(res.error || 'Failed to duplicate template');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminNewsletters.deleteTemplate(deleteTarget.id);
      if (res.success) {
        toast.success(`Template "${deleteTarget.name}" deleted`);
        setDeleteTarget(null);
        loadData();
      } else {
        toast.error(res.error || 'Failed to delete template');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setDeleting(false);
    }
  }

  function renderActions(template: Template) {
    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light" aria-label="Template actions">
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu
          aria-label="Template actions"
          onAction={(key) => {
            switch (key) {
              case 'edit':
                navigate(tenantPath(`/admin/newsletters/templates/edit/${template.id}`));
                break;
              case 'duplicate':
                handleDuplicate(template);
                break;
              case 'preview':
                setPreviewTarget(template.id);
                break;
              case 'delete':
                setDeleteTarget(template);
                break;
            }
          }}
        >
          <DropdownItem key="edit" startContent={<Pencil size={14} />}>
            Edit
          </DropdownItem>
          <DropdownItem key="duplicate" startContent={<Copy size={14} />}>
            Duplicate
          </DropdownItem>
          <DropdownItem key="preview" startContent={<Eye size={14} />}>
            Preview
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

  const columns: Column<Template>[] = [
    {
      key: 'name',
      label: 'Template Name',
      sortable: true,
      render: (item) => (
        <div>
          <p className="font-medium">{item.name}</p>
          {item.description && (
            <p className="text-xs text-default-400 line-clamp-1">{item.description}</p>
          )}
        </div>
      ),
    },
    {
      key: 'category',
      label: 'Category',
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={CATEGORY_COLORS[item.category] || 'default'}
        >
          {CATEGORY_LABELS[item.category] || item.category}
        </Chip>
      ),
    },
    {
      key: 'subject',
      label: 'Default Subject',
      render: (item) => (
        <span className="text-sm text-default-600 line-clamp-1">
          {item.subject || '--'}
        </span>
      ),
    },
    {
      key: 'is_active',
      label: 'Status',
      render: (item) => (
        <Chip
          size="sm"
          variant="dot"
          color={item.is_active ? 'success' : 'default'}
        >
          {item.is_active ? 'Active' : 'Inactive'}
        </Chip>
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
      key: 'actions' as keyof Template,
      label: '',
      render: (item) => renderActions(item),
    },
  ];

  // Category counts for filter tabs
  const categoryCounts = items.reduce(
    (acc, t) => {
      acc[t.category] = (acc[t.category] || 0) + 1;
      return acc;
    },
    {} as Record<string, number>,
  );

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title="Templates"
          description="Reusable email templates"
          actions={
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
            >
              Create Template
            </Button>
          }
        />
        <EmptyState
          icon={FileText}
          title="No Templates Created"
          description="Create reusable email templates to speed up newsletter creation."
          actionLabel="Create Template"
          onAction={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Templates"
        description="Reusable email templates"
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
              onPress={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
            >
              Create Template
            </Button>
          </div>
        }
      />

      {/* Category filter tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={categoryFilter}
          onSelectionChange={(key) => setCategoryFilter(key as string)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={`All (${items.length})`} />
          {Object.entries(categoryCounts).map(([cat, count]) => (
            <Tab key={cat} title={`${CATEGORY_LABELS[cat] || cat} (${count})`} />
          ))}
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={filteredItems}
        isLoading={loading}
        onRefresh={loadData}
      />

      {/* Delete confirmation */}
      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Template"
        message={`Are you sure you want to delete "${deleteTarget?.name}"? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleting}
      />

      {/* Preview modal */}
      {previewTarget !== null && (
        <TemplatePreview
          templateId={previewTarget}
          isOpen={true}
          onClose={() => setPreviewTarget(null)}
        />
      )}
    </div>
  );
}

export default Templates;
