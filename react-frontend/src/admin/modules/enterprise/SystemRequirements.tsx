// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Requirements
 * Checks PHP version, extensions, writable directories, services, and INI settings.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Input,
  Divider,
} from '@heroui/react';
import {
  RefreshCw,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Search,
  Server,
  FolderOpen,
  Settings,
  Puzzle,
  Code,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SystemRequirements as SystemRequirementsType } from '../../api/types';

export function SystemRequirements() {
  usePageTitle("Enterprise");
  const toast = useToast();

  const [data, setData] = useState<SystemRequirementsType | null>(null);
  const [loading, setLoading] = useState(true);
  const [extSearch, setExtSearch] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getSystemRequirements();
      if (res.success && res.data) {
        setData(res.data as unknown as SystemRequirementsType);
      }
    } catch {
      toast.error("Failed to load system requirements");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    loadData();
  }, [loadData]);

  // Compute overall status
  const computeStatus = (): 'pass' | 'warning' | 'fail' => {
    if (!data) return 'fail';
    const phpFail = !data.php.meets_minimum;
    const missingRequired = data.extensions.some((ext) => ext.required && !ext.loaded);
    const dirFail = data.writable_directories.some((d) => !d.writable);
    const serviceFail = data.services.some((s) => s.status === 'fail');
    if (phpFail || missingRequired || serviceFail) return 'fail';
    if (dirFail) return 'warning';
    const missingOptional = data.extensions.some((ext) => !ext.required && !ext.loaded);
    if (missingOptional) return 'warning';
    return 'pass';
  };

  const status = computeStatus();
  const statusConfig = {
    pass: { color: 'success' as const, label: "All checks passed", icon: CheckCircle, bg: 'bg-success/10' },
    warning: { color: 'warning' as const, label: "Some warnings detected", icon: AlertTriangle, bg: 'bg-warning/10' },
    fail: { color: 'danger' as const, label: "Critical issues found", icon: XCircle, bg: 'bg-danger/10' },
  };

  const filteredExtensions = data?.extensions.filter((ext) =>
    !extSearch || ext.name.toLowerCase().includes(extSearch.toLowerCase())
  ) ?? [];

  const loadedCount = data?.extensions.filter((ext) => ext.loaded).length ?? 0;
  const requiredCount = data?.extensions.filter((ext) => ext.required).length ?? 0;

  const iniEntries = data?.ini_settings ? Object.entries(data.ini_settings) : [];

  const iniLabels: Record<string, string> = {
    memory_limit: "Memory Limit",
    max_execution_time: "Max Execution Time",
    max_input_time: "Max Input Time",
    max_input_vars: "Max Input Variables",
    upload_max_filesize: "Upload Max File Size",
    post_max_size: "Post Max Size",
  };

  return (
    <div>
      <PageHeader
        title={"System Requirements"}
        description={"System Requirements."}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : data ? (
        <div className="space-y-6">
          {/* Overall Status Banner */}
          <Card shadow="sm">
            <CardBody className={`flex flex-row items-center gap-4 p-6 ${statusConfig[status].bg}`}>
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-white/80 dark:bg-default-100">
                {(() => {
                  const StatusIcon = statusConfig[status].icon;
                  return <StatusIcon size={28} className={`text-${statusConfig[status].color}`} />;
                })()}
              </div>
              <div>
                <h3 className="text-lg font-bold text-foreground">{"Overall Status"}</h3>
                <Chip size="sm" variant="flat" color={statusConfig[status].color} className="mt-1">
                  {statusConfig[status].label}
                </Chip>
              </div>
            </CardBody>
          </Card>

          {/* PHP Version */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
              <Code size={18} className="text-primary" />
              <h3 className="text-base font-semibold">{"Php Version"}</h3>
            </CardHeader>
            <CardBody className="px-6 pb-5">
              <div className="flex items-center gap-4">
                <span className="text-3xl font-bold font-mono text-foreground">{data.php.version}</span>
                <Chip
                  size="sm"
                  variant="flat"
                  color={data.php.meets_minimum ? 'success' : 'danger'}
                  startContent={data.php.meets_minimum ? <CheckCircle size={12} /> : <XCircle size={12} />}
                >
                  {data.php.meets_minimum ? "Meets minimum (8.2+)" : "Below minimum (requires 8.2+)"}
                </Chip>
              </div>
            </CardBody>
          </Card>

          {/* PHP Extensions */}
          <Card shadow="sm">
            <CardHeader className="flex items-center justify-between px-6 pt-5 pb-0">
              <div className="flex items-center gap-2">
                <Puzzle size={18} className="text-secondary" />
                <h3 className="text-base font-semibold">{"Php Extensions"}</h3>
                <Chip size="sm" variant="flat" color="default">
                  {`${loadedCount} of ${data.extensions.length} loaded (${requiredCount} required)`}
                </Chip>
              </div>
              <Input
                placeholder={"Search extensions..."}
                startContent={<Search size={14} className="text-default-400" />}
                value={extSearch}
                onValueChange={setExtSearch}
                variant="bordered"
                size="sm"
                className="w-48"
              />
            </CardHeader>
            <CardBody className="px-6 pb-5">
              <div className="flex flex-wrap gap-2">
                {filteredExtensions.map((ext) => {
                  let color: 'success' | 'danger' | 'warning' = 'success';
                  if (!ext.loaded && ext.required) color = 'danger';
                  else if (!ext.loaded && !ext.required) color = 'warning';

                  return (
                    <Chip
                      key={ext.name}
                      size="sm"
                      variant="flat"
                      color={color}
                      startContent={ext.loaded ? <CheckCircle size={12} /> : <XCircle size={12} />}
                      className={ext.required ? 'font-bold' : ''}
                    >
                      {ext.name}
                    </Chip>
                  );
                })}
              </div>
            </CardBody>
          </Card>

          {/* Writable Directories */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
              <FolderOpen size={18} className="text-warning" />
              <h3 className="text-base font-semibold">{"Writable Directories"}</h3>
            </CardHeader>
            <CardBody className="px-6 pb-5">
              <div className="space-y-2">
                {data.writable_directories.map((dir) => (
                  <div key={dir.path} className="flex items-center gap-3">
                    {dir.writable ? (
                      <CheckCircle size={16} className="text-success shrink-0" />
                    ) : (
                      <XCircle size={16} className="text-danger shrink-0" />
                    )}
                    <span className="font-mono text-sm text-foreground">{dir.path}</span>
                    <Chip size="sm" variant="flat" color={dir.writable ? 'success' : 'danger'}>
                      {dir.writable ? "Writable" : "Not writable"}
                    </Chip>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          {/* Services */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
              <Server size={18} className="text-success" />
              <h3 className="text-base font-semibold">{"Services"}</h3>
            </CardHeader>
            <CardBody className="px-6 pb-5">
              <div className="flex gap-4">
                {data.services.map((svc) => (
                  <div key={svc.name} className="flex items-center gap-2">
                    {svc.status === 'ok' ? (
                      <CheckCircle size={16} className="text-success" />
                    ) : (
                      <XCircle size={16} className="text-danger" />
                    )}
                    <span className="text-sm font-medium text-foreground">{svc.name}</span>
                    <Chip size="sm" variant="flat" color={svc.status === 'ok' ? 'success' : 'danger'}>
                      {svc.status === 'ok' ? "OK" : "FAIL"}
                    </Chip>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          {/* INI Settings */}
          {iniEntries.length > 0 && (
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
                <Settings size={18} className="text-default-500" />
                <h3 className="text-base font-semibold">{"Ini Settings"}</h3>
              </CardHeader>
              <CardBody className="px-6 pb-5">
                <div className="divide-y divide-divider">
                  {iniEntries.map(([key, value]) => (
                    <div key={key} className="flex items-center justify-between py-2">
                      <span className="text-sm text-default-600">{iniLabels[key] ?? key}</span>
                      <span className="font-mono text-sm text-foreground">{value}</span>
                    </div>
                  ))}
                </div>
                <Divider className="my-0" />
              </CardBody>
            </Card>
          )}
        </div>
      ) : null}
    </div>
  );
}

export default SystemRequirements;
