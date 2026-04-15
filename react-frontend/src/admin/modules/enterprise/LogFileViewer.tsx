// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Log File Viewer
 * View individual log file contents with filtering and auto-refresh.
 */

import { useEffect, useState, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  Button,
  Spinner,
  Select,
  SelectItem,
  Switch,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ArrowLeft,
  RefreshCw,
  Download,
  Trash2,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { LogFileContent } from '../../api/types';

import { useTranslation } from 'react-i18next';

const LINE_OPTIONS = [
  { key: '100', label: '100 lines' },
  { key: '200', label: '200 lines' },
  { key: '500', label: '500 lines' },
  { key: '1000', label: '1000 lines' },
];

const LEVEL_OPTIONS = [
  { key: 'all', label: 'All Levels' },
  { key: 'ERROR', label: 'ERROR' },
  { key: 'WARNING', label: 'WARNING' },
  { key: 'INFO', label: 'INFO' },
  { key: 'DEBUG', label: 'DEBUG' },
];

const LEVEL_REGEX = /\[(ERROR|WARNING|INFO|DEBUG)\]/i;
const LEVEL_START_REGEX = /^(error|warning|info|debug)\b/i;

function detectLevel(text: string): string | null {
  const match = text.match(LEVEL_REGEX);
  if (match?.[1]) return match[1].toUpperCase();
  const startMatch = text.match(LEVEL_START_REGEX);
  if (startMatch?.[1]) return startMatch[1].toUpperCase();
  return null;
}

function levelColorClass(level: string | null): string {
  switch (level) {
    case 'ERROR':
      return 'text-danger';
    case 'WARNING':
      return 'text-warning';
    case 'INFO':
      return 'text-primary';
    case 'DEBUG':
      return 'text-default-400';
    default:
      return 'text-default-300';
  }
}

export function LogFileViewer() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const { filename } = useParams<{ filename: string }>();

  const [content, setContent] = useState<LogFileContent | null>(null);
  const [loading, setLoading] = useState(true);
  const [lines, setLines] = useState(200);
  const [level, setLevel] = useState('all');
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [clearModalOpen, setClearModalOpen] = useState(false);
  const [clearing, setClearing] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadData = useCallback(async () => {
    if (!filename) return;
    try {
      const params: { lines?: number; level?: string } = { lines };
      if (level !== 'all') params.level = level;
      const res = await adminEnterprise.getLogFile(filename, params);
      if (res.success && res.data) {
        setContent(res.data as unknown as LogFileContent);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_log_file'));
    } finally {
      setLoading(false);
    }
  }, [filename, lines, level, toast, t])

  useEffect(() => {
    setLoading(true);
    loadData();
  }, [loadData]);

  // Auto-refresh
  useEffect(() => {
    if (autoRefresh) {
      intervalRef.current = setInterval(() => {
        loadData();
      }, 5000);
    } else if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [autoRefresh, loadData]);

  const handleClear = async () => {
    if (!filename) return;
    setClearing(true);
    try {
      const res = await adminEnterprise.clearLogFile(filename);
      if (res.success) {
        toast.success(t('enterprise.log_file_cleared'));
        setClearModalOpen(false);
        loadData();
      }
    } catch {
      toast.error(t('enterprise.failed_to_clear_log_file'));
    } finally {
      setClearing(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={filename || 'Log File'}
        description={content ? `${content.total_lines} total lines${content.filtered_count !== content.total_lines ? `, ${content.filtered_count} filtered` : ''}` : 'Loading...'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/monitoring/log-files'))}
            size="sm"
          >
            Back
          </Button>
        }
      />

      {/* Controls Bar */}
      <Card shadow="sm" className="mb-4">
        <CardBody className="flex flex-wrap items-center gap-4 p-4">
          <Select
            label={t('enterprise.label_lines')}
            selectedKeys={[String(lines)]}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0];
              if (val) setLines(Number(val));
            }}
            size="sm"
            variant="bordered"
            className="w-36"
          >
            {LINE_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>

          <Select
            label={t('enterprise.label_level')}
            selectedKeys={[level]}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0];
              if (val) setLevel(String(val));
            }}
            size="sm"
            variant="bordered"
            className="w-40"
          >
            {LEVEL_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>

          <div className="flex items-center gap-2">
            <Switch
              size="sm"
              isSelected={autoRefresh}
              onValueChange={setAutoRefresh}
            />
            <span className="text-sm text-default-600">Auto-refresh (5s)</span>
          </div>

          <div className="flex-1" />

          <Button
            variant="flat"
            startContent={<RefreshCw size={14} />}
            onPress={loadData}
            size="sm"
          >
            {t('common.refresh')}
          </Button>
          <Button
            variant="flat"
            startContent={<Download size={14} />}
            size="sm"
          >
            Download
          </Button>
          <Button
            color="danger"
            variant="flat"
            startContent={<Trash2 size={14} />}
            onPress={() => setClearModalOpen(true)}
            size="sm"
          >
            Clear
          </Button>
        </CardBody>
      </Card>

      {/* Log Content */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : content && content.content.length > 0 ? (
        <Card shadow="sm" className="bg-default-50 dark:bg-default-100/10">
          <CardBody className="p-0 overflow-x-auto">
            <pre className="text-xs font-mono leading-relaxed p-4">
              {content.content.map((line) => {
                const detectedLevel = line.level?.toUpperCase() || detectLevel(line.text);
                const colorClass = levelColorClass(detectedLevel);
                return (
                  <div key={line.line} className="flex hover:bg-default-100/50">
                    <span className="inline-block w-12 text-right pr-3 text-default-400 select-none shrink-0">
                      {line.line}
                    </span>
                    <span className={colorClass}>{line.text}</span>
                  </div>
                );
              })}
            </pre>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody className="py-16 text-center">
            <p className="text-default-500">{t('shared.log_file_empty')}</p>
          </CardBody>
        </Card>
      )}

      {/* Clear Confirmation Modal */}
      <Modal isOpen={clearModalOpen} onClose={() => setClearModalOpen(false)}>
        <ModalContent>
          <ModalHeader>{t('enterprise.clear_log_file')}</ModalHeader>
          <ModalBody>
            <p className="text-default-600">
              {t('enterprise.clear_log_confirm', { filename })}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setClearModalOpen(false)}>
              Cancel
            </Button>
            <Button color="danger" onPress={handleClear} isLoading={clearing}>
              Clear File
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default LogFileViewer;
