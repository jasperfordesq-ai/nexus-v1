// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback, useRef } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
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
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import { AlertTriangle, AlertCircle, Info, Search, X, FileText } from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';
import { adminSuper } from '../../api/adminApi';
import type { SuperAuditEntry } from '../../api/types';

interface AuditEntry {
  id: number;
  level: 'critical' | 'warning' | 'info';
  action: string;
  category: string;
  actor_name?: string;
  actor_email?: string;
  tenant_a_name?: string;
  tenant_b_name?: string;
  ip_address?: string;
  user_agent?: string;
  data?: Record<string, unknown>;
  created_at: string;
}

interface Stats {
  total: number;
  critical: number;
  warnings: number;
  info: number;
}

function classifyLevel(actionType: string): 'critical' | 'warning' | 'info' {
  if (actionType.includes('lockdown') || actionType.includes('emergency') || actionType.includes('terminate')) return 'critical';
  if (actionType.includes('suspend') || actionType.includes('revoke') || actionType.includes('remove')) return 'warning';
  return 'info';
}

function classifyCategory(targetType: string): string {
  if (targetType.includes('partnership')) return 'partnership';
  if (targetType.includes('whitelist')) return 'whitelist';
  if (targetType.includes('feature')) return 'feature';
  if (targetType.includes('system') || targetType.includes('federation')) return 'system';
  return 'system';
}

function mapAuditEntry(e: SuperAuditEntry): AuditEntry {
  return {
    id: e.id,
    level: classifyLevel(e.action_type),
    action: e.action_type,
    category: classifyCategory(e.target_type),
    actor_name: e.actor_name,
    data: e.new_value as Record<string, unknown> | undefined,
    created_at: e.created_at,
  };
}

