// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useState, useEffect } from 'react';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Chip,
  Avatar,
  Pagination,
  Spinner,
  Card,
  CardBody,
} from '@heroui/react';
import Search from 'lucide-react/icons/search';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import XCircle from 'lucide-react/icons/circle-x';
import AlertCircle from 'lucide-react/icons/circle-alert';
import Flag from 'lucide-react/icons/flag';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/contexts/ToastContext';
import PageHeader from '@/admin/components/PageHeader';
import ConfirmModal from '@/admin/components/ConfirmModal';
import { adminModeration } from '@/admin/api/adminApi';
import { adminSuper } from '@/admin/api/adminApi';
import type { AdminReport, ModerationStats } from '@/admin/api/types';

export default function ReportsManagement() {
  usePageTitle("Moderation");

  const CONTENT_TYPES = [
    { label: "All Types", value: '' },
    { label: "Post", value: 'post' },
    { label: "Comment", value: 'comment' },
    { label: "Review", value: 'review' },
    { label: "User", value: 'user' },
    { label: "Listing", value: 'listing' },
  ];

  const STATUS_FILTERS = [
    { label: "All Statuses", value: '' },
    { label: "Pending", value: 'pending' },
    { label: "Resolved", value: 'resolved' },
    { label: "Dismissed", value: 'dismissed' },
  ];

  const toast = useToast();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [tenantFilter, setTenantFilter] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [activeType, setActiveType] = useState('');
  const [activeStatus, setActiveStatus] = useState('');
  const [activeTenant, setActiveTenant] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: 'resolve' | 'dismiss';
    report: AdminReport;
  } | null>(null);
  const [tenants, setTenants] = useState<Array<{ id: number; name: string }>>([]);

  // Load tenants list for super admin filter
  useEffect(() => {
    if (!isSuperAdmin) return;
    adminSuper.listTenants().then((res) => {
      if (res.success && Array.isArray(res.data)) {
        setTenants(res.data.map((t) => ({
          id: Number(t.id),
          name: String(t.name || 'Unknown'),
        })));
      }
    }).catch(() => {
      // Tenant list is optional; silently fail
    });
  }, [isSuperAdmin]);

  const { data: stats, execute: refetchStats } = useApi<ModerationStats>(
    '/v2/admin/reports/stats',
    { immediate: true, deps: [] }
  );

  // Build query params for the endpoint
  const buildQueryString = () => {
    const params = new URLSearchParams();
    params.append('page', page.toString());
    params.append('limit', '20');
    if (activeSearch) params.append('search', activeSearch);
    if (activeType) params.append('type', activeType);
    if (activeStatus) params.append('status', activeStatus);
    if (activeTenant && activeTenant !== 'all') params.append('tenant_id', activeTenant);
    return params.toString();
  };

  const { data, isLoading, error, execute, meta } = useApi<AdminReport[]>(
    `/v2/admin/reports?${buildQueryString()}`,
    { immediate: true, deps: [page, activeSearch, activeType, activeStatus, activeTenant] }
  );

  const handleSearch = () => {
    setActiveSearch(search);
    setActiveType(typeFilter);
    setActiveStatus(statusFilter);
    setActiveTenant(tenantFilter);
    setPage(1);
  };

  const handleClear = () => {
    setSearch('');
    setTypeFilter('');
    setStatusFilter('');
    setTenantFilter('');
    setActiveSearch('');
    setActiveType('');
    setActiveStatus('');
    setActiveTenant('');
    setPage(1);
  };

  const handleAction = async () => {
    if (!confirmAction) return;

    setActionLoading(true);
    try {
      const response = confirmAction.type === 'resolve'
        ? await adminModeration.resolveReport(confirmAction.report.id)
        : await adminModeration.dismissReport(confirmAction.report.id);

      if (response.success) {
        toast.success(
          confirmAction.type === 'resolve'
            ? "Report resolved successfully"
            : "Report dismissed successfully"
        );
        setConfirmAction(null);
        execute();
        refetchStats();
      } else {
        toast.error(response.error || "Failed");
      }
    } catch {
      toast.error("An error occurred");
    } finally {
      setActionLoading(false);
    }
  };

  const reports = data || [];
  const totalPages = meta?.total_pages || 1;

  // Build cell content for a report row
  const renderCells = (report: AdminReport): React.ReactElement[] => {
    const cells: React.ReactElement[] = [
      <TableCell key="reporter">
        <div className="flex items-center gap-3">
          <Avatar
            src={report.reporter_avatar || undefined}
            name={report.reporter_name}
            size="sm"
            className="flex-shrink-0"
          />
          <div className="flex flex-col">
            <span className="text-sm font-medium">{report.reporter_name}</span>
            <span className="text-xs text-default-400">ID: {report.reporter_id}</span>
          </div>
        </div>
      </TableCell>,
    ];

    if (isSuperAdmin) {
      cells.push(
        <TableCell key="tenant">
          <Chip size="sm" variant="flat" color="secondary">
            {report.tenant_name}
          </Chip>
        </TableCell>
      );
    }

    cells.push(
      <TableCell key="contentType">
        <Chip size="sm" variant="flat">
          {report.content_type}
        </Chip>
      </TableCell>,
      <TableCell key="reason">
        <span className="text-sm">{report.reason}</span>
      </TableCell>,
      <TableCell key="description">
        <p className="text-sm line-clamp-2 max-w-md">{report.description}</p>
      </TableCell>,
      <TableCell key="status">
        {(report.status === 'open' || report.status === 'pending') && (
          <Chip size="sm" color="warning" variant="flat">{"Pending"}</Chip>
        )}
        {report.status === 'resolved' && (
          <Chip size="sm" color="success" variant="flat">{"Resolved"}</Chip>
        )}
        {report.status === 'dismissed' && (
          <Chip size="sm" color="default" variant="flat">{"Dismissed"}</Chip>
        )}
      </TableCell>,
      <TableCell key="created">
        <span className="text-sm text-default-500">
          {new Date(report.created_at).toLocaleDateString()}
        </span>
      </TableCell>,
      <TableCell key="actions">
        {(report.status === 'open' || report.status === 'pending') && (
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              color="success"
              startContent={<CheckCircle2 className="w-4 h-4" />}
              onPress={() => setConfirmAction({ type: 'resolve', report })}
            >
              {"Resolve"}
            </Button>
            <Button
              size="sm"
              variant="flat"
              color="default"
              startContent={<XCircle className="w-4 h-4" />}
              onPress={() => setConfirmAction({ type: 'dismiss', report })}
            >
              {"Dismiss"}
            </Button>
          </div>
        )}
        {report.status !== 'open' && report.status !== 'pending' && (
          <div className="text-sm text-default-400">
            {report.resolved_by && `Resolved by`}
          </div>
        )}
      </TableCell>
    );

    return cells;
  };

  // Determine columns based on super admin status
  const columns = isSuperAdmin
    ? ["Reporter", "Tenant", "Content Type", "Reason", "Description", "Status", "Created", "Actions"]
    : ["Reporter", "Content Type", "Reason", "Description", "Status", "Created", "Actions"];

  return (
    <div className="space-y-6">
      <PageHeader
        title={"Reports Management"}
        description={isSuperAdmin ? "Review content reports across all tenants" : "Review and resolve content reports submitted by members"}
        actions={
          <Button
            color="primary"
            variant="flat"
            startContent={<RefreshCw className="w-4 h-4" />}
            onPress={() => {
              execute();
              refetchStats();
            }}
            isLoading={isLoading}
          >
            {"Refresh"}
          </Button>
        }
      />

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                <Flag className="w-6 h-6 text-primary" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats.total ?? ((stats.reports_pending ?? 0) + (stats.reports_resolved ?? 0) + (stats.reports_dismissed ?? 0))}</p>
                <p className="text-sm text-default-500">{"Total Reports"}</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-warning-100 dark:bg-warning-900/30">
                <AlertCircle className="w-6 h-6 text-warning" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats.pending ?? stats.reports_pending ?? 0}</p>
                <p className="text-sm text-default-500">{"Pending"}</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-success-100 dark:bg-success-900/30">
                <CheckCircle2 className="w-6 h-6 text-success" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats.resolved ?? stats.reports_resolved ?? 0}</p>
                <p className="text-sm text-default-500">{"Resolved"}</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-default-100 dark:bg-default-900/30">
                <XCircle className="w-6 h-6 text-default-500" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats.dismissed ?? stats.reports_dismissed ?? 0}</p>
                <p className="text-sm text-default-500">{"Dismissed"}</p>
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-4">
        <Input
          placeholder={"Search Reports..."}
          aria-label={"Search Reports"}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label={"Content Type"}
          selectedKeys={typeFilter ? [typeFilter] : []}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="w-full sm:w-48"
        >
          {CONTENT_TYPES.map((type) => (
            <SelectItem key={type.value}>
              {type.label}
            </SelectItem>
          ))}
        </Select>
        <Select
          label={"Status"}
          selectedKeys={statusFilter ? [statusFilter] : []}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-full sm:w-48"
        >
          {STATUS_FILTERS.map((status) => (
            <SelectItem key={status.value}>
              {status.label}
            </SelectItem>
          ))}
        </Select>
        {isSuperAdmin && (
          <Select
            label={"Tenant"}
            selectedKeys={tenantFilter ? [tenantFilter] : []}
            onChange={(e) => setTenantFilter(e.target.value)}
            className="w-full sm:w-56"
          >
            {[
              <SelectItem key="all">{"All Tenants"}</SelectItem>,
              ...tenants.map((t) => (
                <SelectItem key={t.id.toString()}>
                  {t.name}
                </SelectItem>
              )),
            ]}
          </Select>
        )}
        <div className="flex gap-2">
          <Button color="primary" onPress={handleSearch}>
            {"Apply"}
          </Button>
          <Button variant="flat" onPress={handleClear}>
            {"Clear"}
          </Button>
        </div>
      </div>

      {/* Results Count */}
      {meta && (
        <div className="text-sm text-default-500">
          {`Showing`}
          {isSuperAdmin && !activeTenant && ` (${"All Tenants"})`}
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          {"Failed to load reports"}
        </div>
      )}

      {/* Table */}
      <Table aria-label={"Reports Table"}>
        <TableHeader>
          {columns.map((col) => (
            <TableColumn key={col}>{col}</TableColumn>
          ))}
        </TableHeader>
        <TableBody
          items={reports}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent={
            <div className="text-center py-8 text-default-400">
              {activeSearch || activeType || activeStatus
                ? "No reports match filters"
                : "No reports to review"}
            </div>
          }
        >
          {(report) => (
            <TableRow key={report.id}>
              {renderCells(report)}
            </TableRow>
          )}
        </TableBody>
      </Table>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center">
          <Pagination
            total={totalPages}
            page={page}
            onChange={setPage}
            showControls
            color="primary"
          />
        </div>
      )}

      {/* Confirm Modal */}
      <ConfirmModal
        isOpen={!!confirmAction}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleAction}
        title={confirmAction?.type === 'resolve' ? "Resolve Report" : "Dismiss Report"}
        message={
          confirmAction?.type === 'resolve'
            ? `Are you sure you want to mark this report as resolved?`
            : `Are you sure you want to dismiss this report?`
        }
        confirmLabel={confirmAction?.type === 'resolve' ? "Resolve Report" : "Dismiss Report"}
        confirmColor={confirmAction?.type === 'resolve' ? 'primary' : 'warning'}
        isLoading={actionLoading}
      />
    </div>
  );
}
