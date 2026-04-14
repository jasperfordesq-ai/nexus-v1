// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobLogs() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
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
      toast.error(t('system.please_select_a_date'));
      return;
    }

    try {
      const res = await adminCron.clearLogs(clearBeforeDate);
      if (res.success) {
        toast.success(res.message || t('system.logs_cleared_successfully'));
        onClearClose();
        setClearBeforeDate('');
        loadLogs();
      } else {
        toast.error(res.error || t('system.failed_to_clear_logs'));
      }
    } catch {
      toast.error(t('system.failed_to_clear_logs'));
    }
  };

  const exportToCSV = () => {
    const headers = [
      t('system.csv_header_id'),
      t('system.csv_header_job_name'),
      t('system.csv_header_status'),
      t('system.csv_header_duration'),
      t('system.csv_header_executed_at'),
      t('system.csv_header_executed_by'),
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
        title={t('system.cron_job_logs_title')}
        description={t('system.cron_job_logs_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              startContent={<Download size={16} />}
              onPress={exportToCSV}
              isDisabled={logs.length === 0}
            >
              {t('system.btn_export_csv')}
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<Trash2 size={16} />}
              onPress={onClearOpen}
            >
              {t('system.btn_clear_old_logs')}
            </Button>
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadLogs}
              isLoading={loading}
            >
              {t('system.btn_refresh')}
            </Button>
          </div>
        }
      />

      {/* Filters */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-2 pb-0">
          <Filter size={16} className="text-default-500" />
          <span className="text-sm font-medium">{t('system.filter_section_header')}</span>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label={t('system.label_status')}
            placeholder={t('system.placeholder_all_statuses')}
            size="sm"
            variant="bordered"
            selectedKeys={statusFilter ? [statusFilter] : []}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <SelectItem key="success">
              {t('system.status_success')}
            </SelectItem>
            <SelectItem key="failed">
              {t('system.status_failed')}
            </SelectItem>
          </Select>

          <Input
            label={t('system.label_job_i_d')}
            placeholder={t('system.placeholder_filter_by_job_i_d')}
            size="sm"
            variant="bordered"
            value={jobIdFilter}
            onChange={(e) => {
              setJobIdFilter(e.target.value);
              setPage(1);
            }}
          />

          <Input
            label={t('system.label_start_date')}
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
            label={t('system.label_end_date')}
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
          <Spinner size="lg" label={t('system.loading_logs')} />
        </div>
      )}

      {/* Empty state */}
      {!loading && logs.length === 0 && (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center gap-3 py-16 text-default-400">
            <FileText size={48} />
            <p className="text-lg font-medium">{t('system.no_logs_found')}</p>
            <p className="text-sm">{t('system.try_filters_or_run_cron')}</p>
          </CardBody>
        </Card>
      )}

      {/* Logs table */}
      {!loading && logs.length > 0 && (
        <Card shadow="sm">
          <CardBody className="p-0">
            <Table aria-label={t('system.label_cron_job_logs')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('system.col_job_name')}</TableColumn>
                <TableColumn>{t('system.col_status')}</TableColumn>
                <TableColumn>{t('system.col_duration')}</TableColumn>
                <TableColumn>{t('system.col_output')}</TableColumn>
                <TableColumn>{t('system.col_executed_at')}</TableColumn>
                <TableColumn>{t('system.col_executed_by')}</TableColumn>
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
                        {log.output || t('system.table_no_output')}
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
            <span>{t('system.modal_log_detail')}</span>
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
                    <p className="text-xs text-default-500 mb-1">{t('system.modal_label_status')}</p>
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
                    <p className="text-xs text-default-500 mb-1">{t('system.modal_label_duration')}</p>
                    <p className="text-sm font-medium">
                      {Number(selectedLog.duration_seconds).toFixed(2)}s
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-default-500 mb-1">{t('system.modal_label_executed_at')}</p>
                    <p className="text-sm font-medium">
                      {new Date(selectedLog.executed_at).toLocaleString()}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-default-500 mb-1">{t('system.modal_label_executed_by')}</p>
                    <p className="text-sm font-medium">
                      {selectedLog.executed_by}
                    </p>
                  </div>
                </div>

                <div>
                  <p className="text-xs text-default-500 mb-2">{t('system.modal_label_output')}</p>
                  <pre className="bg-default-100 p-3 rounded-lg text-xs overflow-x-auto whitespace-pre-wrap break-all">
                    {selectedLog.output || t('system.table_no_output')}
                  </pre>
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button size="sm" variant="flat" onPress={onClose}>
              {t('system.btn_close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Clear Logs Modal */}
      <Modal isOpen={isClearOpen} onClose={onClearClose}>
        <ModalContent>
          <ModalHeader>{t('system.clear_old_logs')}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-4">
              {t('system.clear_old_logs_warning')}
            </p>
            <Input
              label={t('system.label_delete_logs_before')}
              type="date"
              variant="bordered"
              value={clearBeforeDate}
              onChange={(e) => setClearBeforeDate(e.target.value)}
            />
          </ModalBody>
          <ModalFooter>
            <Button size="sm" variant="flat" onPress={onClearClose}>
              {t('common.cancel')}
            </Button>
            <Button
              size="sm"
              color="danger"
              onPress={handleClearLogs}
              isDisabled={!clearBeforeDate}
            >
              {t('system.clear_logs')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CronJobLogs;
