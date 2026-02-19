// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Risk Tags
 * View, filter, create, edit and remove listing risk tags.
 * Parity: PHP BrokerControlsController::riskTags()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Tabs, Tab, Button, Chip,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Select, SelectItem, Textarea, Switch,
} from '@heroui/react';
import { ArrowLeft, ShieldCheck, ShieldAlert, Plus, Edit, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { RiskTag } from '../../api/types';

const riskColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  low: 'success',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
};

const RISK_LEVELS = [
  { key: 'low', label: 'Low' },
  { key: 'medium', label: 'Medium' },
  { key: 'high', label: 'High' },
  { key: 'critical', label: 'Critical' },
];

interface RiskTagForm {
  listing_id: string;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  risk_category: string;
  risk_notes: string;
  member_visible_notes: string;
  requires_approval: boolean;
  insurance_required: boolean;
  dbs_required: boolean;
}

const EMPTY_FORM: RiskTagForm = {
  listing_id: '',
  risk_level: 'medium',
  risk_category: '',
  risk_notes: '',
  member_visible_notes: '',
  requires_approval: false,
  insurance_required: false,
  dbs_required: false,
};

export function RiskTagsPage() {
  usePageTitle('Admin - Risk Tags');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<RiskTag[]>([]);
  const [loading, setLoading] = useState(true);
  const [riskLevel, setRiskLevel] = useState('all');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingTag, setEditingTag] = useState<RiskTag | null>(null);
  const [form, setForm] = useState<RiskTagForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [removing, setRemoving] = useState<number | null>(null);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getRiskTags({
        risk_level: riskLevel === 'all' ? undefined : riskLevel,
      });
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [riskLevel]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  function openCreateModal() {
    setEditingTag(null);
    setForm(EMPTY_FORM);
    setModalOpen(true);
  }

  function openEditModal(tag: RiskTag) {
    setEditingTag(tag);
    setForm({
      listing_id: String(tag.listing_id),
      risk_level: tag.risk_level,
      risk_category: tag.risk_category,
      risk_notes: tag.risk_notes ?? '',
      member_visible_notes: '',
      requires_approval: tag.requires_approval,
      insurance_required: tag.insurance_required,
      dbs_required: tag.dbs_required,
    });
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditingTag(null);
    setForm(EMPTY_FORM);
  }

  async function handleSave() {
    const listingId = parseInt(form.listing_id);
    if (!listingId || listingId <= 0) {
      toast.error('Please enter a valid Listing ID');
      return;
    }
    if (!form.risk_category.trim()) {
      toast.error('Risk category is required');
      return;
    }

    setSaving(true);
    try {
      const res = await adminBroker.saveRiskTag(listingId, {
        risk_level: form.risk_level,
        risk_category: form.risk_category.trim(),
        risk_notes: form.risk_notes || undefined,
        member_visible_notes: form.member_visible_notes || undefined,
        requires_approval: form.requires_approval,
        insurance_required: form.insurance_required,
        dbs_required: form.dbs_required,
      });
      if (res.success) {
        toast.success(editingTag ? 'Risk tag updated' : 'Risk tag created');
        closeModal();
        loadItems();
      } else {
        toast.error('Failed to save risk tag');
      }
    } catch {
      toast.error('Failed to save risk tag');
    } finally {
      setSaving(false);
    }
  }

  async function handleRemove(tag: RiskTag) {
    if (!confirm(`Remove risk tag from listing "${tag.listing_title ?? tag.listing_id}"?`)) return;
    setRemoving(tag.listing_id);
    try {
      const res = await adminBroker.removeRiskTag(tag.listing_id);
      if (res.success) {
        toast.success('Risk tag removed');
        loadItems();
      } else {
        toast.error('Failed to remove risk tag');
      }
    } catch {
      toast.error('Failed to remove risk tag');
    } finally {
      setRemoving(null);
    }
  }

  const columns: Column<RiskTag>[] = [
    {
      key: 'listing_title',
      label: 'Listing',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'owner_name',
      label: 'Owner',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.owner_name || '—'}
        </span>
      ),
    },
    {
      key: 'risk_level',
      label: 'Risk Level',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={riskColorMap[item.risk_level] || 'default'}
          startContent={item.risk_level === 'critical' || item.risk_level === 'high'
            ? <ShieldAlert size={12} />
            : <ShieldCheck size={12} />
          }
          className="capitalize"
        >
          {item.risk_level}
        </Chip>
      ),
    },
    {
      key: 'risk_category',
      label: 'Category',
      sortable: true,
      render: (item) => (
        <span className="text-sm capitalize">{item.risk_category || '—'}</span>
      ),
    },
    {
      key: 'requires_approval',
      label: 'Approval Req.',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.requires_approval ? 'warning' : 'default'}>
          {item.requires_approval ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'insurance_required',
      label: 'Insurance',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.insurance_required ? 'warning' : 'default'}>
          {item.insurance_required ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'dbs_required',
      label: 'DBS',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.dbs_required ? 'warning' : 'default'}>
          {item.dbs_required ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'id',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="flat"
            color="primary"
            isIconOnly
            onPress={() => openEditModal(item)}
            aria-label="Edit risk tag"
          >
            <Edit size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            isIconOnly
            isLoading={removing === item.listing_id}
            onPress={() => handleRemove(item)}
            aria-label="Remove risk tag"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Risk Tags"
        description="Listings flagged with risk assessments"
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={openCreateModal}
              size="sm"
            >
              Tag Listing
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              Back
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={riskLevel}
          onSelectionChange={(key) => setRiskLevel(key as string)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="critical" title="Critical" />
          <Tab key="high" title="High" />
          <Tab key="medium" title="Medium" />
          <Tab key="low" title="Low" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
      />

      {/* Create / Edit Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingTag ? 'Edit Risk Tag' : 'Tag Listing'}
          </ModalHeader>
          <ModalBody className="space-y-4">
            {/* Listing ID — only shown when creating */}
            {!editingTag && (
              <Input
                label="Listing ID"
                type="number"
                value={form.listing_id}
                onValueChange={v => setForm(f => ({ ...f, listing_id: v }))}
                placeholder="Enter listing ID"
                isRequired
                min={1}
              />
            )}
            {editingTag && (
              <div>
                <p className="text-sm text-default-500">Listing</p>
                <p className="font-medium">{editingTag.listing_title ?? `Listing #${editingTag.listing_id}`}</p>
              </div>
            )}

            <Select
              label="Risk Level"
              selectedKeys={new Set([form.risk_level])}
              onSelectionChange={keys => {
                const val = Array.from(keys)[0] as RiskTagForm['risk_level'];
                if (val) setForm(f => ({ ...f, risk_level: val }));
              }}
              isRequired
            >
              {RISK_LEVELS.map(level => (
                <SelectItem key={level.key}>
                  {level.label}
                </SelectItem>
              ))}
            </Select>

            <Input
              label="Risk Category"
              value={form.risk_category}
              onValueChange={v => setForm(f => ({ ...f, risk_category: v }))}
              placeholder="e.g. physical, financial, safeguarding"
              isRequired
            />

            <Textarea
              label="Risk Notes (internal)"
              value={form.risk_notes}
              onValueChange={v => setForm(f => ({ ...f, risk_notes: v }))}
              placeholder="Internal notes visible only to brokers"
              minRows={3}
            />

            <Textarea
              label="Member Visible Notes"
              value={form.member_visible_notes}
              onValueChange={v => setForm(f => ({ ...f, member_visible_notes: v }))}
              placeholder="Notes shown to members when this tag is triggered"
              minRows={3}
            />

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Requires Approval</p>
                <p className="text-xs text-default-500">Broker must approve exchanges involving this listing</p>
              </div>
              <Switch
                isSelected={form.requires_approval}
                onValueChange={v => setForm(f => ({ ...f, requires_approval: v }))}
                size="sm"
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Insurance Required</p>
                <p className="text-xs text-default-500">Provider must have insurance for this listing</p>
              </div>
              <Switch
                isSelected={form.insurance_required}
                onValueChange={v => setForm(f => ({ ...f, insurance_required: v }))}
                size="sm"
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">DBS Check Required</p>
                <p className="text-xs text-default-500">Provider must have a valid DBS check</p>
              </div>
              <Switch
                isSelected={form.dbs_required}
                onValueChange={v => setForm(f => ({ ...f, dbs_required: v }))}
                size="sm"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingTag ? 'Update Tag' : 'Create Tag'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default RiskTagsPage;
