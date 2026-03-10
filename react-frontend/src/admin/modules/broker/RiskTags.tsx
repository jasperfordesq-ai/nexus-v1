// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Risk Tags
 * View, filter, create, edit and remove listing risk tags.
 * Parity: PHP BrokerControlsController::riskTags()
 */

import { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  Tabs, Tab, Button, Chip,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Select, SelectItem, Textarea, Switch, Spinner,
} from '@heroui/react';
import { ArrowLeft, ShieldCheck, ShieldAlert, Plus, Edit, Trash2, Search } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker, adminListings } from '../../api/adminApi';
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

const RISK_CATEGORIES = [
  { key: 'safeguarding', label: 'Safeguarding Concern' },
  { key: 'financial', label: 'Financial Risk' },
  { key: 'health_safety', label: 'Health & Safety' },
  { key: 'legal', label: 'Legal/Regulatory' },
  { key: 'reputation', label: 'Reputational Risk' },
  { key: 'fraud', label: 'Potential Fraud' },
  { key: 'other', label: 'Other' },
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

interface ListingSearchResult {
  id: number;
  title: string;
  owner_name?: string;
}

export function RiskTagsPage() {
  usePageTitle('Admin - Risk Tags');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<RiskTag[]>([]);
  const [loading, setLoading] = useState(true);
  const [riskLevel, setRiskLevel] = useState('all');
  const [tableSearch, setTableSearch] = useState('');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingTag, setEditingTag] = useState<RiskTag | null>(null);
  const [form, setForm] = useState<RiskTagForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [removing, setRemoving] = useState<number | null>(null);

  // Listing search state
  const [listingSearch, setListingSearch] = useState('');
  const [listingResults, setListingResults] = useState<ListingSearchResult[]>([]);
  const [searchingListings, setSearchingListings] = useState(false);
  const [selectedListing, setSelectedListing] = useState<ListingSearchResult | null>(null);
  const searchDebounce = useRef<ReturnType<typeof setTimeout>>();

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
      toast.error('Failed to load risk tags');
    } finally {
      setLoading(false);
    }
  }, [riskLevel]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // Listing search with debounce
  useEffect(() => {
    if (!listingSearch.trim() || listingSearch.trim().length < 2) {
      setListingResults([]);
      return;
    }
    clearTimeout(searchDebounce.current);
    searchDebounce.current = setTimeout(async () => {
      setSearchingListings(true);
      try {
        const res = await adminListings.list({ search: listingSearch.trim(), page: 1 });
        if (res.success && res.data) {
          const data = Array.isArray(res.data) ? res.data : (res.data as { items?: ListingSearchResult[] }).items ?? [];
          setListingResults(data.slice(0, 8).map((l: Record<string, unknown>) => ({
            id: l.id as number,
            title: (l.title as string) || `Listing #${l.id}`,
            owner_name: (l.owner_name ?? l.user_name ?? '') as string,
          })));
        }
      } catch {
        // silently fail
      } finally {
        setSearchingListings(false);
      }
    }, 300);
    return () => clearTimeout(searchDebounce.current);
  }, [listingSearch]);

  function openCreateModal() {
    setEditingTag(null);
    setForm(EMPTY_FORM);
    setSelectedListing(null);
    setListingSearch('');
    setListingResults([]);
    setModalOpen(true);
  }

  function openEditModal(tag: RiskTag) {
    setEditingTag(tag);
    setForm({
      listing_id: String(tag.listing_id),
      risk_level: tag.risk_level,
      risk_category: tag.risk_category,
      risk_notes: tag.risk_notes ?? '',
      member_visible_notes: tag.member_visible_notes ?? '',
      requires_approval: tag.requires_approval,
      insurance_required: tag.insurance_required,
      dbs_required: tag.dbs_required,
    });
    setSelectedListing(null);
    setListingSearch('');
    setListingResults([]);
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditingTag(null);
    setForm(EMPTY_FORM);
    setSelectedListing(null);
    setListingSearch('');
    setListingResults([]);
  }

  function selectListing(listing: ListingSearchResult) {
    setSelectedListing(listing);
    setForm(f => ({ ...f, listing_id: String(listing.id) }));
    setListingSearch('');
    setListingResults([]);
  }

  async function handleSave() {
    const listingId = parseInt(form.listing_id);
    if (!listingId || listingId <= 0) {
      toast.error('Please select a valid listing');
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

  // Client-side search filtering
  const filteredItems = useMemo(() => {
    if (!tableSearch.trim()) return items;
    const q = tableSearch.toLowerCase();
    return items.filter(item =>
      (item.listing_title ?? '').toLowerCase().includes(q) ||
      (item.owner_name ?? '').toLowerCase().includes(q) ||
      (item.risk_category ?? '').toLowerCase().includes(q) ||
      (item.tagged_by_name ?? '').toLowerCase().includes(q)
    );
  }, [items, tableSearch]);

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
      render: (item) => {
        const label = RISK_CATEGORIES.find(c => c.key === item.risk_category)?.label;
        return <span className="text-sm">{label ?? item.risk_category ?? '—'}</span>;
      },
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
      key: 'tagged_by_name',
      label: 'Tagged By',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.tagged_by_name || '—'}
        </span>
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
        data={filteredItems}
        isLoading={loading}
        searchable
        onSearch={setTableSearch}
        onRefresh={loadItems}
      />

      {/* Create / Edit Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingTag ? 'Edit Risk Tag' : 'Tag Listing'}
          </ModalHeader>
          <ModalBody className="space-y-4">
            {/* Listing search — only shown when creating */}
            {!editingTag && (
              <div className="relative">
                {selectedListing ? (
                  <div className="flex items-center gap-2 p-3 rounded-lg bg-default-100">
                    <div className="flex-1">
                      <p className="font-medium text-sm">{selectedListing.title}</p>
                      <p className="text-xs text-default-500">
                        ID: {selectedListing.id}
                        {selectedListing.owner_name && ` · ${selectedListing.owner_name}`}
                      </p>
                    </div>
                    <Button
                      size="sm"
                      variant="flat"
                      onPress={() => {
                        setSelectedListing(null);
                        setForm(f => ({ ...f, listing_id: '' }));
                      }}
                    >
                      Change
                    </Button>
                  </div>
                ) : (
                  <>
                    <Input
                      label="Search Listing"
                      value={listingSearch}
                      onValueChange={setListingSearch}
                      placeholder="Type to search by title or ID..."
                      isRequired
                      startContent={searchingListings ? <Spinner size="sm" /> : <Search size={14} />}
                    />
                    {listingResults.length > 0 && (
                      <div className="absolute z-50 w-full mt-1 bg-content1 border border-default-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        {listingResults.map(listing => (
                          <Button
                            key={listing.id}
                            variant="light"
                            className="w-full text-left px-3 py-2 justify-start h-auto rounded-none"
                            onPress={() => selectListing(listing)}
                          >
                            <div className="text-left">
                              <p className="text-sm font-medium">{listing.title}</p>
                              <p className="text-xs text-default-500">
                                ID: {listing.id}
                                {listing.owner_name && ` · ${listing.owner_name}`}
                              </p>
                            </div>
                          </Button>
                        ))}
                      </div>
                    )}
                    {/* Fallback: manual ID entry */}
                    <Input
                      label="Or enter Listing ID directly"
                      type="number"
                      value={form.listing_id}
                      onValueChange={v => setForm(f => ({ ...f, listing_id: v }))}
                      placeholder="e.g. 42"
                      min={1}
                      className="mt-2"
                      size="sm"
                    />
                  </>
                )}
              </div>
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

            <Select
              label="Risk Category"
              selectedKeys={form.risk_category ? new Set([form.risk_category]) : new Set()}
              onSelectionChange={keys => {
                const val = Array.from(keys)[0] as string;
                if (val) setForm(f => ({ ...f, risk_category: val }));
              }}
              isRequired
            >
              {RISK_CATEGORIES.map(cat => (
                <SelectItem key={cat.key}>
                  {cat.label}
                </SelectItem>
              ))}
            </Select>

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
