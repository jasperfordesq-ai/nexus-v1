// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Approvals
 * Lists pending volunteer applications requiring admin review.
 * Parity: PHP VolunteeringController::approvals() + approve() + decline()
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Button, Avatar, Input, Tabs, Tab, Checkbox, Select, SelectItem } from '@heroui/react';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import Search from 'lucide-react/icons/search';
import Download from 'lucide-react/icons/download';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, type Column } from '../../components';

import { useTranslation } from 'react-i18next';

function exportToCsv(data: Array<Record<string, unknown>>, filename: string) {
  if (data.length === 0) return;
  const headers = Object.keys(data[0] ?? {});
  const csv = [
    headers.join(','),
    ...data.map(r => headers.map(h => JSON.stringify(r[h] ?? '')).join(',')),
  ].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
interface VolApplication {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  email: string;
  opportunity_title: string;
  status: string;
  created_at: string;
}

export function VolunteerApprovals() {
  const { t } = useTranslation('admin');
  usePageTitle("Volunteering");
  const toast = useToast();
  const [items, setItems] = useState<VolApplication[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionId, setActionId] = useState<number | null>(null);

  // Search, filter, and bulk state
  const [searchQuery, setSearchQuery] = useState('');
  const [statusTab, setStatusTab] = useState<string>('all');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);
  const [opportunityFilter, setOpportunityFilter] = useState<string>('all');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getApprovals();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: VolApplication[] }).data || []);
        }
      }
    } catch {
      toast.error("Failed to load approvals");
      setItems([]);
    }
    setLoading(false);
    setSelectedIds(new Set());
  }, [toast]);


  useEffect(() => { loadData(); }, [loadData]);

  // Derive unique opportunity titles for filter dropdown
  const opportunityOptions = useMemo(() => {
    const titles = [...new Set(items.map(i => i.opportunity_title).filter(Boolean))];
    titles.sort();
    return titles;
  }, [items]);

  // Filtered data
  const filteredItems = useMemo(() => {
    let result = items;
    // Status tab filter
    if (statusTab !== 'all') {
      result = result.filter(i => i.status === statusTab);
    }
    // Search by name
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter(i =>
        `${i.first_name} ${i.last_name}`.toLowerCase().includes(q) ||
        i.email?.toLowerCase().includes(q)
      );
    }
    // Opportunity filter
    if (opportunityFilter !== 'all') {
      result = result.filter(i => i.opportunity_title === opportunityFilter);
    }
    return result;
  }, [items, statusTab, searchQuery, opportunityFilter]);

  const handleApprove = async (id: number) => {
    setActionId(id);
    try {
      const res = await adminVolunteering.approveApplication(id);
      if (res.success) {
        toast.success("Application Approved");
        loadData();
      } else {
        toast.error("Failed to approve application");
      }
    } catch {
      toast.error("Failed to approve application");
    } finally {
      setActionId(null);
    }
  };

  const handleDecline = async (id: number) => {
    setActionId(id);
    try {
      const res = await adminVolunteering.declineApplication(id);
      if (res.success) {
        toast.success("Application Declined");
        loadData();
      } else {
        toast.error("Failed to decline application");
      }
    } catch {
      toast.error("Failed to decline application");
    } finally {
      setActionId(null);
    }
  };

  // Bulk operations
  const handleBulkApprove = async () => {
    if (selectedIds.size === 0) return;
    setBulkLoading(true);
    let successCount = 0;
    for (const id of selectedIds) {
      try {
        const res = await adminVolunteering.approveApplication(id);
        if (res.success) successCount++;
      } catch { /* continue */ }
    }
    toast.success(t('volunteering.bulk_approved', '{{count}} applications approved', { count: successCount }));
    setBulkLoading(false);
    loadData();
  };

  const handleBulkDecline = async () => {
    if (selectedIds.size === 0) return;
    setBulkLoading(true);
    let successCount = 0;
    for (const id of selectedIds) {
      try {
        const res = await adminVolunteering.declineApplication(id);
        if (res.success) successCount++;
      } catch { /* continue */ }
    }
    toast.success(t('volunteering.bulk_declined', '{{count}} applications declined', { count: successCount }));
    setBulkLoading(false);
    loadData();
  };

  const handleToggleSelect = (id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleSelectAll = () => {
    if (selectedIds.size === filteredItems.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(filteredItems.map(i => i.id)));
    }
  };

  const handleExport = () => {
    const exportData = filteredItems.map(i => ({
      name: `${i.first_name} ${i.last_name}`,
      email: i.email,
      opportunity: i.opportunity_title,
      status: i.status,
      applied: i.created_at ? new Date(i.created_at).toLocaleDateString() : '',
    }));
    exportToCsv(exportData as Array<Record<string, unknown>>, 'volunteer-approvals.csv');
    toast.success(t('volunteering.export_success', 'Exported successfully'));
  };

  const columns: Column<VolApplication>[] = [
    {
      key: 'select', label: '',
      render: (item) => (
        <Checkbox
          isSelected={selectedIds.has(item.id)}
          onValueChange={() => handleToggleSelect(item.id)}
          aria-label={t('volunteering.select_application', 'Select application')}
        />
      ),
    },
    {
      key: 'applicant', label: "Applicant", sortable: true,
      render: (item) => (
        <div className="flex items-center gap-3">
          <Avatar name={`${item.first_name} ${item.last_name}`} size="sm" />
          <div>
            <p className="font-medium">{item.first_name} {item.last_name}</p>
            <p className="text-xs text-default-400">{item.email}</p>
          </div>
        </div>
      ),
    },
    { key: 'opportunity_title', label: "Opportunity", sortable: true },
    {
      key: 'status', label: "Status",
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: "Applied", sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions', label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          <Button
            size="sm"
            variant="flat"
            color="success"
            startContent={<CheckCircle size={14} />}
            onPress={() => handleApprove(item.id)}
            isLoading={actionId === item.id}
            isDisabled={actionId !== null && actionId !== item.id}
          >
            {"Approve"}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<XCircle size={14} />}
            onPress={() => handleDecline(item.id)}
            isLoading={actionId === item.id}
            isDisabled={actionId !== null && actionId !== item.id}
          >
            {"Decline"}
          </Button>
        </div>
      ),
    },
  ];

  // Status tab counts
  const statusCounts = useMemo(() => ({
    all: items.length,
    pending: items.filter(i => i.status === 'pending').length,
    approved: items.filter(i => i.status === 'approved').length,
    declined: items.filter(i => i.status === 'declined').length,
  }), [items]);

  // Top content: search + filters + bulk actions
  const topContent = useMemo(() => (
    <div className="flex flex-col gap-3">
      {/* Status Tabs */}
      <Tabs
        selectedKey={statusTab}
        onSelectionChange={(key) => { setStatusTab(key as string); setSelectedIds(new Set()); }}
        variant="underlined"
        size="sm"
      >
        <Tab key="all" title={`${t('common.all', 'All')} (${statusCounts.all})`} />
        <Tab key="pending" title={`${t('common.pending', 'Pending')} (${statusCounts.pending})`} />
        <Tab key="approved" title={`${t('common.approved', 'Approved')} (${statusCounts.approved})`} />
        <Tab key="declined" title={`${t('volunteering.declined', 'Declined')} (${statusCounts.declined})`} />
      </Tabs>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
          {/* Search */}
          <Input
            className="max-w-xs"
            placeholder={t('volunteering.search_applicants', 'Search applicants...')}
            startContent={<Search size={16} className="text-default-400" />}
            value={searchQuery}
            onValueChange={setSearchQuery}
            isClearable
            onClear={() => setSearchQuery('')}
            size="sm"
          />

          {/* Opportunity filter */}
          {opportunityOptions.length > 1 && (
            <Select
              className="max-w-[220px]"
              label={t('volunteering.filter_opportunity', 'Opportunity')}
              size="sm"
              selectedKeys={new Set([opportunityFilter])}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string;
                setOpportunityFilter(val || 'all');
              }}
              items={[{ key: 'all', label: t('common.all', 'All') }, ...opportunityOptions.map(title => ({ key: title, label: title }))]}
            >
              {(item) => <SelectItem key={item.key}>{item.label}</SelectItem>}
            </Select>
          )}
        </div>

        <div className="flex items-center gap-2">
          {/* Select all checkbox */}
          {filteredItems.length > 0 && (
            <Checkbox
              isSelected={selectedIds.size === filteredItems.length && filteredItems.length > 0}
              isIndeterminate={selectedIds.size > 0 && selectedIds.size < filteredItems.length}
              onValueChange={handleSelectAll}
              size="sm"
            >
              <span className="text-xs text-default-500">
                {selectedIds.size > 0
                  ? t('volunteering.selected_count', '{{count}} selected', { count: selectedIds.size })
                  : t('volunteering.select_all', 'Select all')}
              </span>
            </Checkbox>
          )}

          {/* Bulk actions */}
          {selectedIds.size > 0 && (
            <>
              <Button
                size="sm"
                variant="flat"
                color="success"
                startContent={<CheckCircle size={14} />}
                onPress={handleBulkApprove}
                isLoading={bulkLoading}
              >
                {t('volunteering.bulk_approve', 'Approve ({{count}})', { count: selectedIds.size })}
              </Button>
              <Button
                size="sm"
                variant="flat"
                color="danger"
                startContent={<XCircle size={14} />}
                onPress={handleBulkDecline}
                isLoading={bulkLoading}
              >
                {t('volunteering.bulk_decline', 'Decline ({{count}})', { count: selectedIds.size })}
              </Button>
            </>
          )}

          {/* Export */}
          <Button
            size="sm"
            variant="flat"
            startContent={<Download size={14} />}
            onPress={handleExport}
            isDisabled={filteredItems.length === 0}
          >
            {t('common.export', 'Export')}
          </Button>
        </div>
      </div>
    </div>
  ), [statusTab, statusCounts, searchQuery, opportunityFilter, opportunityOptions, selectedIds, filteredItems, bulkLoading, t, handleSelectAll, handleBulkApprove, handleBulkDecline, handleExport]);

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title={"Volunteer Approvals"} description={"Review and approve or decline volunteer applications"} />
        <EmptyState icon={ClipboardCheck} title={"No pending approvals"} description={"All volunteer applications have been reviewed"} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Volunteer Approvals"}
        description={"Review and approve or decline volunteer applications"}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{"Refresh"}</Button>}
      />
      <DataTable
        columns={columns}
        data={filteredItems}
        isLoading={loading}
        onRefresh={loadData}
        searchable={false}
        topContent={topContent}
      />
    </div>
  );
}

export default VolunteerApprovals;
