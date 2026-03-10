// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * A7 - Content Moderation Queue
 *
 * - Queue list: pending content with type, author, submitted date, preview
 * - Approve/Reject buttons with rejection reason modal
 * - Stats dashboard: pending count, approved today, rejected today, by content type
 * - Settings panel: toggle moderation per content type (posts, listings, events)
 *
 * API: GET  /api/v2/admin/moderation/queue
 *      POST /api/v2/admin/moderation/{id}/review
 *      GET  /api/v2/admin/moderation/stats
 *      GET  /api/v2/admin/moderation/settings
 *      PUT  /api/v2/admin/moderation/settings
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
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
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Textarea,
  Switch,
  Input,
  Divider,
} from '@heroui/react';
import {
  Shield,
  RefreshCw,
  CheckCircle,
  XCircle,
  Clock,
  AlertTriangle,
  Settings,
  Filter,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts/ToastContext';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ModerationItem {
  id: number;
  content_type: string;
  content_id: number;
  title: string;
  body: string | null;
  author_id: number;
  author_name: string;
  author_avatar: string | null;
  status: 'pending' | 'flagged' | 'approved' | 'rejected';
  auto_flagged: boolean;
  auto_flag_reason: string | null;
  submitted_at: string;
  reviewed_at: string | null;
  reviewed_by: string | null;
  rejection_reason: string | null;
}

interface ModerationStats {
  total: number;
  pending: number;
  flagged: number;
  approved: number;
  rejected: number;
  auto_flagged_total: number;
  by_type: Record<string, {
    pending: number;
    flagged: number;
    approved: number;
    rejected: number;
  }>;
}

