/**
 * Cron Job Logs
 * View and filter cron job execution logs with detail modal
 * Parity: PHP CronJobController::logs()
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Button,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Select,
  SelectItem,
  Input,
  Pagination,
} from '@heroui/react';
import {
  FileText,
  RefreshCw,
  CheckCircle,
  XCircle,
  Calendar,
  Trash2,
  Download,
  Filter,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminCron } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CronLog } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobLogs() {
  usePageTitle('Admin - Cron Job Logs');
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [logs, setLogs] = useState<CronLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedLog, setSelectedLog] = useState<CronLog | null>(null);

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [jobIdFilter, setJobIdFilter] = useState<string>('');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');

  // Pagination
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const limit = 50;

  // Clear logs modal
  const {
    isOpen: isClearOpen,
    onOpen: onClearOpen,
    onClose: onClearClose,
  } = useDisclosure();
  const [clearBeforeDate, setClearBeforeDate] = useState<string>('');

  const loadLogs = useCallback(async () => {
    setLoading(true);
    try {
      const offset = (page - 1) * limit;
      const res = await adminCron.getLogs({
        status: statusFilter || undefined,
        jobId: jobIdFilter || undefined,
        startDate: startDate || undefined,
        endDate: endDate || undefined,
        limit,
        offset,
      });

      if (res.success && res.data) {
        setLogs(Array.isArray(res.data) ? res.data : []);
        setTotal(res.meta?.total ?? 0);
      }
    } catch {
      setLogs([]);
      setTotal(0);
    }
    setLoading(false);
  }, [page, statusFilter, jobIdFilter, startDate, endDate]);

  const handleViewDetail = async (log: CronLog) => {
    setSelectedLog(log);
    onOpen();
  };

  const handleClearLogs = async () => {
    if (!clearBeforeDate) {
      toast.error('Please select a date');
      return;
    }

    try {
      const res = await adminCron.clearLogs(clearBeforeDate);
      if (res.success) {
        toast.success(res.message || 'Logs cleared successfully');
        onClearClose();
        setClearBeforeDate('');
        loadLogs();
      } else {
        toast.error(res.error || 'Failed to clear logs');
      }
    } catch {
      toast.error('Failed to clear logs');
    }
  };

  const exportToCSV = () => {
    const headers = [
      'ID',
      'Job Name',
      'Status',
      'Duration (s)',
      'Executed At',
      'Executed By',
    ];
    const rows = logs.map((log) => [
      log.id,
      log.job_name,
      log.status,
      log.duration_seconds,
      log.executed_at,
      log.executed_by,
    ]);

    const csv = [
      headers.join(','),
      ...rows.map((row) => row.map((cell) => `"${cell}"`).join(',')),
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cron-logs-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const totalPages = Math.ceil(total / limit);

  return (
    <div>
      <PageHeader
        title="Cron Job Logs"
        description="View execution history and troubleshoot failures"
        actions={
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              startContent={<Download size={16} />}
              onPress={exportToCSV}
              isDisabled={logs.length === 0}
            >
              Export CSV
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<Trash2 size={16} />}
              onPress={onClearOpen}
            >
              Clear Old Logs
            </Button>
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadLogs}
              isLoading={loading}
            >
              Refresh
            </Button>
          </div>
        }
      />

      {/* Filters */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-2 pb-0">
          <Filter size={16} className="text-default-500" />
          <span className="text-sm font-medium">Filters</span>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Status"
            placeholder="All statuses"
            size="sm"
            variant="bordered"
            selectedKeys={statusFilter ? [statusFilter] : []}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <SelectItem key="success">
              Success
            </SelectItem>
            <SelectItem key="failed">
              Failed
            </SelectItem>
          </Select>

          <Input
            label="Job ID"
            placeholder="Filter by job ID"
            size="sm"
            variant="bordered"
            value={jobIdFilter}
            onChange={(e) => {
              setJobIdFilter(e.target.value);
              setPage(1);
            }}
          />

          <Input
            label="Start Date"
            type="date"
            size="sm"
            variant="bordered"
            value={startDate}
            onChange={(e) => {
              setStartDate(e.target.value);
              setPage(1);
            }}
          />

          <Input
            label="End Date"
            type="date"
            size="sm"
            variant="bordered"
            value={endDate}
            onChange={(e) => {
              setEndDate(e.target.value);
              setPage(1);
            }}
          />
        </CardBody>
      </Card>

      {/* Loading state */}
      {loading && logs.length === 0 && (
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" label="Loading logs..." />
        </div>
      )}

      {/* Empty state */}
      {!loading && logs.length === 0 && (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center gap-3 py-16 text-default-400">
            <FileText size={48} />
            <p className="text-lg font-medium">No logs found</p>
            <p className="text-sm">Try adjusting your filters or run a cron job</p>
          </CardBody>
        </Card>
      )}

      {/* Logs table */}
      {!loading && logs.length > 0 && (
        <Card shadow="sm">
          <CardBody className="p-0">
            <Table aria-label="Cron job logs" removeWrapper>
              <TableHeader>
                <TableColumn>JOB NAME</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>DURATION</TableColumn>
                <TableColumn>OUTPUT</TableColumn>
                <TableColumn>EXECUTED AT</TableColumn>
                <TableColumn>EXECUTED BY</TableColumn>
              </TableHeader>
              <TableBody>
                {logs.map((log) => (
                  <TableRow
                    key={log.id}
                    className="cursor-pointer hover:bg-default-100"
                    onClick={() => handleViewDetail(log)}
                  >
                    <TableCell>
                      <div className="font-medium">{log.job_name}</div>
                      <div className="text-xs text-default-400">{log.job_id}</div>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={log.status === 'success' ? 'success' : 'danger'}
                        startContent={
                          log.status === 'success' ? (
                            <CheckCircle size={12} />
                          ) : (
                            <XCircle size={12} />
                          )
                        }
                      >
                        {log.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">
                        {Number(log.duration_seconds).toFixed(2)}s
                      </span>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-600 line-clamp-2">
                        {log.output || 'No output'}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5 text-sm">
                        <Calendar size={14} className="text-default-400" />
                        {new Date(log.executed_at).toLocaleString()}
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-600">
                        {log.executed_by}
                      </span>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex justify-center border-t border-divider p-4">
                <Pagination
                  total={totalPages}
                  page={page}
                  onChange={setPage}
                  size="sm"
                  showControls
                />
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Detail Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="3xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1">
            <span>Log Detail</span>
            {selectedLog && (
              <span className="text-sm font-normal text-default-500">
                {selectedLog.job_name} ({selectedLog.job_id})
              </span>
            )}
          </ModalHeader>
          <ModalBody>
            {selectedLog && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs text-default-500 mb-1">Status</p>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={
                        selectedLog.status === 'success' ? 'success' : 'danger'
                      }
                      startContent={
                        selectedLog.status === 'success' ? (
                          <CheckCircle size={12} />
                        ) : (
                          <XCircle size={12} />
                        )
                      }
                    >
                      {selectedLog.status}
                    </Chip>
                  </div>
                  <div>
                    <p className="text-xs text-default-500 mb-1">Duration</p>
                    <p className="text-sm font-medium">
                      {Number(selectedLog.duration_seconds).toFixed(2)}s
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-default-500 mb-1">Executed At</p>
                    <p className="text-sm font-medium">
                      {new Date(selectedLog.executed_at).toLocaleString()}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-default-500 mb-1">Executed By</p>
                    <p className="text-sm font-medium">
                      {selectedLog.executed_by}
                    </p>
                  </div>
                </div>

                <div>
                  <p className="text-xs text-default-500 mb-2">Output</p>
                  <pre className="bg-default-100 p-3 rounded-lg text-xs overflow-x-auto whitespace-pre-wrap break-all">
                    {selectedLog.output || 'No output'}
                  </pre>
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button size="sm" variant="flat" onPress={onClose}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Clear Logs Modal */}
      <Modal isOpen={isClearOpen} onClose={onClearClose}>
        <ModalContent>
          <ModalHeader>Clear Old Logs</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-4">
              Delete all logs executed before the selected date. This action cannot be
              undone.
            </p>
            <Input
              label="Delete logs before"
              type="date"
              variant="bordered"
              value={clearBeforeDate}
              onChange={(e) => setClearBeforeDate(e.target.value)}
            />
          </ModalBody>
          <ModalFooter>
            <Button size="sm" variant="flat" onPress={onClearClose}>
              Cancel
            </Button>
            <Button
              size="sm"
              color="danger"
              onPress={handleClearLogs}
              isDisabled={!clearBeforeDate}
            >
              Clear Logs
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CronJobLogs;
