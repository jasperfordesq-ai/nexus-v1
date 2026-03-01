// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * A4 - Inactive Member Detection
 *
 * - List of inactive members with last activity date
 * - Filter by days inactive (30/60/90/180)
 * - Flag type filter (inactive/dormant/at_risk)
 * - "Run Detection" button
 * - "Mark Notified" bulk action
 * - Stats summary (inactive count, dormant count, inactivity rate)
 *
 * API: GET  /api/v2/admin/members/inactive
 *      POST /api/v2/admin/members/inactive/detect
 *      POST /api/v2/admin/members/inactive/notify
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  Spinner,
  Button,
  Select,
  SelectItem,
  Pagination,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Avatar,
  Chip,
  Checkbox,
} from '@heroui/react';
import {
  UserX,
  Download,
  RefreshCw,
  AlertTriangle,
  Clock,
  Bell,
  Scan,
  Activity,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts/ToastContext';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface InactiveMember {
  id: number;
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  last_activity: string | null;
  last_login: string | null;
  days_inactive: number;
  flag_type: 'inactive' | 'dormant' | 'at_risk';
  notified_at: string | null;
  flagged_at: string;
}

interface InactivityStats {
  total_active_members: number;
  total_flagged: number;
  inactive_count: number;
  dormant_count: number;
  at_risk_count: number;
  notified_count: number;
  inactivity_rate: number;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const DAYS_OPTIONS = [
  { key: '30', label: '30 days' },
  { key: '60', label: '60 days' },
  { key: '90', label: '90 days' },
  { key: '180', label: '180 days' },
  { key: '365', label: '365 days' },
];

const FLAG_TYPE_OPTIONS = [
  { key: '', label: 'All Types' },
  { key: 'inactive', label: 'Inactive' },
  { key: 'dormant', label: 'Dormant' },
  { key: 'at_risk', label: 'At Risk' },
];

const FLAG_COLORS: Record<string, 'warning' | 'danger' | 'secondary'> = {
  inactive: 'warning',
  dormant: 'danger',
  at_risk: 'secondary',
};

// ---------------------------------------------------------------------------
// CSV Export helper
// ---------------------------------------------------------------------------

async function exportCsv(days: string) {
  const token = localStorage.getItem('nexus_access_token');
  const tenantId = localStorage.getItem('nexus_tenant_id');
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = new URLSearchParams({ format: 'csv', days });

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/inactive_members/export?${params}`, { headers });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'inactive-members.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function InactiveMembersPage() {
  usePageTitle('Inactive Members');

  const toast = useToast();

  const [members, setMembers] = useState<InactiveMember[]>([]);
  const [stats, setStats] = useState<InactivityStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [days, setDays] = useState('90');
  const [flagType, setFlagType] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [detecting, setDetecting] = useState(false);
  const [notifying, setNotifying] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        days,
        page: String(page),
        limit: '20',
      });
      if (flagType) params.append('flag_type', flagType);

      const res = await api.get(`/v2/admin/members/inactive?${params}`);
      if (res.data) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const d = res.data as any;
        setMembers(d.members ?? []);
        setStats(d.stats ?? null);

        // Pagination from meta or data
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const meta = (res as any).meta as Record<string, number> | undefined;
        const tp = meta?.total_pages ?? d.pagination?.total_pages ?? 1;
        setTotalPages(Math.max(1, tp));
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [days, flagType, page]);

  useEffect(() => {
    setPage(1);
    setSelectedIds(new Set());
  }, [days, flagType]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Run detection
  const handleDetect = async () => {
    setDetecting(true);
    try {
      const res = await api.post('/v2/admin/members/inactive/detect', {
        threshold_days: parseInt(days, 10),
      });
      if (res.data) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const result = res.data as any;
        toast.success(`Detection complete: ${result.flagged ?? 0} members flagged`);
        await loadData();
      }
    } catch {
      toast.error('Detection failed');
    } finally {
      setDetecting(false);
    }
  };

  // Mark notified
  const handleNotify = async () => {
    if (selectedIds.size === 0) {
      toast.warning('Select members to mark as notified');
      return;
    }

    setNotifying(true);
    try {
      const userIds = Array.from(selectedIds);
      const res = await api.post('/v2/admin/members/inactive/notify', { user_ids: userIds });
      if (res.data) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const result = res.data as any;
        toast.success(result.message || `${result.updated} members marked as notified`);
        setSelectedIds(new Set());
        await loadData();
      }
    } catch {
      toast.error('Failed to mark members as notified');
    } finally {
      setNotifying(false);
    }
  };

  // Selection toggles
  const toggleSelect = (userId: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(userId)) {
        next.delete(userId);
      } else {
        next.add(userId);
      }
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === members.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(members.map((m) => m.user_id)));
    }
  };

  return (
    <div>
      <PageHeader
        title="Inactive Members"
        description="Detect and manage members who have become inactive"
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Select
              size="sm"
              selectedKeys={[days]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) setDays(String(v));
              }}
              className="w-32"
              aria-label="Days threshold"
            >
              {DAYS_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <Select
              size="sm"
              selectedKeys={[flagType]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                setFlagType(v !== undefined ? String(v) : '');
              }}
              className="w-32"
              aria-label="Flag type"
            >
              {FLAG_TYPE_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <Button
              color="primary"
              variant="flat"
              startContent={<Scan size={16} />}
              onPress={handleDetect}
              isLoading={detecting}
              size="sm"
            >
              Run Detection
            </Button>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={() => exportCsv(days)}
              size="sm"
            >
              Export CSV
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              Refresh
            </Button>
          </div>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Flagged"
          value={stats?.total_flagged ?? '\u2014'}
          icon={AlertTriangle}
          color="danger"
          loading={!stats}
        />
        <StatCard
          label="Inactive"
          value={stats?.inactive_count ?? '\u2014'}
          icon={UserX}
          color="warning"
          loading={!stats}
        />
        <StatCard
          label="Dormant"
          value={stats?.dormant_count ?? '\u2014'}
          icon={Clock}
          color="danger"
          loading={!stats}
        />
        <StatCard
          label="Inactivity Rate"
          value={stats ? `${(stats.inactivity_rate * 100).toFixed(1)}%` : '\u2014'}
          icon={Activity}
          color="secondary"
          loading={!stats}
        />
      </div>

      {/* Secondary stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">Active Members</p>
            <p className="text-2xl font-bold text-foreground">
              {stats?.total_active_members?.toLocaleString() ?? '\u2014'}
            </p>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">At Risk</p>
            <p className="text-2xl font-bold text-warning">
              {stats?.at_risk_count?.toLocaleString() ?? '\u2014'}
            </p>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">Already Notified</p>
            <p className="text-2xl font-bold text-foreground">
              {stats?.notified_count?.toLocaleString() ?? '\u2014'}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Bulk action bar */}
      {selectedIds.size > 0 && (
        <Card shadow="sm" className="mb-4">
          <CardBody className="flex flex-row items-center gap-4 p-3">
            <span className="text-sm font-medium text-foreground">
              {selectedIds.size} member{selectedIds.size !== 1 ? 's' : ''} selected
            </span>
            <Button
              color="primary"
              size="sm"
              startContent={<Bell size={14} />}
              onPress={handleNotify}
              isLoading={notifying}
            >
              Mark as Notified
            </Button>
            <Button
              variant="flat"
              size="sm"
              onPress={() => setSelectedIds(new Set())}
            >
              Clear Selection
            </Button>
          </CardBody>
        </Card>
      )}

      {/* Members Table */}
      <Table aria-label="Inactive members" shadow="sm">
        <TableHeader>
          <TableColumn width={40}>
            <Checkbox
              isSelected={selectedIds.size === members.length && members.length > 0}
              isIndeterminate={selectedIds.size > 0 && selectedIds.size < members.length}
              onValueChange={toggleSelectAll}
              aria-label="Select all"
            />
          </TableColumn>
          <TableColumn>Member</TableColumn>
          <TableColumn>Flag Type</TableColumn>
          <TableColumn>Days Inactive</TableColumn>
          <TableColumn>Last Activity</TableColumn>
          <TableColumn>Last Login</TableColumn>
          <TableColumn>Notified</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent="No inactive members found. Run detection to scan."
          isLoading={loading}
          loadingContent={<Spinner />}
        >
          {members.map((m) => (
            <TableRow key={m.id}>
              <TableCell>
                <Checkbox
                  isSelected={selectedIds.has(m.user_id)}
                  onValueChange={() => toggleSelect(m.user_id)}
                  aria-label={`Select ${m.name}`}
                />
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2">
                  <Avatar size="sm" src={m.avatar_url ?? undefined} name={m.name} />
                  <div>
                    <p className="text-sm font-medium">{m.name}</p>
                    <p className="text-xs text-default-400">{m.email}</p>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={FLAG_COLORS[m.flag_type] ?? 'default'}
                >
                  {m.flag_type.replace('_', ' ')}
                </Chip>
              </TableCell>
              <TableCell>
                <span className={`text-sm font-medium ${m.days_inactive > 180 ? 'text-danger' : m.days_inactive > 90 ? 'text-warning' : 'text-default-600'}`}>
                  {m.days_inactive} days
                </span>
              </TableCell>
              <TableCell className="text-sm text-default-600">
                {m.last_activity ? new Date(m.last_activity).toLocaleDateString() : 'Never'}
              </TableCell>
              <TableCell className="text-sm text-default-600">
                {m.last_login ? new Date(m.last_login).toLocaleDateString() : 'Never'}
              </TableCell>
              <TableCell>
                {m.notified_at ? (
                  <Chip size="sm" variant="flat" color="success">
                    {new Date(m.notified_at).toLocaleDateString()}
                  </Chip>
                ) : (
                  <Chip size="sm" variant="flat" color="default">Not yet</Chip>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {totalPages > 1 && (
        <div className="flex justify-center mt-4">
          <Pagination total={totalPages} page={page} onChange={setPage} />
        </div>
      )}
    </div>
  );
}

export default InactiveMembersPage;
