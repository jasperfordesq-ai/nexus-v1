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
import { useTranslation } from 'react-i18next';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Tabs, Tab, Button, Chip,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Select, SelectItem, Textarea, Switch, Spinner,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Plus from 'lucide-react/icons/plus';
import Edit from 'lucide-react/icons/square-pen';
import Trash2 from 'lucide-react/icons/trash-2';
import Search from 'lucide-react/icons/search';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { formatServerDate } from '@/lib/serverTime';
import { adminBroker, adminListings } from '@/admin/api/adminApi';
import { DataTable, PageHeader, ConfirmModal, type Column } from '@/admin/components';
import type { RiskTag } from '@/admin/api/types';

const riskColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  low: 'success',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
};

const RISK_LEVEL_KEYS = ['low', 'medium', 'high', 'critical'] as const;

const RISK_CATEGORY_KEYS = [
  'safeguarding',
  'financial',
  'health_safety',
  'legal',
  'reputation',
  'fraud',
  'other',
] as const;

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
  const { t } = useTranslation('broker');
  usePageTitle(t('risk_tags.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Risk level filter is mirrored to `?level=` so stat-card deep-links work.
  const RISK_LEVELS = ['all', 'critical', 'high', 'medium', 'low'] as const;
  type RiskLevel = (typeof RISK_LEVELS)[number];
  const [searchParams, setSearchParams] = useSearchParams();
  const urlLevel = searchParams.get('level') as RiskLevel | null;
  const riskLevel: RiskLevel =
    urlLevel && RISK_LEVELS.includes(urlLevel) ? urlLevel : 'all';
  const setRiskLevel = useCallback(
    (next: RiskLevel) => {
      setSearchParams(
        (prev) => {
          const params = new URLSearchParams(prev);
          if (next === 'all') {
            params.delete('level');
          } else {
            params.set('level', next);
          }
          return params;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  const [items, setItems] = useState<RiskTag[]>([]);
  const [loading, setLoading] = useState(true);
  const [tableSearch, setTableSearch] = useState('');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editingTag, setEditingTag] = useState<RiskTag | null>(null);
  const [form, setForm] = useState<RiskTagForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [removing, setRemoving] = useState<number | null>(null);
  const [removeTarget, setRemoveTarget] = useState<RiskTag | null>(null);

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
      toast.error(t('risk_tags.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [riskLevel, toast, t])


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
            title: (l.title as string) || t('risk_tags.listing_fallback', { id: l.id }),
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
  }, [listingSearch, t]);

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
      toast.error(t('risk_tags.select_listing_error'));
      return;
    }
    if (!form.risk_category.trim()) {
      toast.error(t('risk_tags.category_required_error'));
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
        toast.success(editingTag ? t('risk_tags.updated_success') : t('risk_tags.created_success'));
        closeModal();
        loadItems();
      } else {
        toast.error(t('risk_tags.save_failed'));
      }
    } catch {
      toast.error(t('risk_tags.save_failed'));
    } finally {
      setSaving(false);
    }
  }

  async function handleRemove(tag: RiskTag) {
    setRemoving(tag.listing_id);
    try {
      const res = await adminBroker.removeRiskTag(tag.listing_id);
      if (res.success) {
        toast.success(t('risk_tags.removed_success'));
        loadItems();
        setRemoveTarget(null);
      } else {
        toast.error(t('risk_tags.remove_failed'));
      }
    } catch {
      toast.error(t('risk_tags.remove_failed'));
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
      label: t('risk_tags.col_listing'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'owner_name',
      label: t('risk_tags.col_owner'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.owner_name || '—'}
        </span>
      ),
    },
    {
      key: 'risk_level',
      label: t('risk_tags.col_risk_level'),
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
          {t(`risk_tags.level_${item.risk_level}`, { defaultValue: item.risk_level })}
        </Chip>
      ),
    },
    {
      key: 'risk_category',
      label: t('risk_tags.col_category'),
      sortable: true,
      render: (item) => {
        const cat = item.risk_category;
        const label = cat ? t(`risk_tags.category_${cat}`, { defaultValue: cat }) : '—';
        return <span className="text-sm">{label}</span>;
      },
    },
    {
      key: 'requires_approval',
      label: t('risk_tags.col_approval_req'),
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.requires_approval ? 'warning' : 'default'}>
          {item.requires_approval ? t('risk_tags.yes') : t('risk_tags.no')}
        </Chip>
      ),
    },
    {
      key: 'insurance_required',
      label: t('risk_tags.col_insurance'),
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.insurance_required ? 'warning' : 'default'}>
          {item.insurance_required ? t('risk_tags.yes') : t('risk_tags.no')}
        </Chip>
      ),
    },
    {
      key: 'dbs_required',
      label: t('risk_tags.col_dbs'),
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.dbs_required ? 'warning' : 'default'}>
          {item.dbs_required ? t('risk_tags.yes') : t('risk_tags.no')}
        </Chip>
      ),
    },
    {
      key: 'tagged_by_name',
      label: t('risk_tags.col_tagged_by'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.tagged_by_name || '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('risk_tags.col_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {formatServerDate(item.created_at)}
        </span>
      ),
    },
    {
      key: 'id',
      label: t('risk_tags.col_actions'),
      render: (item) => (
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="flat"
            color="primary"
            isIconOnly
            onPress={() => openEditModal(item)}
            aria-label={t('risk_tags.edit_aria')}
          >
            <Edit size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            isIconOnly
            isLoading={removing === item.listing_id}
            onPress={() => setRemoveTarget(item)}
            aria-label={t('risk_tags.remove_aria')}
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
        title={t('risk_tags.title')}
        description={t('risk_tags.description')}
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={openCreateModal}
              size="sm"
            >
              {t('risk_tags.tag_listing')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/broker')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              {t('risk_tags.back')}
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={riskLevel}
          onSelectionChange={(key) => setRiskLevel(key as RiskLevel)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('risk_tags.tab_all')} />
          <Tab key="critical" title={t('risk_tags.level_critical')} />
          <Tab key="high" title={t('risk_tags.level_high')} />
          <Tab key="medium" title={t('risk_tags.level_medium')} />
          <Tab key="low" title={t('risk_tags.level_low')} />
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
            {editingTag ? t('risk_tags.modal_title_edit') : t('risk_tags.modal_title_create')}
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
                        {t('risk_tags.id_label', { id: selectedListing.id })}
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
                      {t('risk_tags.change')}
                    </Button>
                  </div>
                ) : (
                  <>
                    <Input
                      label={t('risk_tags.search_listing_label')}
                      value={listingSearch}
                      onValueChange={setListingSearch}
                      placeholder={t('risk_tags.search_listing_placeholder')}
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
                                {t('risk_tags.id_label', { id: listing.id })}
                                {listing.owner_name && ` · ${listing.owner_name}`}
                              </p>
                            </div>
                          </Button>
                        ))}
                      </div>
                    )}
                    {/* Fallback: manual ID entry */}
                    <Input
                      label={t('risk_tags.manual_id_label')}
                      type="number"
                      value={form.listing_id}
                      onValueChange={v => setForm(f => ({ ...f, listing_id: v }))}
                      placeholder={t('risk_tags.manual_id_placeholder')}
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
                <p className="text-sm text-default-500">{t('risk_tags.listing_field_label')}</p>
                <p className="font-medium">{editingTag.listing_title ?? t('risk_tags.listing_fallback', { id: editingTag.listing_id })}</p>
              </div>
            )}

            <Select
              label={t('risk_tags.risk_level_label')}
              selectedKeys={new Set([form.risk_level])}
              onSelectionChange={keys => {
                const val = Array.from(keys)[0] as RiskTagForm['risk_level'];
                if (val) setForm(f => ({ ...f, risk_level: val }));
              }}
              isRequired
            >
              {RISK_LEVEL_KEYS.map(key => (
                <SelectItem key={key}>
                  {t(`risk_tags.level_${key}`)}
                </SelectItem>
              ))}
            </Select>

            <Select
              label={t('risk_tags.risk_category_label')}
              selectedKeys={form.risk_category ? new Set([form.risk_category]) : new Set()}
              onSelectionChange={keys => {
                const val = Array.from(keys)[0] as string;
                if (val) setForm(f => ({ ...f, risk_category: val }));
              }}
              isRequired
            >
              {RISK_CATEGORY_KEYS.map(key => (
                <SelectItem key={key}>
                  {t(`risk_tags.category_${key}`)}
                </SelectItem>
              ))}
            </Select>

            <Textarea
              label={t('risk_tags.risk_notes_label')}
              value={form.risk_notes}
              onValueChange={v => setForm(f => ({ ...f, risk_notes: v }))}
              placeholder={t('risk_tags.risk_notes_placeholder')}
              minRows={3}
            />

            <Textarea
              label={t('risk_tags.member_visible_notes_label')}
              value={form.member_visible_notes}
              onValueChange={v => setForm(f => ({ ...f, member_visible_notes: v }))}
              placeholder={t('risk_tags.member_visible_notes_placeholder')}
              minRows={3}
            />

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">{t('risk_tags.requires_approval_label')}</p>
                <p className="text-xs text-default-500">{t('risk_tags.requires_approval_description')}</p>
              </div>
              <Switch
                isSelected={form.requires_approval}
                onValueChange={v => setForm(f => ({ ...f, requires_approval: v }))}
                size="sm"
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">{t('risk_tags.insurance_required_label')}</p>
                <p className="text-xs text-default-500">{t('risk_tags.insurance_required_description')}</p>
              </div>
              <Switch
                isSelected={form.insurance_required}
                onValueChange={v => setForm(f => ({ ...f, insurance_required: v }))}
                size="sm"
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">{t('risk_tags.dbs_required_label')}</p>
                <p className="text-xs text-default-500">{t('risk_tags.dbs_required_description')}</p>
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
              {t('risk_tags.cancel')}
            </Button>
            <Button color="primary" onPress={handleSave} isLoading={saving} isDisabled={saving}>
              {editingTag ? t('risk_tags.update_tag') : t('risk_tags.create_tag')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Remove confirmation — replaces native window.confirm() so the
          dialog matches the rest of the broker panel (HeroUI styled,
          non-blocking, iOS-friendly). */}
      <ConfirmModal
        isOpen={!!removeTarget}
        onClose={() => setRemoveTarget(null)}
        onConfirm={() => removeTarget && handleRemove(removeTarget)}
        title={t('risk_tags.confirm_remove_title')}
        message={
          removeTarget
            ? t('risk_tags.confirm_remove_message', {
                listing: removeTarget.listing_title ?? t('risk_tags.listing_fallback', { id: removeTarget.listing_id }),
              })
            : ''
        }
        confirmLabel={t('risk_tags.remove')}
        confirmColor="danger"
        isLoading={removing !== null}
      />
    </div>
  );
}

export default RiskTagsPage;