interface ModerationSettings {
  enabled: boolean;
  require_post: boolean;
  require_listing: boolean;
  require_event: boolean;
  require_comment: boolean;
  auto_filter: boolean;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const STATUS_OPTIONS = [
  { key: '', label: 'All Status' },
  { key: 'pending', label: 'Pending' },
  { key: 'flagged', label: 'Flagged' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
];

const CONTENT_TYPE_OPTIONS = [
  { key: '', label: 'All Types' },
  { key: 'post', label: 'Posts' },
  { key: 'listing', label: 'Listings' },
  { key: 'event', label: 'Events' },
  { key: 'comment', label: 'Comments' },
];

const STATUS_COLORS: Record<string, 'warning' | 'danger' | 'success' | 'default' | 'secondary'> = {
  pending: 'warning',
  flagged: 'danger',
  approved: 'success',
  rejected: 'default',
};

const TYPE_COLORS: Record<string, 'primary' | 'secondary' | 'success' | 'warning'> = {
  post: 'primary',
  listing: 'secondary',
  event: 'success',
  comment: 'warning',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function ModerationQueuePage() {
  usePageTitle('Content Moderation');

  const toast = useToast();

  const [items, setItems] = useState<ModerationItem[]>([]);
  const [stats, setStats] = useState<ModerationStats | null>(null);
  const [settings, setSettings] = useState<ModerationSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('pending');
  const [typeFilter, setTypeFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [savingSettings, setSavingSettings] = useState(false);

  // Reject modal state
  const { isOpen: isRejectOpen, onOpen: onRejectOpen, onClose: onRejectClose } = useDisclosure();
  const { isOpen: isSettingsOpen, onOpen: onSettingsOpen, onClose: onSettingsClose } = useDisclosure();
  const [rejectItemId, setRejectItemId] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  // Local settings copy for editing
  const [localSettings, setLocalSettings] = useState<ModerationSettings | null>(null);

  // Load queue
  const loadQueue = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        limit: '20',
      });
      if (statusFilter) params.append('status', statusFilter);
      if (typeFilter) params.append('content_type', typeFilter);
      if (search) params.append('search', search);

      const res = await api.get(`/v2/admin/moderation/queue?${params}`);
      if (res.data) {
        const d = res.data;
        if (Array.isArray(d)) {
          setItems(d as ModerationItem[]);
        } else {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const obj = d as any;
          setItems(obj.data ?? obj.items ?? []);
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const meta = (res as any).meta as Record<string, number> | undefined;
          setTotalPages(Math.max(1, meta?.total_pages ?? obj.pagination?.total_pages ?? 1));
        }
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [statusFilter, typeFilter, search, page]);

  // Load stats
  const loadStats = useCallback(async () => {
    try {
      const res = await api.get('/v2/admin/moderation/stats');
      if (res.data) {
        setStats(res.data as ModerationStats);
      }
    } catch {
      // Silently handle
    }
  }, []);

  // Load settings
  const loadSettings = useCallback(async () => {
    try {
      const res = await api.get('/v2/admin/moderation/settings');
      if (res.data) {
        const s = res.data as ModerationSettings;
        setSettings(s);
        setLocalSettings({ ...s });
      }
    } catch {
      // Silently handle
    }
  }, []);

  useEffect(() => {
    loadQueue();
  }, [loadQueue]);

  useEffect(() => {
    loadStats();
    loadSettings();
  }, [loadStats, loadSettings]);

  useEffect(() => {
    setPage(1);
  }, [statusFilter, typeFilter, search]);

  // Approve item
  const handleApprove = async (id: number) => {
    setActionLoading(id);
    try {
      const res = await api.post(`/v2/admin/moderation/${id}/review`, {
        decision: 'approved',
      });
      if (res.data) {
        toast.success('Content approved');
        await loadQueue();
        await loadStats();
      }
    } catch {
      toast.error('Failed to approve content');
    } finally {
      setActionLoading(null);
    }
  };

  // Open reject modal
  const openRejectModal = (id: number) => {
    setRejectItemId(id);
    setRejectReason('');
    onRejectOpen();
  };

  // Confirm rejection
  const handleReject = async () => {
    if (!rejectItemId) return;
    if (!rejectReason.trim()) {
      toast.warning('Please provide a rejection reason');
      return;
    }

    setActionLoading(rejectItemId);
    try {
      const res = await api.post(`/v2/admin/moderation/${rejectItemId}/review`, {
        decision: 'rejected',
        rejection_reason: rejectReason.trim(),
      });
      if (res.data) {
        toast.success('Content rejected');
        onRejectClose();
        await loadQueue();
        await loadStats();
      }
    } catch {
      toast.error('Failed to reject content');
    } finally {
      setActionLoading(null);
    }
  };

  // Save settings
  const handleSaveSettings = async () => {
    if (!localSettings) return;
    setSavingSettings(true);
    try {
      const res = await api.put('/v2/admin/moderation/settings', localSettings);
      if (res.data) {
        toast.success('Moderation settings updated');
        await loadSettings();
        onSettingsClose();
      }
    } catch {
      toast.error('Failed to update settings');
    } finally {
      setSavingSettings(false);
    }
  };

  // Truncate preview text
  const truncate = (text: string | null, len: number) => {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '...' : text;
  };

  return (
    <div>
      <PageHeader
        title="Content Moderation"
        description="Review and moderate user-submitted content before publication"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<Settings size={16} />}
              onPress={() => {
                setLocalSettings(settings ? { ...settings } : null);
                onSettingsOpen();
              }}
              size="sm"
            >
              Settings
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={() => { loadQueue(); loadStats(); }}
              isLoading={loading}
              size="sm"
            >
              Refresh
            </Button>
          </div>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label="Pending Review"
          value={stats?.pending ?? '\u2014'}
          icon={Clock}
          color="warning"
          loading={!stats}
        />
        <StatCard
          label="Flagged"
          value={stats?.flagged ?? '\u2014'}
          icon={AlertTriangle}
          color="danger"
          loading={!stats}
        />
        <StatCard
          label="Approved"
          value={stats?.approved ?? '\u2014'}
          icon={CheckCircle}
          color="success"
          loading={!stats}
        />
        <StatCard
          label="Rejected"
          value={stats?.rejected ?? '\u2014'}
          icon={XCircle}
          color="default"
          loading={!stats}
        />
        <StatCard
          label="Auto-Flagged"
          value={stats?.auto_flagged_total ?? '\u2014'}
          icon={Shield}
          color="secondary"
          loading={!stats}
        />
      </div>

      {/* Content Type Breakdown */}
      {stats?.by_type && Object.keys(stats.by_type).length > 0 && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Filter size={18} className="text-primary" />
            <h3 className="font-semibold">By Content Type</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {Object.entries(stats.by_type).map(([type, counts]) => (
                <Card key={type} shadow="none" className="border border-divider">
                  <CardBody className="p-3">
                    <div className="flex items-center gap-2 mb-2">
                      <Chip size="sm" variant="flat" color={TYPE_COLORS[type] ?? 'default'}>
                        {type}
                      </Chip>
                    </div>
                    <div className="grid grid-cols-2 gap-1 text-xs">
                      <span className="text-default-400">Pending:</span>
                      <span className="text-warning font-medium">{counts.pending}</span>
                      <span className="text-default-400">Flagged:</span>
                      <span className="text-danger font-medium">{counts.flagged}</span>
                      <span className="text-default-400">Approved:</span>
                      <span className="text-success font-medium">{counts.approved}</span>
                      <span className="text-default-400">Rejected:</span>
                      <span className="text-default-600 font-medium">{counts.rejected}</span>
                    </div>
                  </CardBody>
                </Card>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Filters */}
      <div className="flex items-center gap-3 mb-4 flex-wrap">
        <Select
          size="sm"
          selectedKeys={[statusFilter]}
          onSelectionChange={(keys) => {
            const v = Array.from(keys)[0];
            setStatusFilter(v !== undefined ? String(v) : '');
          }}
          className="w-32"
          aria-label="Status filter"
        >
          {STATUS_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>
        <Select
          size="sm"
          selectedKeys={[typeFilter]}
          onSelectionChange={(keys) => {
            const v = Array.from(keys)[0];
            setTypeFilter(v !== undefined ? String(v) : '');
          }}
          className="w-32"
          aria-label="Content type filter"
        >
          {CONTENT_TYPE_OPTIONS.map((opt) => (
            <SelectItem key={opt.key}>{opt.label}</SelectItem>
          ))}
        </Select>
        <Input
          size="sm"
          placeholder="Search content..."
          aria-label="Search moderation queue"
          value={search}
          onValueChange={setSearch}
          className="w-48"
          variant="bordered"
          isClearable
        />
      </div>

      {/* Queue Table */}
      <Table aria-label="Moderation queue" shadow="sm">
        <TableHeader>
          <TableColumn>Content</TableColumn>
          <TableColumn>Type</TableColumn>
          <TableColumn>Author</TableColumn>
          <TableColumn>Status</TableColumn>
          <TableColumn>Submitted</TableColumn>
          <TableColumn>Actions</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent="No items in moderation queue"
          isLoading={loading}
          loadingContent={<Spinner />}
        >
          {items.map((item) => (
            <TableRow key={item.id}>
              <TableCell>
                <div className="max-w-xs">
                  <p className="text-sm font-medium text-foreground truncate">{item.title || 'Untitled'}</p>
                  {item.body && (
                    <p className="text-xs text-default-400 truncate">{truncate(item.body, 80)}</p>
                  )}
                  {item.auto_flagged && item.auto_flag_reason && (
                    <p className="text-xs text-danger mt-1">
                      Auto-flagged: {item.auto_flag_reason}
                    </p>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat" color={TYPE_COLORS[item.content_type] ?? 'default'}>
                  {item.content_type}
                </Chip>
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2">
                  <Avatar size="sm" src={item.author_avatar ?? undefined} name={item.author_name} />
                  <span className="text-sm">{item.author_name}</span>
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat" color={STATUS_COLORS[item.status] ?? 'default'}>
                  {item.status}
                </Chip>
                {item.rejection_reason && (
                  <p className="text-xs text-danger mt-1 max-w-[120px] truncate">
                    {item.rejection_reason}
                  </p>
                )}
              </TableCell>
              <TableCell className="text-sm text-default-500">
                {new Date(item.submitted_at).toLocaleDateString()}
              </TableCell>
              <TableCell>
                {(item.status === 'pending' || item.status === 'flagged') && (
                  <div className="flex items-center gap-1">
                    <Button
                      size="sm"
                      color="success"
                      variant="flat"
                      isIconOnly
                      onPress={() => handleApprove(item.id)}
                      isLoading={actionLoading === item.id}
                      aria-label="Approve"
                    >
                      <CheckCircle size={16} />
                    </Button>
                    <Button
                      size="sm"
                      color="danger"
                      variant="flat"
                      isIconOnly
                      onPress={() => openRejectModal(item.id)}
                      isLoading={actionLoading === item.id}
                      aria-label="Reject"
                    >
                      <XCircle size={16} />
                    </Button>
                  </div>
                )}
                {item.status === 'approved' && (
                  <span className="text-xs text-success">Approved</span>
                )}
                {item.status === 'rejected' && (
                  <span className="text-xs text-default-400">Rejected</span>
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

      {/* Reject Modal */}
      <Modal isOpen={isRejectOpen} onClose={onRejectClose} size="md">
        <ModalContent>
          <ModalHeader>Reject Content</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-3">
              Please provide a reason for rejecting this content. The author will see this message.
            </p>
            <Textarea
              label="Rejection Reason"
              placeholder="Explain why this content was rejected..."
              value={rejectReason}
              onValueChange={setRejectReason}
              variant="bordered"
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onRejectClose}>Cancel</Button>
            <Button color="danger" onPress={handleReject} isLoading={actionLoading !== null}>
              Reject Content
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Settings Modal */}
      <Modal isOpen={isSettingsOpen} onClose={onSettingsClose} size="lg">
        <ModalContent>
          <ModalHeader>Moderation Settings</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-4">
              Configure which content types require moderation before being published.
            </p>
            {localSettings && (
              <div className="space-y-4">
                <Switch
                  isSelected={localSettings.enabled}
                  onValueChange={(v) => setLocalSettings({ ...localSettings, enabled: v })}
                >
                  <span className="text-sm font-medium">Enable Content Moderation</span>
                </Switch>

                <Divider />

                <p className="text-sm font-medium text-foreground">Require Moderation For:</p>

                <div className="space-y-3 pl-2">
                  <Switch
                    isSelected={localSettings.require_post}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_post: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">Feed Posts</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_listing}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_listing: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">Listings</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_event}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_event: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">Events</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_comment}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_comment: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">Comments</span>
                  </Switch>
                </div>

                <Divider />

                <Switch
                  isSelected={localSettings.auto_filter}
                  onValueChange={(v) => setLocalSettings({ ...localSettings, auto_filter: v })}
                  isDisabled={!localSettings.enabled}
                >
                  <div>
                    <span className="text-sm font-medium">Auto-Flag Suspicious Content</span>
                    <p className="text-xs text-default-400">
                      Automatically flag content that matches filter patterns
                    </p>
                  </div>
                </Switch>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onSettingsClose}>Cancel</Button>
            <Button color="primary" onPress={handleSaveSettings} isLoading={savingSettings}>
              Save Settings
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ModerationQueuePage;
