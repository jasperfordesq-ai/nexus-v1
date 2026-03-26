// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, type CSSProperties } from 'react';
import {
  Card,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import { Plus, Settings, Trash2, Edit2 } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { GroupType } from '@/admin/api/types';
import GroupPolicies from './GroupPolicies';

import { useTranslation } from 'react-i18next';
export default function GroupTypes() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const { success, error } = useToast();
  const [types, setTypes] = useState<GroupType[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedType, setSelectedType] = useState<GroupType | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    icon: 'fa-layer-group',
    color: '#6366f1',
  });

  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const { isOpen: isEditOpen, onOpen: onEditOpen, onClose: onEditClose } = useDisclosure();
  const { isOpen: isPoliciesOpen, onOpen: onPoliciesOpen, onClose: onPoliciesClose } = useDisclosure();

  const loadTypes = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getGroupTypes();
      setTypes((response.data as GroupType[]) || []);
    } catch {
      error(t('groups.failed_to_load_group_types'));
    } finally {
      setLoading(false);
    }
  }, [error]);

  useEffect(() => {
    loadTypes();
  }, [loadTypes]);

  const handleCreate = async () => {
    if (!formData.name.trim()) {
      error(t('groups.name_is_required'));
      return;
    }

    try {
      await adminGroups.createGroupType(formData);
      success(t('groups.group_type_created'));
      onCreateClose();
      setFormData({ name: '', description: '', icon: 'fa-layer-group', color: '#6366f1' });
      loadTypes();
    } catch {
      error(t('groups.failed_to_create_group_type'));
    }
  };

  const handleEdit = async () => {
    if (!selectedType || !formData.name.trim()) {
      error(t('groups.name_is_required'));
      return;
    }

    try {
      await adminGroups.updateGroupType(selectedType.id, formData);
      success(t('groups.group_type_updated'));
      onEditClose();
      setSelectedType(null);
      setFormData({ name: '', description: '', icon: 'fa-layer-group', color: '#6366f1' });
      loadTypes();
    } catch {
      error(t('groups.failed_to_update_group_type'));
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm(t('groups.confirm_delete_group_type'))) {
      return;
    }

    try {
      await adminGroups.deleteGroupType(id);
      success(t('groups.group_type_deleted'));
      loadTypes();
    } catch {
      error(t('groups.failed_to_delete_group_type'));
    }
  };

  const openEdit = (type: GroupType) => {
    setSelectedType(type);
    setFormData({
      name: type.name,
      description: type.description || '',
      icon: type.icon || 'fa-layer-group',
      color: type.color || '#6366f1',
    });
    onEditOpen();
  };

  const openPolicies = (type: GroupType) => {
    setSelectedType(type);
    onPoliciesOpen();
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t('groups.group_types_title')}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {t('groups.group_types_desc')}
          </p>
        </div>
        <Button color="primary" startContent={<Plus className="w-4 h-4" />} onPress={onCreateOpen}>
          {t('groups.create_type')}
        </Button>
      </div>

      <Card className="p-4">
        <Table aria-label={t('groups.label_group_types_table')}>
          <TableHeader>
            <TableColumn>{t('groups.col_name')}</TableColumn>
            <TableColumn>{t('groups.col_icon')}</TableColumn>
            <TableColumn>{t('groups.col_groups')}</TableColumn>
            <TableColumn>{t('groups.col_policies')}</TableColumn>
            <TableColumn>{t('groups.col_created')}</TableColumn>
            <TableColumn>{t('groups.col_actions')}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={loading ? t('groups.loading') : t('groups.no_group_types_found')}
            items={types}
          >
            {(type) => (
              <TableRow key={type.id}>
                <TableCell>
                  <div>
                    <div className="font-medium">{type.name}</div>
                    {type.description && (
                      <div className="text-xs text-gray-500 mt-1">{type.description}</div>
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div
                      className="w-8 h-8 rounded-lg flex items-center justify-center"
                      style={{ '--group-type-color': type.color, backgroundColor: 'var(--group-type-color)' } as CSSProperties}
                    >
                      <i className={`${type.icon} text-white text-sm`}></i>
                    </div>
                  </div>
                </TableCell>
                <TableCell>{type.member_count}</TableCell>
                <TableCell>{type.policy_count}</TableCell>
                <TableCell>{new Date(type.created_at).toLocaleDateString()}</TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      startContent={<Settings className="w-3 h-3" />}
                      onPress={() => openPolicies(type)}
                    >
                      {t('groups.policies')}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={t('groups.label_edit_group_type')}
                      onPress={() => openEdit(type)}
                    >
                      <Edit2 className="w-4 h-4" />
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      isIconOnly
                      aria-label={t('groups.label_delete_group_type')}
                      onPress={() => handleDelete(type.id)}
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </Card>

      {/* Create Modal */}
      <Modal isOpen={isCreateOpen} onClose={onCreateClose}>
        <ModalContent>
          <ModalHeader>{t('groups.create_group_type')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('groups.label_name')}
                placeholder={t('groups.placeholder_enter_type_name')}
                value={formData.name}
                onValueChange={(value) => setFormData({ ...formData, name: value })}
              />
              <Textarea
                label={t('groups.label_description')}
                placeholder={t('groups.placeholder_optional_description')}
                value={formData.description}
                onValueChange={(value) => setFormData({ ...formData, description: value })}
              />
              <Input
                label={t('groups.label_icon_class')}
                placeholder="e.g. fa-layer-group"
                value={formData.icon}
                onValueChange={(value) => setFormData({ ...formData, icon: value })}
              />
              <Input
                type="color"
                label={t('groups.label_color')}
                value={formData.color}
                onValueChange={(value) => setFormData({ ...formData, color: value })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onCreateClose}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleCreate}>
              {t('groups.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={isEditOpen} onClose={onEditClose}>
        <ModalContent>
          <ModalHeader>{t('groups.edit_group_type')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('groups.label_name')}
                placeholder={t('groups.placeholder_enter_type_name')}
                value={formData.name}
                onValueChange={(value) => setFormData({ ...formData, name: value })}
              />
              <Textarea
                label={t('groups.label_description')}
                placeholder={t('groups.placeholder_optional_description')}
                value={formData.description}
                onValueChange={(value) => setFormData({ ...formData, description: value })}
              />
              <Input
                label={t('groups.label_icon_class')}
                placeholder="e.g. fa-layer-group"
                value={formData.icon}
                onValueChange={(value) => setFormData({ ...formData, icon: value })}
              />
              <Input
                type="color"
                label={t('groups.label_color')}
                value={formData.color}
                onValueChange={(value) => setFormData({ ...formData, color: value })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onEditClose}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleEdit}>
              {t('groups.save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Policies Modal */}
      {selectedType && (
        <GroupPolicies
          isOpen={isPoliciesOpen}
          onClose={onPoliciesClose}
          typeId={selectedType.id}
          typeName={selectedType.name}
        />
      )}
    </div>
  );
}
