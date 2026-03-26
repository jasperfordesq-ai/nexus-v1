// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Categories Management
 * Full CRUD for content categories with type filtering.
 * Parity: PHP Admin\CategoryController
 */

import { useState, type CSSProperties, useCallback, useEffect } from 'react';
import {
  Button,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Select,
  SelectItem,
  Tabs,
  Tab,
} from '@heroui/react';
import {
  Plus,
  MoreVertical,
  Edit,
  Trash2,
  FolderOpen,
  Tag,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminCategories } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { AdminCategory } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const CATEGORY_TYPES = [
  { key: 'listing', label: 'Listing' },
  { key: 'event', label: 'Event' },
  { key: 'blog', label: 'Blog' },
  { key: 'vol_opportunity', label: 'Volunteering' },
] as const;

const TYPE_COLORS: Record<string, 'primary' | 'success' | 'warning' | 'secondary'> = {
  listing: 'primary',
  event: 'success',
  blog: 'warning',
  vol_opportunity: 'secondary',
};

const TYPE_LABELS: Record<string, string> = {
  listing: 'Listing',
  event: 'Event',
  blog: 'Blog',
  vol_opportunity: 'Volunteering',
};

const COLOR_OPTIONS = [
  'blue', 'red', 'green', 'orange', 'purple', 'pink',
  'yellow', 'teal', 'indigo', 'cyan', 'fuchsia', 'gray', 'slate',
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CategoriesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('categories.page_title'));
  const toast = useToast();

  // Data state
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState('all');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<AdminCategory | null>(null);
  const [formData, setFormData] = useState({ name: '', color: 'blue', type: 'listing' });
  const [saving, setSaving] = useState(false);

  // Delete confirm state
  const [deleteTarget, setDeleteTarget] = useState<AdminCategory | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Data loading ───

  const loadCategories = useCallback(async () => {
    setLoading(true);
    const params = typeFilter !== 'all' ? { type: typeFilter } : {};
    const res = await adminCategories.list(params);
    if (res.success && res.data) {
      // The API returns { data: [...] } envelope; the api client unwraps it
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setCategories(data);
      } else if (data && typeof data === 'object' && 'data' in data) {
        setCategories((data as { data: AdminCategory[] }).data || []);
      }
    } else {
      toast.error(t('categories.failed_to_load_categories'));
    }
    setLoading(false);
  }, [typeFilter, toast]);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  // ─── Create / Edit ───

  const openCreateModal = () => {
    setEditingCategory(null);
    setFormData({ name: '', color: 'blue', type: 'listing' });
    setModalOpen(true);
  };

  const openEditModal = (category: AdminCategory) => {
    setEditingCategory(category);
    setFormData({
      name: category.name,
      color: category.color || 'blue',
      type: category.type || 'listing',
    });
    setModalOpen(true);
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditingCategory(null);
    setFormData({ name: '', color: 'blue', type: 'listing' });
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error(t('categories.category_name_is_required'));
      return;
    }

    setSaving(true);

    if (editingCategory) {
      // Update
      const res = await adminCategories.update(editingCategory.id, {
        name: formData.name.trim(),
        color: formData.color,
        type: formData.type,
      });

      if (res.success) {
        toast.success(t('content.item_updated'));
        closeModal();
        loadCategories();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('categories.failed_to_load_categories');
        toast.error(errorMsg);
      }
    } else {
      // Create
      const res = await adminCategories.create({
        name: formData.name.trim(),
        color: formData.color,
        type: formData.type,
      });

      if (res.success) {
        toast.success(t('content.item_added'));
        closeModal();
        loadCategories();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('categories.failed_to_load_categories');
        toast.error(errorMsg);
      }
    }

    setSaving(false);
  };

  // ─── Delete ───

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);

    const res = await adminCategories.delete(deleteTarget.id);
    if (res.success) {
      toast.success(t('content.item_deleted'));
      setDeleteTarget(null);
      loadCategories();
    } else {
      toast.error(t('categories.failed_to_delete_category'));
    }

    setDeleting(false);
  };

  // ─── Actions menu ───

  function CategoryActionsMenu({ category }: { category: AdminCategory }) {
    type ActionKey = 'edit' | 'delete';

    const handleMenuAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'edit') {
        openEditModal(category);
      } else if (action === 'delete') {
        setDeleteTarget(category);
      }
    };

    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light" aria-label={t('categories.label_category_actions')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={t('categories.label_category_actions')} onAction={handleMenuAction}>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            {t('breadcrumbs.edit')}
          </DropdownItem>
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            {t('common.delete')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  // ─── Table columns ───

  const columns: Column<AdminCategory>[] = [
    {
      key: 'name',
      label: t('categories.label_name'),
      sortable: true,
      render: (cat) => (
        <div className="flex items-center gap-3">
          <div
            className="h-3 w-3 rounded-full shrink-0"
            style={{ '--category-color': cat.color || 'var(--color-primary)', backgroundColor: 'var(--category-color)' } as CSSProperties}
          />
          <span className="font-medium text-foreground">{cat.name}</span>
        </div>
      ),
    },
    {
      key: 'slug',
      label: t('federation.col_slug'),
      render: (cat) => (
        <span className="text-sm text-default-500 font-mono">{cat.slug}</span>
      ),
    },
    {
      key: 'type',
      label: t('categories.label_type'),
      sortable: true,
      render: (cat) => (
        <Chip size="sm" variant="flat" color={TYPE_COLORS[cat.type] || 'default'}>
          {TYPE_LABELS[cat.type] || cat.type}
        </Chip>
      ),
    },
    {
      key: 'listing_count',
      label: t('breadcrumbs.listings'),
      sortable: true,
      render: (cat) => (
        <Chip size="sm" variant="flat" color={cat.listing_count > 0 ? 'primary' : 'default'}>
          {cat.listing_count}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: t('listings.created'),
      sortable: true,
      render: (cat) => (
        <span className="text-sm text-default-500">
          {new Date(cat.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('listings.actions'),
      render: (cat) => <CategoryActionsMenu category={cat} />,
    },
  ];

  // ─── Render ───

  return (
    <div>
      <PageHeader
        title={t('categories.categories_admin_title')}
        description={t('categories.categories_admin_desc')}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={openCreateModal}
          >
            {t('federation.add')} {t('breadcrumbs.categories')}
          </Button>
        }
      />

      {/* Type Filter Tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={typeFilter}
          onSelectionChange={(key) => setTypeFilter(key as string)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('listings.filter_all')} />
          <Tab key="listing" title={t('breadcrumbs.listings')} />
          <Tab key="event" title={t('categories.events', 'Events')} />
          <Tab key="blog" title={t('breadcrumbs.blog')} />
          <Tab key="vol_opportunity" title={t('categories.volunteering', 'Volunteering')} />
        </Tabs>
      </div>

      {categories.length === 0 && !loading ? (
        <EmptyState
          icon={FolderOpen}
          title={t('no_data')}
          description={t('categories.categories_admin_desc')}
          actionLabel={`${t('federation.add')} ${t('breadcrumbs.categories')}`}
          onAction={openCreateModal}
        />
      ) : (
        <DataTable
          columns={columns}
          data={categories}
          isLoading={loading}
          searchPlaceholder={t('data_table.search', 'Search categories...')}
          onRefresh={loadCategories}
          emptyContent={t('no_data')}
        />
      )}

      {/* ─── Create / Edit Modal ─── */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Tag size={20} />
            {editingCategory ? `${t('breadcrumbs.edit')} ${t('breadcrumbs.categories')}` : `${t('breadcrumbs.create')} ${t('breadcrumbs.categories')}`}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('categories.label_name')}
              placeholder={t('categories.placeholder_name', 'e.g. Arts & Crafts')}
              value={formData.name}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, name: v }))}
              isRequired
              variant="bordered"
              autoFocus
            />

            <Select
              label={t('categories.label_type')}
              selectedKeys={new Set([formData.type])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) setFormData((prev) => ({ ...prev, type: selected }));
              }}
              variant="bordered"
            >
              {CATEGORY_TYPES.map((t) => (
                <SelectItem key={t.key}>{t.label}</SelectItem>
              ))}
            </Select>

            <Select
              label={t('categories.label_colour')}
              selectedKeys={new Set([formData.color])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) setFormData((prev) => ({ ...prev, color: selected }));
              }}
              variant="bordered"
              renderValue={(items) => {
                return items.map((item) => (
                  <div key={item.key} className="flex items-center gap-2">
                    <div
                      className="h-3 w-3 rounded-full"
                      style={{ '--swatch-color': String(item.key), backgroundColor: 'var(--swatch-color)' } as CSSProperties}
                    />
                    <span className="capitalize">{String(item.key)}</span>
                  </div>
                ));
              }}
            >
              {COLOR_OPTIONS.map((color) => (
                <SelectItem key={color} textValue={color}>
                  <div className="flex items-center gap-2">
                    <div
                      className="h-3 w-3 rounded-full"
                      style={{ '--swatch-color': color, backgroundColor: 'var(--swatch-color)' } as CSSProperties}
                    />
                    <span className="capitalize">{color}</span>
                  </div>
                </SelectItem>
              ))}
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal} isDisabled={saving}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving} isDisabled={saving}>
              {editingCategory ? t('federation.save_changes') : `${t('breadcrumbs.create')} ${t('breadcrumbs.categories')}`}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation ─── */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete')} ${t('breadcrumbs.categories')}`}
          message={t('gamification.confirm_delete_campaign', { name: deleteTarget.name })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default CategoriesAdmin;
