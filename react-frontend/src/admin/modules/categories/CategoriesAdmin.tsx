// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Categories Management
 * Full CRUD for content categories with type filtering.
 * Parity: PHP Admin\CategoryController
 */

import { useState, useCallback, useEffect } from 'react';
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
import { useTenant, useToast } from '@/contexts';
import { adminCategories } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { AdminCategory } from '../../api/types';

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
  usePageTitle('Admin - Categories');
  const { tenantPath: _tenantPath } = useTenant();
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
      toast.error('Failed to load categories');
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
      toast.error('Category name is required');
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
        toast.success(`Category "${formData.name.trim()}" updated`);
        closeModal();
        loadCategories();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || 'Failed to update category';
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
        toast.success(`Category "${formData.name.trim()}" created`);
        closeModal();
        loadCategories();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || 'Failed to create category';
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
      const unassigned = (res.data as { listings_unassigned?: number } | undefined)?.listings_unassigned || 0;
      const msg = unassigned > 0
        ? `Category "${deleteTarget.name}" deleted. ${unassigned} listing(s) unassigned.`
        : `Category "${deleteTarget.name}" deleted`;
      toast.success(msg);
      setDeleteTarget(null);
      loadCategories();
    } else {
      toast.error('Failed to delete category');
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
          <Button isIconOnly size="sm" variant="light">
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label="Category actions" onAction={handleMenuAction}>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            Edit
          </DropdownItem>
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            Delete
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  // ─── Table columns ───

  const columns: Column<AdminCategory>[] = [
    {
      key: 'name',
      label: 'Name',
      sortable: true,
      render: (cat) => (
        <div className="flex items-center gap-3">
          <div
            className="h-3 w-3 rounded-full shrink-0"
            style={{ backgroundColor: cat.color || 'var(--color-primary)' }}
          />
          <span className="font-medium text-foreground">{cat.name}</span>
        </div>
      ),
    },
    {
      key: 'slug',
      label: 'Slug',
      render: (cat) => (
        <span className="text-sm text-default-500 font-mono">{cat.slug}</span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (cat) => (
        <Chip size="sm" variant="flat" color={TYPE_COLORS[cat.type] || 'default'}>
          {TYPE_LABELS[cat.type] || cat.type}
        </Chip>
      ),
    },
    {
      key: 'listing_count',
      label: 'Listings',
      sortable: true,
      render: (cat) => (
        <Chip size="sm" variant="flat" color={cat.listing_count > 0 ? 'primary' : 'default'}>
          {cat.listing_count}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (cat) => (
        <span className="text-sm text-default-500">
          {new Date(cat.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (cat) => <CategoryActionsMenu category={cat} />,
    },
  ];

  // ─── Render ───

  return (
    <div>
      <PageHeader
        title="Categories"
        description="Manage content categories for listings, events, blog posts, and volunteering"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={openCreateModal}
          >
            Add Category
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
          <Tab key="all" title="All Types" />
          <Tab key="listing" title="Listings" />
          <Tab key="event" title="Events" />
          <Tab key="blog" title="Blog" />
          <Tab key="vol_opportunity" title="Volunteering" />
        </Tabs>
      </div>

      {categories.length === 0 && !loading ? (
        <EmptyState
          icon={FolderOpen}
          title="No categories found"
          description={
            typeFilter !== 'all'
              ? `No ${TYPE_LABELS[typeFilter] || typeFilter} categories exist yet.`
              : 'Create your first category to organise content.'
          }
          actionLabel="Add Category"
          onAction={openCreateModal}
        />
      ) : (
        <DataTable
          columns={columns}
          data={categories}
          isLoading={loading}
          searchPlaceholder="Search categories..."
          onRefresh={loadCategories}
          emptyContent="No categories match your search"
        />
      )}

      {/* ─── Create / Edit Modal ─── */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Tag size={20} />
            {editingCategory ? 'Edit Category' : 'Create Category'}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="Name"
              placeholder="e.g. Arts & Crafts"
              value={formData.name}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, name: v }))}
              isRequired
              variant="bordered"
              autoFocus
            />

            <Select
              label="Type"
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
              label="Colour"
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
                      style={{ backgroundColor: String(item.key) }}
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
                      style={{ backgroundColor: color }}
                    />
                    <span className="capitalize">{color}</span>
                  </div>
                </SelectItem>
              ))}
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal} isDisabled={saving}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingCategory ? 'Save Changes' : 'Create Category'}
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
          title="Delete Category"
          message={
            deleteTarget.listing_count > 0
              ? `Are you sure you want to delete "${deleteTarget.name}"? This category has ${deleteTarget.listing_count} listing(s) assigned. They will be unassigned from this category.`
              : `Are you sure you want to delete "${deleteTarget.name}"? This action cannot be undone.`
          }
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default CategoriesAdmin;
