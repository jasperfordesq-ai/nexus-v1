// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Resource Categories Management
 * CRUD for resource_categories (Knowledge Base categories).
 * Uses the /v2/resources/categories API endpoints.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import { Plus, Pencil, Trash2, FolderTree, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ResourceCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  icon: string | null;
  parent_id: number | null;
}

interface CategoryFormData {
  name: string;
  description: string;
  sort_order: number;
  icon: string;
}

const EMPTY_FORM: CategoryFormData = {
  name: '',
  description: '',
  sort_order: 0,
  icon: '',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function ResourceCategoriesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('resources.categories_page_title', 'Resource Categories'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [items, setItems] = useState<ResourceCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<ResourceCategory | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<ResourceCategory | null>(null);
  const [form, setForm] = useState<CategoryFormData>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);

  // ── Data fetching ────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/v2/resources/categories/tree?flat=1');

      if (res.success && res.data) {
        const data = res.data;
        if (Array.isArray(data)) {
          setItems(data);
        } else if (Array.isArray((data as { categories?: ResourceCategory[] }).categories)) {
          setItems((data as { categories: ResourceCategory[] }).categories);
        }
      }
    } catch {
      toast.error(t('resources.failed_to_load_resources', 'Failed to load categories'));
    } finally {
      setLoading(false);
    }
  }, [toast]);


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Modal handlers ───────────────────────────────────────────────────────

  function openCreateModal() {
    setEditingItem(null);
    setForm(EMPTY_FORM);
    setErrors({});
    setIsModalOpen(true);
  }

  function openEditModal(item: ResourceCategory) {
    setEditingItem(item);
    setForm({
      name: item.name,
      description: item.description || '',
      sort_order: item.sort_order,
      icon: item.icon || '',
    });
    setErrors({});
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingItem(null);
    setForm(EMPTY_FORM);
    setErrors({});
  }

  // ── Form validation ──────────────────────────────────────────────────────

  function validate(): boolean {
    const newErrors: Record<string, string> = {};

    if (!form.name.trim()) {
      newErrors.name = t('blog.title_required', 'Name is required');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  // ── Submit handler ───────────────────────────────────────────────────────

  async function handleSubmit() {
    if (!validate()) return;

    setSubmitting(true);

    try {
      const payload = {
        name: form.name.trim(),
        slug: form.name.trim().toLowerCase().replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, ''),
        description: form.description.trim() || undefined,
        sort_order: form.sort_order,
        icon: form.icon.trim() || undefined,
      };

      const res = editingItem
        ? await api.put(`/v2/resources/categories/${editingItem.id}`, payload)
        : await api.post('/v2/resources/categories', payload);

      if (res.success) {
        toast.success(
          editingItem
            ? t('resources.category_updated', 'Category updated successfully')
            : t('resources.category_created', 'Category created successfully')
        );
        closeModal();
        loadItems();
      } else {
        toast.error(res.error || t('resources.an_unexpected_error_occurred', 'An unexpected error occurred'));
      }
    } catch {
      toast.error(t('resources.an_unexpected_error_occurred', 'An unexpected error occurred'));
    } finally {
      setSubmitting(false);
    }
  }

  // ── Delete handler ───────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await api.delete(`/v2/resources/categories/${confirmDelete.id}`);
      if (res?.success) {
        toast.success(t('resources.category_deleted', 'Category deleted successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('resources.an_unexpected_error_occurred', 'An unexpected error occurred'));
      }
    } catch {
      toast.error(t('resources.an_unexpected_error_occurred', 'An unexpected error occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Column definitions ───────────────────────────────────────────────────

  const columns: Column<ResourceCategory>[] = [
    {
      key: 'name',
      label: "Name",
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.name}</span>
      ),
    },
    {
      key: 'slug',
      label: t('federation.col_slug', 'Slug'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.slug}</span>
      ),
    },
    {
      key: 'description',
      label: t('content.label_description', 'Description'),
      render: (item) => (
        <span className="text-sm text-default-500 line-clamp-2">
          {item.description || '--'}
        </span>
      ),
    },
    {
      key: 'sort_order',
      label: t('resources.sort_order', 'Sort Order'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.sort_order}</span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => openEditModal(item)}
            aria-label={t('resources.label_edit_category', 'Edit category')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('resources.label_delete_category', 'Delete category')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('resources.categories_page_title', 'Resource Categories')}
        description={t('resources.categories_page_desc', 'Manage Knowledge Base categories')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/resources'))}
            >
              {t('common.back', 'Back')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={openCreateModal}
            >
              {t('resources.new_category', 'New Category')}
            </Button>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={t('data_table.search', 'Search categories...')}
        onRefresh={loadItems}
        totalItems={items.length}
        emptyContent={
          <EmptyState
            icon={FolderTree}
            title={t('no_data', 'No data')}
            description={t('resources.categories_page_desc', 'Manage Knowledge Base categories')}
          />
        }
      />

      {/* Create / Edit Modal */}
      <Modal isOpen={isModalOpen} onClose={closeModal} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingItem
              ? `${t('breadcrumbs.edit', 'Edit')}: ${editingItem.name}`
              : t('resources.new_category', 'New Category')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={"Name"}
              placeholder={t('resources.category_name_placeholder', 'Enter category name')}
              value={form.name}
              onValueChange={(val) => setForm((prev) => ({ ...prev, name: val }))}
              isRequired
              isInvalid={!!errors.name}
              errorMessage={errors.name}
              isDisabled={submitting}
            />
            <Textarea
              label={t('content.label_description', 'Description')}
              placeholder={t('resources.category_desc_placeholder', 'Optional description')}
              value={form.description}
              onValueChange={(val) => setForm((prev) => ({ ...prev, description: val }))}
              minRows={2}
              maxRows={4}
              isDisabled={submitting}
            />
            <Input
              label={t('resources.sort_order', 'Sort Order')}
              type="number"
              placeholder="0"
              value={String(form.sort_order)}
              onValueChange={(val) =>
                setForm((prev) => ({ ...prev, sort_order: parseInt(val, 10) || 0 }))
              }
              isDisabled={submitting}
            />
            <Input
              label={t('resources.icon_label', 'Icon')}
              placeholder={t('resources.icon_placeholder', 'Lucide icon name (e.g. book-open)')}
              value={form.icon}
              onValueChange={(val) => setForm((prev) => ({ ...prev, icon: val }))}
              isDisabled={submitting}
              description={t('resources.icon_desc', 'Optional Lucide React icon name')}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal} isDisabled={submitting}>
              {t('cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleSubmit}
              isLoading={submitting}
              isDisabled={submitting}
            >
              {editingItem
                ? t('federation.save_changes', 'Save Changes')
                : t('resources.new_category', 'New Category')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete', 'Delete')} ${t('breadcrumbs.categories', 'Category')}`}
          message={`Delete Campaign`}
          confirmLabel={t('common.delete', 'Delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default ResourceCategoriesAdmin;
