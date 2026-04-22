// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Audit Log
 * Activity log for GDPR actions with advanced filtering, CSV export, and detail modal.
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import {
  Card,
  CardBody,
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
} from '@heroui/react';
import { RefreshCw, Download, Filter, Eye } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprAuditEntry } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

type ActionColor = 'danger' | 'warning' | 'success' | 'primary' | 'default';

function getActionColor(action: string): ActionColor {
  const lower = action.toLowerCase();
  if (lower.includes('delete') || lower.includes('remove') || lower.includes('purge')) return 'danger';
  if (lower.includes('update') || lower.includes('edit') || lower.includes('modify')) return 'warning';
  if (lower.includes('create') || lower.includes('approve') || lower.includes('grant')) return 'success';
  if (lower.includes('view') || lower.includes('export') || lower.includes('access')) return 'primary';
  return 'default';
}

interface AuditFilters {
  action: string;
  entity_type: string;
  date_from: string;
  date_to: string;
}

const EMPTY_FILTERS: AuditFilters = {
  action: '',
  entity_type: '',
  date_from: '',
  date_to: '',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GdprAuditLog() {
  usePageTitle("Enterprise");
  const toast = useToast();

  const [entries, setEntries] = useState<GdprAuditEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const perPage = 25;

  // Filters
  const [filters, setFilters] = useState<AuditFilters>(EMPTY_FILTERS);
  const [appliedFilters, setAppliedFilters] = useState<AuditFilters>(EMPTY_FILTERS);

  // Detail modal
  const [selectedEntry, setSelectedEntry] = useState<GdprAuditEntry | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);

  // Derived unique values from loaded data for filter dropdowns
  const [allActions, setAllActions] = useState<string[]>([]);
  const [allEntityTypes, setAllEntityTypes] = useState<string[]>([]);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprAudit({
        page,
        per_page: perPage,
        action: appliedFilters.action || undefined,
        entity_type: appliedFilters.entity_type || undefined,
        date_from: appliedFilters.date_from || undefined,
        date_to: appliedFilters.date_to || undefined,
      });
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (result && typeof result === 'object') {
          const pd = result as { data?: GdprAuditEntry[]; meta?: { total?: number } };
          const items = pd.data || [];
          setEntries(items);
          setTotal(pd.meta?.total ?? items.length);

          // Build unique action/entity_type lists from returned data
          // (we accumulate across pages so the dropdown stays populated)
          setAllActions((prev) => {
            const set = new Set(prev);
            items.forEach((e) => { if (e.action) set.add(e.action); });
            return Array.from(set).sort();
          });
          setAllEntityTypes((prev) => {
            const set = new Set(prev);
            items.forEach((e) => { if (e.entity_type) set.add(e.entity_type); });
            return Array.from(set).sort();
          });
        } else if (Array.isArray(result)) {
          setEntries(result);
          setTotal(result.length);
        }
      }
    } catch {
      toast.error("Failed to load GDPR audit log");
    } finally {
      setLoading(false);
    }
  }, [page, appliedFilters, toast, t]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleApplyFilters = useCallback(() => {
    setPage(1);
    setAppliedFilters({ ...filters });
  }, [filters]);

  const handleClearFilters = useCallback(() => {
    setFilters(EMPTY_FILTERS);
    setAppliedFilters(EMPTY_FILTERS);
    setPage(1);
  }, []);

  const hasActiveFilters = useMemo(
    () => Object.values(appliedFilters).some((v) => v !== ''),
    [appliedFilters]
  );

  const handleExportCsv = useCallback(() => {
    const url = adminEnterprise.getGdprAuditExportUrl({
      action: appliedFilters.action || undefined,
      entity_type: appliedFilters.entity_type || undefined,
      date_from: appliedFilters.date_from || undefined,
      date_to: appliedFilters.date_to || undefined,
    });
    window.open(url, '_blank');
  }, [appliedFilters]);

  const handleViewEntry = useCallback((entry: GdprAuditEntry) => {
    setSelectedEntry(entry);
    setIsDetailOpen(true);
  }, []);

  // ─── Columns ────────────────────────────────────────────────────────

  const columns: Column<GdprAuditEntry>[] = useMemo(
    () => [
      { key: 'id', label: "ID", sortable: true },
      { key: 'user_name', label: "User", sortable: true },
      {
        key: 'action',
        label: "Action",
        sortable: true,
        render: (e) => (
          <Chip size="sm" variant="flat" color={getActionColor(e.action)}>
            {e.action}
          </Chip>
        ),
      },
      {
        key: 'entity_type',
        label: "GDPR Col Entity",
        render: (e) => (
          <Chip size="sm" variant="flat" color="default">
            {e.entity_type}
          </Chip>
        ),
      },
      { key: 'entity_id', label: "GDPR Col Entity ID" },
      {
        key: 'ip_address',
        label: "GDPR Col IP Address",
        render: (e) => (
          <span className="font-mono text-xs">{e.ip_address || '-'}</span>
        ),
      },
      {
        key: 'created_at',
        label: "Date",
        sortable: true,
        render: (e) => new Date(e.created_at).toLocaleString(),
      },
      {
        key: 'actions',
        label: '',
        render: (e) => (
          <Button
            isIconOnly
            variant="light"
            size="sm"
            onPress={() => handleViewEntry(e)}
            aria-label="View detail"
          >
            <Eye size={16} />
          </Button>
        ),
      },
    ],
    [t, handleViewEntry]
  );

  // ─── Render ─────────────────────────────────────────────────────────

  return (
    <div className="space-y-4">
      <PageHeader
        title={"GDPR Audit Log"}
        description={"Audit trail of all GDPR-related actions taken on this platform"}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={handleExportCsv}
              size="sm"
            >
              {"GDPR Export CSV"}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {"Refresh"}
            </Button>
          </div>
        }
      />

      {/* Filter Bar */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <Select
              label={"GDPR Filter"}
              size="sm"
              className="w-40"
              selectedKeys={filters.action ? [filters.action] : []}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                setFilters((f) => ({ ...f, action: val || '' }));
              }}
            >
              {allActions.map((a) => (
                <SelectItem key={a}>{a}</SelectItem>
              ))}
            </Select>

            <Select
              label={"GDPR Filter Entity"}
              size="sm"
              className="w-40"
              selectedKeys={filters.entity_type ? [filters.entity_type] : []}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                setFilters((f) => ({ ...f, entity_type: val || '' }));
              }}
            >
              {allEntityTypes.map((et) => (
                <SelectItem key={et}>{et}</SelectItem>
              ))}
            </Select>

            <Input
              label={"From Date"}
              type="date"
              size="sm"
              className="w-40"
              value={filters.date_from}
              onValueChange={(v) => setFilters((f) => ({ ...f, date_from: v }))}
            />

            <Input
              label={"To Date"}
              type="date"
              size="sm"
              className="w-40"
              value={filters.date_to}
              onValueChange={(v) => setFilters((f) => ({ ...f, date_to: v }))}
            />

            <Button
              color="primary"
              variant="flat"
              size="sm"
              startContent={<Filter size={16} />}
              onPress={handleApplyFilters}
            >
              {"GDPR Apply"}
            </Button>

            {hasActiveFilters && (
              <Button
                variant="light"
                size="sm"
                onPress={handleClearFilters}
              >
                {"GDPR Clear"}
              </Button>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={entries}
        isLoading={loading}
        searchable={false}
        emptyContent={"No GDPR audit entries"}
        totalItems={total}
        page={page}
        pageSize={perPage}
        onPageChange={setPage}
      />

      {/* Detail Modal */}
      <Modal isOpen={isDetailOpen} onOpenChange={setIsDetailOpen} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {`GDPR Audit Entry`}
              </ModalHeader>
              <ModalBody>
                {selectedEntry ? (
                  <div className="space-y-4">
                    {/* Top metadata grid */}
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail"}</p>
                        <Chip size="sm" variant="flat" color={getActionColor(selectedEntry.action)}>
                          {selectedEntry.action}
                        </Chip>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail Entity"}</p>
                        <Chip size="sm" variant="flat" color="default">
                          {selectedEntry.entity_type}
                        </Chip>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail Entity ID"}</p>
                        <p className="text-sm">{selectedEntry.entity_id}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail Admin User"}</p>
                        <p className="text-sm">{selectedEntry.user_name || `ID ${selectedEntry.admin_id}`}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail IP Address"}</p>
                        <p className="text-sm font-mono">{selectedEntry.ip_address || '-'}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">{"GDPR Detail Timestamp"}</p>
                        <p className="text-sm">{new Date(selectedEntry.created_at).toLocaleString()}</p>
                      </div>
                    </div>

                    {/* Old/New Values */}
                    {(selectedEntry.old_value || selectedEntry.new_value) && (
                      <div className={`grid gap-4 ${selectedEntry.old_value && selectedEntry.new_value ? 'md:grid-cols-2' : 'grid-cols-1'}`}>
                        {selectedEntry.old_value && (
                          <div>
                            <p className="text-xs text-default-400 mb-1">{"GDPR Old Value"}</p>
                            <pre className="text-xs bg-default-100 border border-default-200 rounded-lg p-3 overflow-auto max-h-60 whitespace-pre-wrap break-all">
                              {selectedEntry.old_value}
                            </pre>
                          </div>
                        )}
                        {selectedEntry.new_value && (
                          <div>
                            <p className="text-xs text-default-400 mb-1">{"GDPR New Value"}</p>
                            <pre className="text-xs bg-default-100 border border-default-200 rounded-lg p-3 overflow-auto max-h-60 whitespace-pre-wrap break-all">
                              {selectedEntry.new_value}
                            </pre>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="flex justify-center py-8">
                    <Spinner />
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>
                  {"GDPR Close"}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GdprAuditLog;