export default function FederationAuditLog() {
  usePageTitle('Federation Audit Log');
  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [stats, setStats] = useState<Stats>({ total: 0, critical: 0, warnings: 0, info: 0 });
  const [filters, setFilters] = useState({
    level: 'all',
    category: 'all',
    from: '',
    to: '',
    search: '',
  });
  const [selectedEntry, setSelectedEntry] = useState<AuditEntry | null>(null);
  const [loading, setLoading] = useState(true);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  const loadData = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getAudit({
      target_type: 'federation',
      search: filters.search || undefined,
      date_from: filters.from || undefined,
      date_to: filters.to || undefined,
      limit: 100,
    });
    if (res.success && res.data) {
      const raw = Array.isArray(res.data) ? res.data : [];
      let mapped = raw.map(mapAuditEntry);

      // Apply client-side level/category filters
      if (filters.level !== 'all') {
        mapped = mapped.filter(e => e.level === filters.level);
      }
      if (filters.category !== 'all') {
        mapped = mapped.filter(e => e.category === filters.category);
      }

      setEntries(mapped);
      setStats({
        total: mapped.length,
        critical: mapped.filter(e => e.level === 'critical').length,
        warnings: mapped.filter(e => e.level === 'warning').length,
        info: mapped.filter(e => e.level === 'info').length,
      });
    }
    setLoading(false);
  }, [filters]);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(loadData, 300);
    return () => clearTimeout(debounceRef.current);
  }, [loadData]);

  const getLevelIcon = (level: string) => {
    switch (level) {
      case 'critical':
        return <AlertTriangle className="w-4 h-4 text-danger" />;
      case 'warning':
        return <AlertCircle className="w-4 h-4 text-warning" />;
      case 'info':
        return <Info className="w-4 h-4 text-primary" />;
      default:
        return <Info className="w-4 h-4 text-default-500" />;
    }
  };

  const getLevelColor = (level: string) => {
    switch (level) {
      case 'critical': return 'danger';
      case 'warning': return 'warning';
      case 'info': return 'primary';
      default: return 'default';
    }
  };

  const getCategoryColor = (category: string) => {
    switch (category) {
      case 'system': return 'primary';
      case 'partnership': return 'success';
      case 'whitelist': return 'secondary';
      case 'feature': return 'warning';
      case 'security': return 'danger';
      default: return 'default';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Federation Audit Log"
        description="Track all federation system changes and events"
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-secondary-100 dark:bg-secondary-900">
                <FileText className="w-5 h-5 text-secondary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Total Events</p>
                <p className="text-2xl font-bold text-secondary">{stats.total}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-danger-100 dark:bg-danger-900">
                <AlertTriangle className="w-5 h-5 text-danger" />
              </div>
              <div>
                <p className="text-xs text-default-500">Critical</p>
                <p className="text-2xl font-bold text-danger">{stats.critical}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-warning-100 dark:bg-warning-900">
                <AlertCircle className="w-5 h-5 text-warning" />
              </div>
              <div>
                <p className="text-xs text-default-500">Warnings</p>
                <p className="text-2xl font-bold text-warning">{stats.warnings}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                <Info className="w-5 h-5 text-primary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Info</p>
                <p className="text-2xl font-bold text-primary">{stats.info}</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Filter Bar */}
      <Card>
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <Select
              label="Level"
              selectedKeys={[filters.level]}
              onChange={(e) => setFilters(prev => ({ ...prev, level: e.target.value }))}
              variant="bordered"
            >
              <SelectItem key="all">All Levels</SelectItem>
              <SelectItem key="critical">Critical</SelectItem>
              <SelectItem key="warning">Warning</SelectItem>
              <SelectItem key="info">Info</SelectItem>
            </Select>

            <Select
              label="Category"
              selectedKeys={[filters.category]}
              onChange={(e) => setFilters(prev => ({ ...prev, category: e.target.value }))}
              variant="bordered"
            >
              <SelectItem key="all">All Categories</SelectItem>
              <SelectItem key="system">System</SelectItem>
              <SelectItem key="partnership">Partnership</SelectItem>
              <SelectItem key="whitelist">Whitelist</SelectItem>
              <SelectItem key="feature">Feature</SelectItem>
              <SelectItem key="security">Security</SelectItem>
            </Select>

            <Input
              label="From Date"
              type="date"
              value={filters.from}
              onValueChange={(value) => setFilters(prev => ({ ...prev, from: value }))}
              variant="bordered"
            />

            <Input
              label="To Date"
              type="date"
              value={filters.to}
              onValueChange={(value) => setFilters(prev => ({ ...prev, to: value }))}
              variant="bordered"
            />

            <Input
              label="Search"
              placeholder="Search actions, actors..."
              value={filters.search}
              onValueChange={(value) => setFilters(prev => ({ ...prev, search: value }))}
              variant="bordered"
              startContent={<Search className="w-4 h-4 text-default-400" />}
              endContent={
                filters.search && (
                  <Button isIconOnly variant="light" size="sm" onPress={() => setFilters(prev => ({ ...prev, search: '' }))} aria-label="Clear search" className="min-w-0 w-6 h-6">
                    <X className="w-4 h-4 text-default-400" />
                  </Button>
                )
              }
            />
          </div>
        </CardBody>
      </Card>

      {/* Audit Table */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Audit Entries ({entries.length})</h3>
        </CardHeader>
        <CardBody>
          <Table aria-label="Federation audit log" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Level</TableColumn>
              <TableColumn>Action</TableColumn>
              <TableColumn>Category</TableColumn>
              <TableColumn>Actor</TableColumn>
              <TableColumn>Tenants</TableColumn>
              <TableColumn>IP Address</TableColumn>
              <TableColumn>Time</TableColumn>
              <TableColumn>Actions</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No audit entries found">
              {entries.map(entry => (
                <TableRow key={entry.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {getLevelIcon(entry.level)}
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="font-medium">{entry.action}</span>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={getCategoryColor(entry.category)} variant="flat">
                      {entry.category}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    {entry.actor_name ? (
                      <div>
                        <p className="font-medium text-sm">{entry.actor_name}</p>
                        <p className="text-xs text-default-500">{entry.actor_email}</p>
                      </div>
                    ) : (
                      <span className="text-sm text-default-500">System</span>
                    )}
                  </TableCell>
                  <TableCell>
                    {entry.tenant_a_name && (
                      <div className="text-sm">
                        <p>{entry.tenant_a_name}</p>
                        {entry.tenant_b_name && (
                          <p className="text-default-500">↔ {entry.tenant_b_name}</p>
                        )}
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    <span className="text-sm font-mono text-default-600">
                      {entry.ip_address || '-'}
                    </span>
                  </TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(entry.created_at).toLocaleString()}
                  </TableCell>
                  <TableCell>
                    <Button size="sm" variant="flat" onPress={() => setSelectedEntry(entry)}>
                      Details
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Details Modal */}
      {selectedEntry && (
        <Modal
          isOpen={true}
          onClose={() => setSelectedEntry(null)}
          size="2xl"
          scrollBehavior="inside"
        >
          <ModalContent>
            <ModalHeader className="flex flex-col gap-1">
              <div className="flex items-center gap-2">
                {getLevelIcon(selectedEntry.level)}
                <span>Audit Entry Details</span>
              </div>
            </ModalHeader>
            <ModalBody>
              <div className="space-y-4">
                <div>
                  <p className="text-sm font-medium text-default-600">Action</p>
                  <p className="text-lg font-semibold">{selectedEntry.action}</p>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm font-medium text-default-600">Category</p>
                    <Chip
                      size="sm"
                      color={getCategoryColor(selectedEntry.category)}
                      variant="flat"
                      className="mt-1"
                    >
                      {selectedEntry.category}
                    </Chip>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-default-600">Level</p>
                    <Chip
                      size="sm"
                      color={getLevelColor(selectedEntry.level)}
                      variant="flat"
                      className="mt-1"
                    >
                      {selectedEntry.level}
                    </Chip>
                  </div>
                </div>

                <div>
                  <p className="text-sm font-medium text-default-600">Time</p>
                  <p>{new Date(selectedEntry.created_at).toLocaleString()}</p>
                </div>

                {selectedEntry.actor_name && (
                  <div>
                    <p className="text-sm font-medium text-default-600">Actor</p>
                    <p>{selectedEntry.actor_name}</p>
                    <p className="text-sm text-default-500">{selectedEntry.actor_email}</p>
                  </div>
                )}

                {selectedEntry.ip_address && (
                  <div>
                    <p className="text-sm font-medium text-default-600">IP Address</p>
                    <p className="font-mono">{selectedEntry.ip_address}</p>
                  </div>
                )}

                {selectedEntry.user_agent && (
                  <div>
                    <p className="text-sm font-medium text-default-600">User Agent</p>
                    <p className="text-sm text-default-600">{selectedEntry.user_agent}</p>
                  </div>
                )}

                {selectedEntry.data && Object.keys(selectedEntry.data).length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-default-600 mb-2">Additional Data</p>
                    <pre className="p-3 bg-default-100 dark:bg-default-800 rounded-lg text-xs overflow-auto">
                      {JSON.stringify(selectedEntry.data, null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            </ModalBody>
            <ModalFooter>
              <Button color="primary" onPress={() => setSelectedEntry(null)}>
                Close
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}
