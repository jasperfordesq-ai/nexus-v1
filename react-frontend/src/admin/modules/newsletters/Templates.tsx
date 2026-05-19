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
import { useTranslation } from 'react-i18next';
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
import FileText from 'lucide-react/icons/file-text';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Pencil from 'lucide-react/icons/pencil';
import Copy from 'lucide-react/icons/copy';
import Eye from 'lucide-react/icons/eye';
import Trash2 from 'lucide-react/icons/trash-2';
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


const CATEGORY_COLORS: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'default'> = {
  starter: 'primary',
  saved: 'secondary',
  custom: 'success',
};

export function Templates() {
  const { t } = useTranslation('admin');
  usePageTitle(t('newsletters.page_title'));

  const CATEGORY_LABELS: Record<string, string> = {
    starter: t('newsletter_templates.category_starter'),
    saved: t('newsletter_templates.category_saved'),
    custom: t('newsletter_templates.category_custom'),
  };
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
        toast.success(t('newsletter_templates.template_duplicated'));
        loadData();
      } else {
        toast.error(res.error || t('newsletter_templates.failed_to_duplicate_template'));
      }
    } catch {
      toast.error(t('newsletters.an_unexpected_error_occurred'));
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await adminNewsletters.deleteTemplate(deleteTarget.id);
      if (res.success) {
        toast.success(t('newsletter_templates.template_deleted'));
        setDeleteTarget(null);
        loadData();
      } else {
        toast.error(res.error || t('newsletter_templates.failed_to_delete_template'));
      }
    } catch {
      toast.error(t('newsletters.an_unexpected_error_occurred'));
    } finally {
      setDeleting(false);
    }
  }

  function renderActions(template: Template) {
    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light" aria-label={t('newsletters.col_actions')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu
          aria-label={t('newsletters.col_actions')}
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
            {t('newsletters.edit')}
          </DropdownItem>
          <DropdownItem key="duplicate" startContent={<Copy size={14} />}>
            {t('newsletters.duplicate')}
          </DropdownItem>
          <DropdownItem key="preview" startContent={<Eye size={14} />}>
            {t('newsletters.preview')}
          </DropdownItem>
          <DropdownItem
            key="delete"
            startContent={<Trash2 size={14} />}
            className="text-danger"
            color="danger"
          >
            {t('newsletters.delete')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<Template>[] = [
    {
      key: 'name',
      label: t('newsletter_templates.col_template_name'),
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
      label: t('template_form.label_category'),
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
      label: t('template_form.label_default_subject_line'),
      render: (item) => (
        <span className="text-sm text-default-600 line-clamp-1">
          {item.subject || '--'}
        </span>
      ),
    },
    {
      key: 'is_active',
      label: t('newsletter_templates.col_status'),
      render: (item) => (
        <Chip
          size="sm"
          variant="dot"
          color={item.is_active ? 'success' : 'default'}
        >
          {item.is_active ? t('template_form.active') : t('template_form.inactive')}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: t('newsletter_templates.col_created'),
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
          title={t('newsletter_templates.title')}
          description={t('newsletter_templates.description')}
          actions={
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
            >
              {t('template_form.create_template')}
            </Button>
          }
        />
        <EmptyState
          icon={FileText}
          title={t('newsletter_templates.empty_title')}
          description={t('newsletter_templates.empty_description')}
          actionLabel={t('template_form.create_template')}
          onAction={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('newsletter_templates.title')}
        description={t('newsletter_templates.description')}
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
              onPress={() => navigate(tenantPath('/admin/newsletters/templates/create'))}
            >
              {t('template_form.create_template')}
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
          <Tab key="all" title={t('newsletters.tab_all')} />
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
        title={t('newsletter_templates.delete_title')}
        message={t('newsletter_templates.delete_confirm', { name: deleteTarget?.name })}
        confirmLabel={t('newsletter_templates.delete_confirm_label')}
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
