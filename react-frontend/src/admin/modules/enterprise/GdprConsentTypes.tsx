// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Consent Types
 * Management page for consent types with CRUD, user viewing, and CSV export.
 * Route: /admin/enterprise/gdpr/consent-types
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card, CardBody, Button, Chip, Progress, Spinner,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Textarea, Select, SelectItem, Switch,
} from '@heroui/react';
import {
  RefreshCw, Plus, Edit, Users, Trash2,
  CheckCircle, XCircle, ExternalLink,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, ConfirmModal } from '../../components';
import type { Column } from '../../components';
import type { ConsentType, ConsentTypeUser } from '../../api/types';

import { useTranslation } from 'react-i18next';

const CATEGORY_OPTIONS = ['essential', 'functional', 'analytics', 'marketing', 'communications', 'other'] as const;

const emptyFormData = {
  slug: '',
  name: '',
  description: '',
  category: 'essential',
  is_required: false,
  legal_basis: '',
  retention_days: '',
  display_order: '0',
  is_active: true,
};

export function GdprConsentTypes() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.gdpr_consent_types_page_title'));
  const toast = useToast();

  const [consentTypes, setConsentTypes] = useState<ConsentType[]>([]);
  const [loading, setLoading] = useState(true);

  // Create/Edit modal
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState(emptyFormData);
  const [formLoading, setFormLoading] = useState(false);

  // View Users modal
  const [usersOpen, setUsersOpen] = useState(false);
  const [usersSlug, setUsersSlug] = useState('');
  const [usersName, setUsersName] = useState('');
  const [users, setUsers] = useState<ConsentTypeUser[]>([]);
  const [usersLoading, setUsersLoading] = useState(false);

  // Delete confirm
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getConsentTypes();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setConsentTypes(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.gdpr_failed_load_consent_types'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openCreateModal = () => {
    setEditingId(null);
    setFormData(emptyFormData);
    setFormOpen(true);
  };

  const openEditModal = (ct: ConsentType) => {
    setEditingId(ct.id);
    setFormData({
      slug: ct.slug,
      name: ct.name,
      description: ct.description || '',
      category: ct.category || 'essential',
      is_required: ct.is_required,
      legal_basis: ct.legal_basis || '',
      retention_days: ct.retention_days != null ? String(ct.retention_days) : '',
      display_order: String(ct.display_order),
      is_active: ct.is_active,
    });
    setFormOpen(true);
  };

  const handleFormSubmit = async () => {
    if (!formData.slug.trim() || !formData.name.trim()) {
      toast.error(t('enterprise.gdpr_slug_name_required'));
      return;
    }
    setFormLoading(true);
    try {
      const payload: Partial<ConsentType> = {
        slug: formData.slug.trim(),
        name: formData.name.trim(),
        description: formData.description.trim() || null,
        category: formData.category || null,
        is_required: formData.is_required,
        legal_basis: formData.legal_basis.trim() || null,
        retention_days: formData.retention_days ? parseInt(formData.retention_days, 10) : null,
        display_order: parseInt(formData.display_order, 10) || 0,
        is_active: formData.is_active,
      };

      let res;
      if (editingId) {
        res = await adminEnterprise.updateConsentType(editingId, payload);
      } else {
        res = await adminEnterprise.createConsentType(payload);
      }

      if (res.success) {
        toast.success(editingId ? t('enterprise.gdpr_consent_type_updated') : t('enterprise.gdpr_consent_type_created'));
        setFormOpen(false);
        loadData();
      } else {
        toast.error(editingId ? t('enterprise.gdpr_failed_update_consent_type') : t('enterprise.gdpr_failed_create_consent_type'));
      }
    } catch {
      toast.error(t('enterprise.gdpr_failed_save_consent_type'));
    } finally {
      setFormLoading(false);
    }
  };

  const openUsersModal = async (slug: string, name: string) => {
    setUsersSlug(slug);
    setUsersName(name);
    setUsersOpen(true);
    setUsersLoading(true);
    try {
      const res = await adminEnterprise.getConsentTypeUsers(slug);
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setUsers(data);
        } else if (data && typeof data === 'object') {
          const pd = data as { data?: ConsentTypeUser[] };
          setUsers(pd.data || []);
        }
      }
    } catch {
      toast.error(t('enterprise.gdpr_failed_load_users'));
    } finally {
      setUsersLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteId) return;
    setDeleteLoading(true);
    try {
      const res = await adminEnterprise.deleteConsentType(deleteId);
      if (res.success) {
        toast.success(t('enterprise.gdpr_consent_type_deleted'));
        setDeleteOpen(false);
        setDeleteId(null);
        loadData();
      } else {
        toast.error(t('enterprise.gdpr_failed_delete_consent_type'));
      }
    } catch {
      toast.error(t('enterprise.gdpr_failed_delete_consent_type'));
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleExportUsers = (slug: string) => {
    const url = adminEnterprise.exportConsentTypeUsers(slug);
    window.open(url, '_blank');
  };

  const userColumns: Column<ConsentTypeUser>[] = [
    { key: 'user_name', label: t('enterprise.gdpr_col_user_name'), sortable: true },
    { key: 'user_email', label: t('enterprise.gdpr_col_email'), sortable: true },
    {
      key: 'consent_given',
      label: t('enterprise.gdpr_col_consent'),
      render: (u) =>
        u.consent_given ? (
          <div className="flex items-center gap-1 text-success">
            <CheckCircle size={14} />
            <span className="text-sm">{t('enterprise.gdpr_granted')}</span>
          </div>
        ) : (
          <div className="flex items-center gap-1 text-danger">
            <XCircle size={14} />
            <span className="text-sm">{t('enterprise.gdpr_denied')}</span>
          </div>
        ),
    },
    {
      key: 'given_at',
      label: t('enterprise.gdpr_col_date'),
      sortable: true,
      render: (u) => u.given_at ? new Date(u.given_at).toLocaleDateString() : '---',
    },
    {
      key: 'ip_address',
      label: t('enterprise.gdpr_col_ip_address'),
      render: (u) => u.ip_address || '---',
    },
  ];

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_consent_types_title')}
        description={t('enterprise.gdpr_consent_types_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {t('common.refresh')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={openCreateModal}
              size="sm"
            >
              {t('enterprise.gdpr_create_consent_type')}
            </Button>
          </div>
        }
      />

      {/* Consent Type Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {consentTypes.map((ct) => {
          const totalResponses = ct.granted_count + ct.denied_count;
          const consentRate = totalResponses > 0 ? (ct.granted_count / totalResponses) * 100 : 0;

          return (
            <Card key={ct.id} shadow="sm">
              <CardBody className="p-4 space-y-3">
                <div className="flex justify-between items-start">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-foreground truncate">{ct.name}</p>
                    <p className="text-xs text-default-400 font-mono">{ct.slug}</p>
                  </div>
                  <div className="flex gap-1 shrink-0">
                    {ct.is_required && (
                      <Chip size="sm" variant="flat" color="warning">{t('enterprise.gdpr_required')}</Chip>
                    )}
                    <Chip size="sm" variant="flat" color={ct.is_active ? 'success' : 'default'}>
                      {ct.is_active ? t('enterprise.gdpr_active') : t('enterprise.gdpr_inactive')}
                    </Chip>
                  </div>
                </div>

                {ct.category && (
                  <Chip size="sm" variant="bordered" className="capitalize">{ct.category}</Chip>
                )}

                {ct.legal_basis && (
                  <p className="text-xs text-default-500 line-clamp-2">{ct.legal_basis}</p>
                )}

                {/* Consent Rate Progress */}
                <div>
                  <div className="flex justify-between text-xs text-default-500 mb-1">
                    <span>{t('enterprise.gdpr_consent_rate')}</span>
                    <span>{consentRate.toFixed(1)}% ({ct.granted_count}/{totalResponses})</span>
                  </div>
                  <Progress
                    value={consentRate}
                    color="success"
                    size="sm"
                    aria-label="Consent rate"
                  />
                </div>

                {/* Actions */}
                <div className="flex gap-2 pt-1">
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Edit size={12} />}
                    onPress={() => openEditModal(ct)}
                  >
                    {t('enterprise.gdpr_edit')}
                  </Button>
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Users size={12} />}
                    onPress={() => openUsersModal(ct.slug, ct.name)}
                  >
                    {t('enterprise.gdpr_users')}
                  </Button>
                  <Button
                    size="sm"
                    variant="flat"
                    color="danger"
                    isIconOnly
                    aria-label="Delete"
                    onPress={() => { setDeleteId(ct.id); setDeleteOpen(true); }}
                  >
                    <Trash2 size={12} />
                  </Button>
                </div>
              </CardBody>
            </Card>
          );
        })}

        {consentTypes.length === 0 && (
          <div className="col-span-full text-center py-12 text-default-400">
            {t('enterprise.gdpr_no_consent_types')}
          </div>
        )}
      </div>

      {/* Create/Edit Modal */}
      <Modal isOpen={formOpen} onClose={() => setFormOpen(false)} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{editingId ? t('enterprise.gdpr_edit_consent_type') : t('enterprise.gdpr_create_consent_type')}</ModalHeader>
          <ModalBody className="gap-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label={t('enterprise.gdpr_slug')}
                placeholder={t('enterprise.gdpr_slug_placeholder')}
                value={formData.slug}
                onValueChange={(val) => setFormData({ ...formData, slug: val })}
                variant="bordered"
                isRequired
                isDisabled={!!editingId}
              />
              <Input
                label={t('enterprise.gdpr_name')}
                placeholder={t('enterprise.gdpr_name_placeholder')}
                value={formData.name}
                onValueChange={(val) => setFormData({ ...formData, name: val })}
                variant="bordered"
                isRequired
              />
            </div>
            <Textarea
              label={t('enterprise.gdpr_description')}
              placeholder={t('enterprise.gdpr_description_placeholder')}
              value={formData.description}
              onValueChange={(val) => setFormData({ ...formData, description: val })}
              variant="bordered"
              minRows={2}
            />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label={t('enterprise.gdpr_category')}
                selectedKeys={[formData.category]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) setFormData({ ...formData, category: val });
                }}
                variant="bordered"
              >
                {CATEGORY_OPTIONS.map((key) => (
                  <SelectItem key={key} className="capitalize">{key}</SelectItem>
                ))}
              </Select>
              <Input
                label={t('enterprise.gdpr_legal_basis')}
                placeholder={t('enterprise.gdpr_legal_basis_placeholder')}
                value={formData.legal_basis}
                onValueChange={(val) => setFormData({ ...formData, legal_basis: val })}
                variant="bordered"
              />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label={t('enterprise.gdpr_retention_days')}
                placeholder={t('enterprise.gdpr_retention_days_placeholder')}
                type="number"
                value={formData.retention_days}
                onValueChange={(val) => setFormData({ ...formData, retention_days: val })}
                variant="bordered"
              />
              <Input
                label={t('enterprise.gdpr_display_order')}
                type="number"
                value={formData.display_order}
                onValueChange={(val) => setFormData({ ...formData, display_order: val })}
                variant="bordered"
              />
            </div>
            <div className="flex gap-6">
              <Switch
                isSelected={formData.is_required}
                onValueChange={(val) => setFormData({ ...formData, is_required: val })}
              >
                {t('enterprise.gdpr_required')}
              </Switch>
              <Switch
                isSelected={formData.is_active}
                onValueChange={(val) => setFormData({ ...formData, is_active: val })}
              >
                {t('enterprise.gdpr_active')}
              </Switch>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setFormOpen(false)} isDisabled={formLoading}>
              {t('enterprise.gdpr_cancel')}
            </Button>
            <Button color="primary" onPress={handleFormSubmit} isLoading={formLoading}>
              {editingId ? t('enterprise.gdpr_update') : t('enterprise.gdpr_create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* View Users Modal */}
      <Modal isOpen={usersOpen} onClose={() => setUsersOpen(false)} size="4xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader className="flex justify-between items-center gap-4">
            <span>Users &mdash; {usersName}</span>
            <Button
              size="sm"
              variant="flat"
              startContent={<ExternalLink size={14} />}
              onPress={() => handleExportUsers(usersSlug)}
            >
              {t('enterprise.gdpr_export_csv')}
            </Button>
          </ModalHeader>
          <ModalBody>
            <DataTable
              columns={userColumns}
              data={users}
              isLoading={usersLoading}
              searchable={false}
              emptyContent={t('enterprise.gdpr_no_users_for_consent_type')}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setUsersOpen(false)}>{t('enterprise.gdpr_close')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        isOpen={deleteOpen}
        title={t('enterprise.gdpr_delete_consent_type')}
        message={t('enterprise.gdpr_delete_consent_type_confirm')}
        confirmLabel={t('enterprise.gdpr_delete')}
        confirmColor="danger"
        isLoading={deleteLoading}
        onConfirm={handleDelete}
        onClose={() => { setDeleteOpen(false); setDeleteId(null); }}
      />
    </div>
  );
}

export default GdprConsentTypes;
