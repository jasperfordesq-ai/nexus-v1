import { useState } from 'react';
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
import { Search, RefreshCw, CheckCircle2, XCircle, AlertCircle, Flag } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/contexts/ToastContext';
import PageHeader from '@/admin/components/PageHeader';
import ConfirmModal from '@/admin/components/ConfirmModal';
import { adminModeration } from '@/admin/api/adminApi';
import type { AdminReport, PaginatedResponse, ModerationStats } from '@/admin/api/types';

const CONTENT_TYPES = [
  { label: 'All Types', value: '' },
  { label: 'Post', value: 'post' },
  { label: 'Comment', value: 'comment' },
  { label: 'Review', value: 'review' },
  { label: 'User', value: 'user' },
  { label: 'Listing', value: 'listing' },
];

const STATUS_FILTERS = [
  { label: 'All Status', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Dismissed', value: 'dismissed' },
];

export default function ReportsManagement() {
  usePageTitle('Reports Management');

  const toast = useToast();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [activeType, setActiveType] = useState('');
  const [activeStatus, setActiveStatus] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: 'resolve' | 'dismiss';
    report: AdminReport;
  } | null>(null);

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
    return params.toString();
  };

  const { data, isLoading, error, execute } = useApi<PaginatedResponse<AdminReport>>(
    `/v2/admin/reports?${buildQueryString()}`,
    { immediate: true, deps: [page, activeSearch, activeType, activeStatus] }
  );

  const handleSearch = () => {
    setActiveSearch(search);
    setActiveType(typeFilter);
    setActiveStatus(statusFilter);
    setPage(1);
  };

  const handleClear = () => {
    setSearch('');
    setTypeFilter('');
    setStatusFilter('');
    setActiveSearch('');
    setActiveType('');
    setActiveStatus('');
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
            ? 'Report resolved successfully'
            : 'Report dismissed successfully'
        );
        setConfirmAction(null);
        execute();
        refetchStats();
      } else {
        toast.error(response.error || 'Action failed');
      }
    } catch (err) {
      toast.error('An error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  const reports = data?.data || [];
  const totalPages = data?.meta ? Math.ceil(data.meta.total / data.meta.per_page) : 1;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reports Management"
        description="Manage user-submitted reports and flagged content"
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
            Refresh
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
                <p className="text-2xl font-bold">{(stats as any)?.total || 0}</p>
                <p className="text-sm text-default-500">Total Reports</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-warning-100 dark:bg-warning-900/30">
                <AlertCircle className="w-6 h-6 text-warning" />
              </div>
              <div>
                <p className="text-2xl font-bold">{(stats as any)?.pending || 0}</p>
                <p className="text-sm text-default-500">Pending</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-success-100 dark:bg-success-900/30">
                <CheckCircle2 className="w-6 h-6 text-success" />
              </div>
              <div>
                <p className="text-2xl font-bold">{(stats as any)?.resolved || 0}</p>
                <p className="text-sm text-default-500">Resolved</p>
              </div>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-row items-center gap-3">
              <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-default-100 dark:bg-default-900/30">
                <XCircle className="w-6 h-6 text-default-500" />
              </div>
              <div>
                <p className="text-2xl font-bold">{(stats as any)?.dismissed || 0}</p>
                <p className="text-sm text-default-500">Dismissed</p>
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-4">
        <Input
          placeholder="Search reports..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label="Content Type"
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
          label="Status"
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
        <div className="flex gap-2">
          <Button color="primary" onPress={handleSearch}>
            Apply
          </Button>
          <Button variant="flat" onPress={handleClear}>
            Clear
          </Button>
        </div>
      </div>

      {/* Results Count */}
      {data?.meta && (
        <div className="text-sm text-default-500">
          Showing {reports.length} of {data.meta.total} reports
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          Failed to load reports. Please try again.
        </div>
      )}

      {/* Table */}
      <Table aria-label="Reports table">
        <TableHeader>
          <TableColumn>REPORTER</TableColumn>
          <TableColumn>CONTENT TYPE</TableColumn>
          <TableColumn>REASON</TableColumn>
          <TableColumn>DESCRIPTION</TableColumn>
          <TableColumn>STATUS</TableColumn>
          <TableColumn>CREATED</TableColumn>
          <TableColumn>ACTIONS</TableColumn>
        </TableHeader>
        <TableBody
          items={reports}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent={
            <div className="text-center py-8 text-default-400">
              {activeSearch || activeType || activeStatus
                ? 'No reports match your filters'
                : 'No reports to review'}
            </div>
          }
        >
          {(report) => (
            <TableRow key={report.id}>
              <TableCell>
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
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat">
                  {report.content_type}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-sm">{report.reason}</span>
              </TableCell>
              <TableCell>
                <p className="text-sm line-clamp-2 max-w-md">{report.description}</p>
              </TableCell>
              <TableCell>
                {report.status === 'pending' && (
                  <Chip size="sm" color="warning" variant="flat">Pending</Chip>
                )}
                {report.status === 'resolved' && (
                  <Chip size="sm" color="success" variant="flat">Resolved</Chip>
                )}
                {report.status === 'dismissed' && (
                  <Chip size="sm" color="default" variant="flat">Dismissed</Chip>
                )}
              </TableCell>
              <TableCell>
                <span className="text-sm text-default-500">
                  {new Date(report.created_at).toLocaleDateString()}
                </span>
              </TableCell>
              <TableCell>
                {report.status === 'pending' && (
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      color="success"
                      startContent={<CheckCircle2 className="w-4 h-4" />}
                      onPress={() => setConfirmAction({ type: 'resolve', report })}
                    >
                      Resolve
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="default"
                      startContent={<XCircle className="w-4 h-4" />}
                      onPress={() => setConfirmAction({ type: 'dismiss', report })}
                    >
                      Dismiss
                    </Button>
                  </div>
                )}
                {report.status !== 'pending' && (
                  <div className="text-sm text-default-400">
                    {report.resolved_by && `By ${report.resolved_by}`}
                  </div>
                )}
              </TableCell>
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
        title={confirmAction?.type === 'resolve' ? 'Resolve Report' : 'Dismiss Report'}
        message={
          confirmAction?.type === 'resolve'
            ? 'Are you sure you want to mark this report as resolved? This indicates you have taken appropriate action.'
            : 'Are you sure you want to dismiss this report? This indicates no action is needed.'
        }
        confirmLabel={confirmAction?.type === 'resolve' ? 'Resolve Report' : 'Dismiss Report'}
        confirmColor={confirmAction?.type === 'resolve' ? 'primary' : 'warning'}
        isLoading={actionLoading}
      />
    </div>
  );
}
