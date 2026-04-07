// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Log Files
 * Browse server log files with filtering and download.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Spinner, Chip, Input } from '@heroui/react';
import {
  FileText,
  AlertTriangle,
  Clock,
  RefreshCw,
  Download,
  Search,
  HardDrive,
  Files,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { LogFile } from '../../api/types';

import { useTranslation } from 'react-i18next';

type FilterType = 'all' | 'errors' | 'application' | 'cron';

function getFileIcon(name: string) {
  const lower = name.toLowerCase();
  if (lower.includes('error') || lower.includes('fatal')) return AlertTriangle;
  if (lower.includes('cron') || lower.includes('schedule')) return Clock;
  return FileText;
}

function getFileIconColor(name: string): string {
  const lower = name.toLowerCase();
  if (lower.includes('error') || lower.includes('fatal')) return 'text-danger';
  if (lower.includes('cron') || lower.includes('schedule')) return 'text-warning';
  return 'text-primary';
}

function matchesFilter(name: string, filter: FilterType): boolean {
  if (filter === 'all') return true;
  const lower = name.toLowerCase();
  if (filter === 'errors') return lower.includes('error') || lower.includes('fatal');
  if (filter === 'cron') return lower.includes('cron') || lower.includes('schedule');
  if (filter === 'application') return !lower.includes('error') && !lower.includes('fatal') && !lower.includes('cron') && !lower.includes('schedule');
  return true;
}

export function LogFiles() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [files, setFiles] = useState<LogFile[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<FilterType>('all');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getLogFiles();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setFiles(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_log_files'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const filtered = files.filter((f) => {
    if (search && !f.name.toLowerCase().includes(search.toLowerCase())) return false;
    return matchesFilter(f.name, filter);
  });

  const totalSize = files.reduce((sum, f) => sum + (f.size_bytes || 0), 0);
  const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const filters: { key: FilterType; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'errors', label: 'Errors' },
    { key: 'application', label: 'Application' },
    { key: 'cron', label: 'Cron' },
  ];

  return (
    <div>
      <PageHeader
        title="Log Files"
        description="Browse and view server log files"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {t('common.refresh')}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="flex gap-4 mb-6">
        <Card shadow="sm" className="flex-1">
          <CardBody className="flex flex-row items-center gap-3 p-4">
            <Files size={20} className="text-primary" />
            <div>
              <p className="text-xs text-default-500">Total Files</p>
              <p className="text-lg font-bold text-foreground">{files.length}</p>
            </div>
          </CardBody>
        </Card>
        <Card shadow="sm" className="flex-1">
          <CardBody className="flex flex-row items-center gap-3 p-4">
            <HardDrive size={20} className="text-warning" />
            <div>
              <p className="text-xs text-default-500">Total Size</p>
              <p className="text-lg font-bold text-foreground">{formatBytes(totalSize)}</p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Search & Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <Input
          placeholder="Search log files..."
          startContent={<Search size={16} className="text-default-400" />}
          value={search}
          onValueChange={setSearch}
          variant="bordered"
          size="sm"
          className="w-64"
        />
        <div className="flex gap-2">
          {filters.map((f) => (
            <Button
              key={f.key}
              size="sm"
              variant={filter === f.key ? 'solid' : 'flat'}
              color={filter === f.key ? 'primary' : 'default'}
              onPress={() => setFilter(f.key)}
            >
              {f.label}
            </Button>
          ))}
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : filtered.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="py-16 text-center">
            <p className="text-default-500">No log files found</p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((file) => {
            const Icon = getFileIcon(file.name);
            const iconColor = getFileIconColor(file.name);

            return (
              <Card key={file.name} shadow="sm" isPressable as={Link} to={tenantPath(`/admin/enterprise/monitoring/log-files/${file.name}`)}>
                <CardBody className="p-4">
                  <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-default-100">
                      <Icon size={20} className={iconColor} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="font-mono text-sm font-bold text-foreground truncate">
                        {file.name}
                      </p>
                      <div className="flex items-center gap-2 mt-1">
                        <Chip size="sm" variant="flat" color="default">
                          {file.size}
                        </Chip>
                        <span className="text-xs text-default-400">
                          {file.line_count} lines
                        </span>
                      </div>
                      <p className="text-xs text-default-400 mt-1">
                        {new Date(file.modified_at).toLocaleString()}
                      </p>
                    </div>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Download"
                      onPress={() => window.open(`/v2/admin/enterprise/monitoring/log-files/${file.name}?download=1`, '_blank')}
                    >
                      <Download size={14} />
                    </Button>
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default LogFiles;
