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
import Plus from 'lucide-react/icons/plus';
import Settings from 'lucide-react/icons/settings';
import Trash2 from 'lucide-react/icons/trash-2';
import Edit2 from 'lucide-react/icons/pen';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { GroupType } from '@/admin/api/types';
import GroupPolicies from './GroupPolicies';

export default function GroupTypes() {
  usePageTitle("Groups");
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
      error("Failed to load group types");
    } finally {
      setLoading(false);
    }
  }, [error])


  useEffect(() => {
    loadTypes();
  }, [loadTypes]);

  const handleCreate = async () => {
    if (!formData.name.trim()) {
      error("Name is Required");
      return;
    }

    try {
      await adminGroups.createGroupType(formData);
      success("Group type created");
      onCreateClose();
      setFormData({ name: '', description: '', icon: 'fa-layer-group', color: '#6366f1' });
      loadTypes();
    } catch {
      error("Failed to create group type");
    }
  };

  const handleEdit = async () => {
    if (!selectedType || !formData.name.trim()) {
      error("Name is Required");
      return;
    }

    try {
      await adminGroups.updateGroupType(selectedType.id, formData);
      success("Group type updated");
      onEditClose();
      setSelectedType(null);
      setFormData({ name: '', description: '', icon: 'fa-layer-group', color: '#6366f1' });
      loadTypes();
    } catch {
      error("Failed to update group type");
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Delete Group Type")) {
      return;
    }

    try {
      await adminGroups.deleteGroupType(id);
      success("Group type deleted");
      loadTypes();
    } catch {
      error("Failed to delete group type");
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
          <h1 className="text-2xl font-bold">{"Group Types"}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {"Create and manage group type categories"}
          </p>
        </div>
        <Button color="primary" startContent={<Plus className="w-4 h-4" />} onPress={onCreateOpen}>
          {"Create Type"}
        </Button>
      </div>

      <Card className="p-4">
        <Table aria-label={"Group Types Table"}>
          <TableHeader>
            <TableColumn>{"Name"}</TableColumn>
            <TableColumn>{"Icon"}</TableColumn>
            <TableColumn>{"Groups"}</TableColumn>
            <TableColumn>{"Policies"}</TableColumn>
            <TableColumn>{"Created"}</TableColumn>
            <TableColumn>{"Actions"}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={loading ? "Loading groups..." : "No group types found"}
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
                      {"Policies"}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={"Edit Group Type"}
                      onPress={() => openEdit(type)}
                    >
                      <Edit2 className="w-4 h-4" />
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      isIconOnly
                      aria-label={"Delete Group Type"}
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
          <ModalHeader>{"Create Group Type"}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={"Name"}
                placeholder={"Enter Type Name..."}
                value={formData.name}
                onValueChange={(value) => setFormData({ ...formData, name: value })}
              />
              <Textarea
                label={"Description"}
                placeholder={"Optional description..."}
                value={formData.description}
                onValueChange={(value) => setFormData({ ...formData, description: value })}
              />
              <Input
                label={"Icon Class"}
                placeholder="e.g. fa-layer-group"
                value={formData.icon}
                onValueChange={(value) => setFormData({ ...formData, icon: value })}
              />
              <Input
                type="color"
                label={"Color"}
                value={formData.color}
                onValueChange={(value) => setFormData({ ...formData, color: value })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onCreateClose}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleCreate}>
              {"Create"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={isEditOpen} onClose={onEditClose}>
        <ModalContent>
          <ModalHeader>{"Edit Group Type"}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={"Name"}
                placeholder={"Enter Type Name..."}
                value={formData.name}
                onValueChange={(value) => setFormData({ ...formData, name: value })}
              />
              <Textarea
                label={"Description"}
                placeholder={"Optional description..."}
                value={formData.description}
                onValueChange={(value) => setFormData({ ...formData, description: value })}
              />
              <Input
                label={"Icon Class"}
                placeholder="e.g. fa-layer-group"
                value={formData.icon}
                onValueChange={(value) => setFormData({ ...formData, icon: value })}
              />
              <Input
                type="color"
                label={"Color"}
                value={formData.color}
                onValueChange={(value) => setFormData({ ...formData, color: value })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onEditClose}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleEdit}>
              {"Save"}
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
