// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Subscribers
 * Full subscriber management: list, add, remove, import CSV, export, sync members.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import {
  Button,
  Avatar,
  Chip,
  Card,
  CardBody,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Pagination,
  Tooltip,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import {
  Users,
  RefreshCw,
  Download,
  Upload,
  UserPlus,
  Copy,
  ExternalLink,
  Trash2,
  Search,
  Mail,
  CheckCircle,
  Clock,
  UserX,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader, StatCard, ConfirmModal } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Subscriber {
  id: number;
  first_name: string | null;
  last_name: string | null;
  email: string;
  status: string;
  source: string | null;
  created_at: string;
  confirmed_at: string | null;
  user_id: number | null;
}

interface SubscriberStats {
  total: number;
  active: number;
  pending: number;
  unsubscribed: number;
}

type StatusFilter = '' | 'active' | 'pending' | 'unsubscribed';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function Subscribers() {
  usePageTitle('Admin - Subscribers');
  const { tenant } = useTenant();
  const toast = useToast();

  // Data state
  const [items, setItems] = useState<Subscriber[]>([]);
  const [stats, setStats] = useState<SubscriberStats>({ total: 0, active: 0, pending: 0, unsubscribed: 0 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchInput, setSearchInput] = useState('');

  // Modal state
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [removeTarget, setRemoveTarget] = useState<Subscriber | null>(null);

  // Form state
  const [addForm, setAddForm] = useState({ email: '', first_name: '', last_name: '' });
  const [addLoading, setAddLoading] = useState(false);
  const [importRows, setImportRows] = useState<Array<{ email: string; first_name?: string; last_name?: string }>>([]);
  const [importFileName, setImportFileName] = useState('');
  const [importLoading, setImportLoading] = useState(false);
  const [syncLoading, setSyncLoading] = useState(false);
  const [exportLoading, setExportLoading] = useState(false);
  const [removeLoading, setRemoveLoading] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // ───────────────────────────────────────────────────────────────────────────
  // Load data
  // ───────────────────────────────────────────────────────────────────────────

  const loadData = useCallback(async (p = page, status = statusFilter, search = searchQuery) => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSubscribers({
        page: p,
        status: status || undefined,
        search: search || undefined,
      });
      if (res.success) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object') {
          const p2 = payload as Record<string, unknown>;
          // Paginated response: { data: [...], meta: {...}, stats: {...} }
          if ('data' in p2 && Array.isArray(p2.data)) {
            setItems(p2.data as Subscriber[]);
          } else if (Array.isArray(payload)) {
            setItems(payload as Subscriber[]);
          }
          if ('meta' in p2 && p2.meta && typeof p2.meta === 'object') {
            const meta = p2.meta as Record<string, unknown>;
            setTotalPages((meta.total_pages as number) || 1);
            setTotal((meta.total as number) || 0);
          }
          if ('stats' in p2 && p2.stats && typeof p2.stats === 'object') {
            const s = p2.stats as SubscriberStats;
            setStats({
              total: s.total || 0,
              active: s.active || 0,
              pending: s.pending || 0,
              unsubscribed: s.unsubscribed || 0,
            });
          }
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, [page, statusFilter, searchQuery]);

  useEffect(() => { loadData(); }, [loadData]);

  // Debounced search
  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
    if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
    searchTimerRef.current = setTimeout(() => {
      setSearchQuery(value);
      setPage(1);
    }, 400);
  }, []);

  // Filter by status
  const handleStatusFilter = useCallback((status: StatusFilter) => {
    setStatusFilter(status);
    setPage(1);
  }, []);

  // ───────────────────────────────────────────────────────────────────────────
  // Add subscriber
  // ───────────────────────────────────────────────────────────────────────────

  const handleAddSubscriber = useCallback(async () => {
    if (!addForm.email) return;
    setAddLoading(true);
    try {
      const res = await adminNewsletters.addSubscriber({
        email: addForm.email,
        first_name: addForm.first_name || undefined,
        last_name: addForm.last_name || undefined,
      });
      if (res.success) {
        toast.success(`Subscriber ${addForm.email} added successfully`);
        setAddModalOpen(false);
        setAddForm({ email: '', first_name: '', last_name: '' });
        loadData(1, statusFilter, searchQuery);
      } else {
        toast.error(res.message || 'Failed to add subscriber');
      }
    } catch {
      toast.error('Failed to add subscriber');
    }
    setAddLoading(false);
  }, [addForm, loadData, statusFilter, searchQuery]);

  // ───────────────────────────────────────────────────────────────────────────
  // Remove subscriber
  // ───────────────────────────────────────────────────────────────────────────

  const handleRemoveSubscriber = useCallback(async () => {
    if (!removeTarget) return;
    setRemoveLoading(true);
    try {
      const res = await adminNewsletters.removeSubscriber(removeTarget.id);
      if (res.success) {
        toast.success(`Subscriber ${removeTarget.email} removed`);
        setRemoveTarget(null);
        loadData();
      } else {
        toast.error('Failed to remove subscriber');
      }
    } catch {
      toast.error('Failed to remove subscriber');
    }
    setRemoveLoading(false);
  }, [removeTarget, loadData]);

  // ───────────────────────────────────────────────────────────────────────────
  // Import CSV
  // ───────────────────────────────────────────────────────────────────────────

  const handleFileSelect = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImportFileName(file.name);

    const reader = new FileReader();
    reader.onload = (ev) => {
      const text = ev.target?.result as string;
      if (!text) return;

      const lines = text.split(/\r?\n/).filter((l) => l.trim());
      if (lines.length < 2) {
        toast.warning('CSV must have a header row and at least one data row');
        return;
      }

      // Parse header
      const header = lines[0].split(',').map((h) => h.trim().toLowerCase().replace(/"/g, ''));
      const emailIdx = header.indexOf('email');
      if (emailIdx === -1) {
        toast.warning('CSV must have an "email" column');
        return;
      }
      const fnIdx = header.indexOf('first_name');
      const lnIdx = header.indexOf('last_name');

      const rows: Array<{ email: string; first_name?: string; last_name?: string }> = [];
      for (let i = 1; i < lines.length; i++) {
        const cols = lines[i].split(',').map((c) => c.trim().replace(/^"|"$/g, ''));
        const email = cols[emailIdx];
        if (!email) continue;
        rows.push({
          email,
          first_name: fnIdx >= 0 ? cols[fnIdx] : undefined,
          last_name: lnIdx >= 0 ? cols[lnIdx] : undefined,
        });
      }
      setImportRows(rows);
    };
    reader.readAsText(file);
  }, []);

  const handleImport = useCallback(async () => {
    if (importRows.length === 0) return;
    setImportLoading(true);
    try {
      const res = await adminNewsletters.importSubscribers(importRows);
      if (res.success) {
        const data = res.data as { imported?: number; skipped?: number };
        toast.success(`${data.imported || 0} imported, ${data.skipped || 0} skipped`);
        setImportModalOpen(false);
        setImportRows([]);
        setImportFileName('');
        loadData(1, statusFilter, searchQuery);
      } else {
        toast.error(res.message || 'Failed to import');
      }
    } catch {
      toast.error('Failed to import subscribers');
    }
    setImportLoading(false);
  }, [importRows, loadData, statusFilter, searchQuery]);

  // ───────────────────────────────────────────────────────────────────────────
  // Export CSV
  // ───────────────────────────────────────────────────────────────────────────

  const handleExport = useCallback(async () => {
    setExportLoading(true);
    try {
      const res = await adminNewsletters.exportSubscribers();
      if (res.success && Array.isArray(res.data)) {
        const rows = res.data as Array<Record<string, string>>;
        if (rows.length === 0) {
          toast.warning('No subscribers to export');
          setExportLoading(false);
          return;
        }
        const headers = ['email', 'first_name', 'last_name', 'status', 'source', 'created_at', 'confirmed_at'];
        const csvLines = [headers.join(',')];
        for (const row of rows) {
          csvLines.push(headers.map((h) => `"${(row[h] || '').replace(/"/g, '""')}"`).join(','));
        }
        const blob = new Blob([csvLines.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `subscribers-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success(`${rows.length} subscribers exported`);
      }
    } catch {
      toast.error('Failed to export subscribers');
    }
    setExportLoading(false);
  }, []);

  // ───────────────────────────────────────────────────────────────────────────
  // Sync members
  // ───────────────────────────────────────────────────────────────────────────

  const handleSync = useCallback(async () => {
    setSyncLoading(true);
    try {
      const res = await adminNewsletters.syncMembers();
      if (res.success) {
        const data = res.data as { synced?: number; already_subscribed?: number };
        toast.success(`${data.synced || 0} new subscribers added. ${data.already_subscribed || 0} already subscribed.`);
        loadData(1, statusFilter, searchQuery);
      } else {
        toast.error(res.message || 'Failed to sync');
      }
    } catch {
      toast.error('Failed to sync members');
    }
    setSyncLoading(false);
  }, [loadData, statusFilter, searchQuery]);

  // ───────────────────────────────────────────────────────────────────────────
  // Helpers
  // ───────────────────────────────────────────────────────────────────────────

  const subscribeUrl = tenant?.slug
    ? `${window.location.origin}/${tenant.slug}/newsletter/subscribe`
    : '';

  const copySubscribeLink = useCallback(() => {
    if (!subscribeUrl) return;
    navigator.clipboard.writeText(subscribeUrl);
    toast.success('Subscribe link copied to clipboard');
  }, [subscribeUrl]);

  const statusColor = (status: string): 'success' | 'warning' | 'danger' | 'default' => {
    switch (status) {
      case 'active': return 'success';
      case 'pending': return 'warning';
      case 'unsubscribed': return 'danger';
      default: return 'default';
    }
  };

  const sourceLabel = (source: string | null) => {
    switch (source) {
      case 'signup': return 'Sign-up';
      case 'import': return 'CSV Import';
      case 'manual': return 'Manual';
      case 'member_sync': return 'Member Sync';
      case 'member': return 'Platform';
      default: return source || '--';
    }
  };

  // ───────────────────────────────────────────────────────────────────────────
  // Render
  // ───────────────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      {/* Header */}
      <PageHeader
        title="Newsletter Subscribers"
        description="Manage your newsletter subscriber list"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={14} />}
              onPress={() => loadData()}
              isLoading={loading}
            >
              Refresh
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<Download size={14} />}
              onPress={handleExport}
              isLoading={exportLoading}
            >
              Export CSV
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<Upload size={14} />}
              onPress={() => setImportModalOpen(true)}
            >
              Import CSV
            </Button>
            <Button
              color="primary"
              size="sm"
              startContent={<UserPlus size={14} />}
              onPress={() => setAddModalOpen(true)}
            >
              Add Subscriber
            </Button>
          </div>
        }
      />

      {/* Sync Platform Members card */}
      <Card shadow="sm">
        <CardBody className="flex flex-row items-center gap-4 p-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Users size={20} />
          </div>
          <div className="min-w-0 flex-1">
            <p className="font-medium text-foreground">Sync Platform Members</p>
            <p className="text-sm text-default-500">
              Add all existing platform members to your subscriber list. Already-subscribed members will be skipped.
            </p>
          </div>
          <Button
            color="primary"
            variant="flat"
            size="sm"
            startContent={<RefreshCw size={14} />}
            onPress={handleSync}
            isLoading={syncLoading}
          >
            Sync Now
          </Button>
        </CardBody>
      </Card>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <Button variant="light" className="text-left h-auto p-0" onPress={() => handleStatusFilter('')}>
          <StatCard label="Total Subscribers" value={stats.total} icon={Mail} color="primary" loading={loading} />
        </Button>
        <Button variant="light" className="text-left h-auto p-0" onPress={() => handleStatusFilter('active')}>
          <StatCard label="Active" value={stats.active} icon={CheckCircle} color="success" loading={loading} />
        </Button>
        <Button variant="light" className="text-left h-auto p-0" onPress={() => handleStatusFilter('pending')}>
          <StatCard label="Pending" value={stats.pending} icon={Clock} color="warning" loading={loading} />
        </Button>
        <Button variant="light" className="text-left h-auto p-0" onPress={() => handleStatusFilter('unsubscribed')}>
          <StatCard label="Unsubscribed" value={stats.unsubscribed} icon={UserX} color="danger" loading={loading} />
        </Button>
      </div>

      {/* Filter pills + search */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2">
          {(['', 'active', 'pending', 'unsubscribed'] as StatusFilter[]).map((s) => (
            <Chip
              key={s || 'all'}
              variant={statusFilter === s ? 'solid' : 'flat'}
              color={s === '' ? 'default' : statusColor(s)}
              className="cursor-pointer"
              onClick={() => handleStatusFilter(s)}
            >
              {s === '' ? 'All' : s.charAt(0).toUpperCase() + s.slice(1)}
            </Chip>
          ))}
        </div>
        <div className="ml-auto w-full max-w-xs">
          <Input
            size="sm"
            placeholder="Search by name or email..."
            aria-label="Search subscribers"
            startContent={<Search size={14} className="text-default-400" />}
            value={searchInput}
            onValueChange={handleSearchChange}
            isClearable
            onClear={() => { setSearchInput(''); setSearchQuery(''); setPage(1); }}
          />
        </div>
      </div>

      {/* Data table */}
      <Table
        aria-label="Newsletter subscribers"
        shadow="sm"
        isStriped
        bottomContent={
          totalPages > 1 ? (
            <div className="flex items-center justify-between px-2 py-2">
              <p className="text-sm text-default-500">
                {total} subscriber{total !== 1 ? 's' : ''} total
              </p>
              <Pagination
                total={totalPages}
                page={page}
                onChange={setPage}
                showControls
                size="sm"
              />
            </div>
          ) : undefined
        }
      >
        <TableHeader>
          <TableColumn>Subscriber</TableColumn>
          <TableColumn>Status</TableColumn>
          <TableColumn>Source</TableColumn>
          <TableColumn>Date</TableColumn>
          <TableColumn className="text-right">Actions</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent={
            <div className="flex flex-col items-center gap-2 py-8 text-default-400">
              <Users size={40} />
              <p className="text-lg font-medium">No subscribers found</p>
              <p className="text-sm">Add subscribers manually, import from CSV, or sync your platform members.</p>
            </div>
          }
          isLoading={loading && items.length === 0}
          loadingContent={<RefreshCw size={24} className="animate-spin text-default-400" />}
        >
          {items.map((sub) => (
            <TableRow key={sub.id}>
              <TableCell>
                <div className="flex items-center gap-3">
                  <Avatar
                    name={`${sub.first_name || ''} ${sub.last_name || ''}`.trim() || sub.email}
                    size="sm"
                    className="shrink-0"
                  />
                  <div className="min-w-0">
                    <p className="truncate font-medium text-foreground">
                      {sub.first_name || sub.last_name
                        ? `${sub.first_name || ''} ${sub.last_name || ''}`.trim()
                        : '--'}
                    </p>
                    <p className="truncate text-xs text-default-400">{sub.email}</p>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat" color={statusColor(sub.status)}>
                  {sub.status}
                </Chip>
              </TableCell>
              <TableCell className="text-sm text-default-500">
                {sourceLabel(sub.source)}
              </TableCell>
              <TableCell className="text-sm text-default-500">
                {sub.created_at ? new Date(sub.created_at).toLocaleDateString() : '--'}
              </TableCell>
              <TableCell className="text-right">
                <Tooltip content="Remove subscriber">
                  <Button
                    isIconOnly
                    variant="light"
                    color="danger"
                    size="sm"
                    aria-label="Remove subscriber"
                    onPress={() => setRemoveTarget(sub)}
                  >
                    <Trash2 size={14} />
                  </Button>
                </Tooltip>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>


      {/* Public subscribe link */}
      {subscribeUrl && (
        <Card shadow="sm">
          <CardBody className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-foreground">Public Subscribe Link</p>
              <p className="truncate text-xs text-default-400">{subscribeUrl}</p>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="flat" size="sm" startContent={<Copy size={14} />} onPress={copySubscribeLink}>
                Copy
              </Button>
              <Button
                variant="flat"
                size="sm"
                startContent={<ExternalLink size={14} />}
                as="a"
                href={subscribeUrl}
                target="_blank"
                rel="noopener noreferrer"
              >
                Preview
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* ─── Add Subscriber Modal ────────────────────────────────────────────── */}
      <Modal isOpen={addModalOpen} onClose={() => setAddModalOpen(false)} size="md">
        <ModalContent>
          <ModalHeader>Add Subscriber</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label="Email"
                type="email"
                placeholder="subscriber@example.com"
                isRequired
                value={addForm.email}
                onValueChange={(v) => setAddForm((f) => ({ ...f, email: v }))}
                autoFocus
              />
              <Input
                label="First Name"
                placeholder="Jane"
                value={addForm.first_name}
                onValueChange={(v) => setAddForm((f) => ({ ...f, first_name: v }))}
              />
              <Input
                label="Last Name"
                placeholder="Doe"
                value={addForm.last_name}
                onValueChange={(v) => setAddForm((f) => ({ ...f, last_name: v }))}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setAddModalOpen(false)} isDisabled={addLoading}>
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleAddSubscriber}
              isLoading={addLoading}
              isDisabled={!addForm.email}
            >
              Add Subscriber
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Import CSV Modal ────────────────────────────────────────────────── */}
      <Modal isOpen={importModalOpen} onClose={() => { setImportModalOpen(false); setImportRows([]); setImportFileName(''); }} size="lg">
        <ModalContent>
          <ModalHeader>Import Subscribers from CSV</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div
                className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-default-300 p-8 text-center transition-colors hover:border-primary hover:bg-primary/5"
                onClick={() => fileInputRef.current?.click()}
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                  e.preventDefault();
                  const file = e.dataTransfer.files[0];
                  if (file && fileInputRef.current) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInputRef.current.files = dt.files;
                    fileInputRef.current.dispatchEvent(new Event('change', { bubbles: true }));
                  }
                }}
              >
                <Upload size={32} className="text-default-400" />
                <p className="font-medium text-foreground">
                  {importFileName || 'Click or drag a CSV file here'}
                </p>
                <p className="text-xs text-default-400">
                  Accepted format: .csv
                </p>
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                className="hidden"
                onChange={handleFileSelect}
              />

              {importRows.length > 0 && (
                <div className="rounded-lg bg-success/10 p-3">
                  <p className="text-sm font-medium text-success">
                    {importRows.length} subscriber{importRows.length !== 1 ? 's' : ''} found in CSV
                  </p>
                </div>
              )}

              <Card shadow="none" className="bg-default-50">
                <CardBody className="p-3">
                  <p className="text-xs font-medium text-default-600">Required CSV format:</p>
                  <code className="mt-1 block text-xs text-default-500">
                    email,first_name,last_name
                  </code>
                  <p className="mt-1 text-xs text-default-400">
                    The <strong>email</strong> column is required. first_name and last_name are optional.
                  </p>
                </CardBody>
              </Card>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setImportModalOpen(false); setImportRows([]); setImportFileName(''); }} isDisabled={importLoading}>
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleImport}
              isLoading={importLoading}
              isDisabled={importRows.length === 0}
            >
              Import {importRows.length > 0 ? `(${importRows.length})` : ''}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Remove Subscriber Confirm ───────────────────────────────────────── */}
      <ConfirmModal
        isOpen={!!removeTarget}
        onClose={() => setRemoveTarget(null)}
        onConfirm={handleRemoveSubscriber}
        title="Remove Subscriber"
        message={`Are you sure you want to remove ${removeTarget?.email || 'this subscriber'}? This action cannot be undone.`}
        confirmLabel="Remove"
        confirmColor="danger"
        isLoading={removeLoading}
      />
    </div>
  );
}

export default Subscribers;
