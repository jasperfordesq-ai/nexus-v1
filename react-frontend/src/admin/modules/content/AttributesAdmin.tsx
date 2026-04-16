// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Attributes Admin
 * Full CRUD for custom listing attributes.
 * Parity: PHP AdminCategoriesApiController (attributes endpoints)
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
  Switch,
} from '@heroui/react';
import { Tags, Plus, MoreVertical, Edit, Trash2, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminAttributes, adminCategories } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { AdminAttribute, AdminCategory } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ATTRIBUTE_TYPES = [
  { key: 'checkbox', label: 'Checkbox' },
  { key: 'text', label: 'Text' },
  { key: 'number', label: 'Number' },
  { key: 'select', label: 'Select' },
  { key: 'radio', label: 'Radio' },
  { key: 'date', label: 'Date' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function AttributesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('content.page_title'));
  const toast = useToast();

  // Data state
  const [items, setItems] = useState<AdminAttribute[]>([]);
  const [loading, setLoading] = useState(true);
  const [categories, setCategories] = useState<AdminCategory[]>([]);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<AdminAttribute | null>(null);
  const [formData, setFormData] = useState({ name: '', type: 'checkbox', category_id: '' as string, is_active: true });
  const [saving, setSaving] = useState(false);

  // Delete confirm state
  const [deleteTarget, setDeleteTarget] = useState<AdminAttribute | null>(null);
  const [deleting, setDeleting] = useState(false);

  // ─── Data loading ───

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminAttributes.list();
      if (res.success && res.data) {
        if (Array.isArray(res.data)) {
          setItems(res.data as AdminAttribute[]);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  const loadCategories = useCallback(async () => {
    try {
      const res = await adminCategories.list();
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setCategories(data);
        } else if (data && typeof data === 'object' && 'data' in data) {
          setCategories((data as { data: AdminCategory[] }).data || []);
        }
      }
    } catch {
      // Categories are optional for the form
    }
  }, []);

  useEffect(() => {
    loadData();
    loadCategories();
  }, [loadData, loadCategories]);

  // ─── Create / Edit ───

  const openCreateModal = () => {
    setEditingItem(null);
    setFormData({ name: '', type: 'checkbox', category_id: '', is_active: true });
    setModalOpen(true);
  };

  const openEditModal = (item: AdminAttribute) => {
    setEditingItem(item);
    setFormData({
      name: item.name,
      type: item.type || 'checkbox',
      category_id: item.category_id ? String(item.category_id) : '',
      is_active: item.is_active !== false,
    });
    setModalOpen(true);
  };

  const closeModal = () => {
    setModalOpen(false);
    setEditingItem(null);
    setFormData({ name: '', type: 'checkbox', category_id: '', is_active: true });
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error(t('content.attribute_name_is_required'));
      return;
    }

    setSaving(true);

    if (editingItem) {
      const res = await adminAttributes.update(editingItem.id, {
        name: formData.name.trim(),
        type: formData.type,
        category_id: formData.category_id ? Number(formData.category_id) : null,
        is_active: formData.is_active,
      });

      if (res.success) {
        toast.success(t('content.item_updated'));
        closeModal();
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('content.an_unexpected_error_occurred');
        toast.error(errorMsg);
      }
    } else {
      const res = await adminAttributes.create({
        name: formData.name.trim(),
        type: formData.type,
        category_id: formData.category_id ? Number(formData.category_id) : null,
      });

      if (res.success) {
        toast.success(t('content.item_added'));
        closeModal();
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('content.an_unexpected_error_occurred');
        toast.error(errorMsg);
      }
    }

    setSaving(false);
  };

  // ─── Delete ───

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);

    const res = await adminAttributes.delete(deleteTarget.id);
    if (res.success) {
      toast.success(t('content.item_deleted'));
      setDeleteTarget(null);
      loadData();
    } else {
      toast.error(t('content.failed_to_delete_attribute'));
    }

    setDeleting(false);
  };

  // ─── Actions menu ───

  function AttributeActionsMenu({ item }: { item: AdminAttribute }) {
    type ActionKey = 'edit' | 'delete';

    const handleMenuAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'edit') {
        openEditModal(item);
      } else if (action === 'delete') {
        setDeleteTarget(item);
      }
    };

    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light" aria-label={t('content.label_attribute_actions')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={t('content.label_attribute_actions')} onAction={handleMenuAction}>
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

  const columns: Column<AdminAttribute>[] = [
    {
      key: 'name',
      label: t('content.label_name'),
      sortable: true,
      render: (item) => <span className="font-medium text-foreground">{item.name}</span>,
    },
    { key: 'slug', label: t('content.label_slug'), render: (item) => <span className="text-sm text-default-500 font-mono">{item.slug}</span> },
    {
      key: 'type',
      label: t('content.label_type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'category_name',
      label: t('breadcrumbs.categories'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category_name || '--'}</span>
      ),
    },
    {
      key: 'is_active',
      label: t('listings.status'),
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? t('content.label_active') : t('reports.label_inactive', 'Inactive')}
        </Chip>
      ),
    },
    {
      key: 'actions',
      label: t('listings.actions'),
      render: (item) => <AttributeActionsMenu item={item} />,
    },
  ];

  // ─── Render ───

  return (
    <div>
      <PageHeader
        title={t('content.attributes_admin_title')}
        description={t('content.attributes_admin_desc')}
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('common.refresh')}</Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={openCreateModal}>{t('breadcrumbs.create')} {t('breadcrumbs.attributes')}</Button>
          </div>
        }
      />

      {items.length === 0 && !loading ? (
        <EmptyState
          icon={Tags}
          title={t('no_data')}
          description={t('content.desc_create_custom_attributes_to_add_extra_fi')}
          actionLabel={`${t('breadcrumbs.create')} ${t('breadcrumbs.attributes')}`}
          onAction={openCreateModal}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder={t('data_table.search', 'Search attributes...')}
          onRefresh={loadData}
          emptyContent={t('no_data')}
        />
      )}

      {/* ─── Create / Edit Modal ─── */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Tags size={20} />
            {editingItem ? `${t('breadcrumbs.edit')} ${t('breadcrumbs.attributes')}` : `${t('breadcrumbs.create')} ${t('breadcrumbs.attributes')}`}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('content.label_name')}
              placeholder={t('content.placeholder_eg_skill_level')}
              value={formData.name}
              onValueChange={(v) => setFormData((prev) => ({ ...prev, name: v }))}
              isRequired
              variant="bordered"
              autoFocus
            />

            <Select
              label={t('content.label_input_type')}
              selectedKeys={new Set([formData.type])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) setFormData((prev) => ({ ...prev, type: selected }));
              }}
              variant="bordered"
            >
              {ATTRIBUTE_TYPES.map((attrType) => (
                <SelectItem key={attrType.key}>{t(`content.attr_type_${attrType.key}`)}</SelectItem>
              ))}
            </Select>

            <Select
              label={`${t('breadcrumbs.categories')} (${t('content.placeholder_optional')})`}
              selectedKeys={formData.category_id ? new Set([formData.category_id]) : new Set()}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string | undefined;
                setFormData((prev) => ({ ...prev, category_id: selected || '' }));
              }}
              variant="bordered"
            >
              {categories.map((cat) => (
                <SelectItem key={String(cat.id)}>{cat.name}</SelectItem>
              ))}
            </Select>

            {editingItem && (
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">{t('content.label_active')}</p>
                  <p className="text-sm text-default-500">{t('content.label_active_desc', 'Whether this attribute is available for use')}</p>
                </div>
                <Switch
                  isSelected={formData.is_active}
                  onValueChange={(v) => setFormData((prev) => ({ ...prev, is_active: v }))}
                  aria-label={t('content.label_active')}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal} isDisabled={saving}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving} isDisabled={saving}>
              {editingItem ? t('federation.save_changes') : `${t('breadcrumbs.create')} ${t('breadcrumbs.attributes')}`}
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
          title={`${t('common.delete')} ${t('breadcrumbs.attributes')}`}
          message={t('gamification.confirm_delete_campaign', { name: deleteTarget.name })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default AttributesAdmin;
