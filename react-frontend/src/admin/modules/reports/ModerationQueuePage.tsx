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
import Shield from 'lucide-react/icons/shield';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import Clock from 'lucide-react/icons/clock';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Settings from 'lucide-react/icons/settings';
import Filter from 'lucide-react/icons/filter';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts/ToastContext';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
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

interface ModerationQueueResponse {
  data?: ModerationItem[];
  items?: ModerationItem[];
  pagination?: { total_pages: number };
}

interface ApiResponseWithMeta {
  data?: unknown;
  meta?: { total_pages?: number };
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

// Labels are provided via i18n t() at render time — see getStatusOptions() / getContentTypeOptions()

const STATUS_OPTION_KEYS = [
  { key: '', i18nKey: 'moderation.all_status' },
  { key: 'pending', i18nKey: 'moderation.pending' },
  { key: 'flagged', i18nKey: 'moderation.flagged' },
  { key: 'approved', i18nKey: 'moderation.approved' },
  { key: 'rejected', i18nKey: 'moderation.rejected' },
];

const CONTENT_TYPE_OPTION_KEYS = [
  { key: '', i18nKey: 'moderation.all_types' },
  { key: 'post', i18nKey: 'moderation.posts' },
  { key: 'listing', i18nKey: 'moderation.listings' },
  { key: 'event', i18nKey: 'moderation.events' },
  { key: 'comment', i18nKey: 'moderation.comments' },
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
  const { t } = useTranslation('admin');
  usePageTitle(t('reports.page_title'));

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
          const obj = d as ModerationQueueResponse;
          setItems(obj.data ?? obj.items ?? []);
          const meta = (res as ApiResponseWithMeta).meta;
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
        toast.success(t('reports.content_approved'));
        await loadQueue();
        await loadStats();
      }
    } catch {
      toast.error(t('reports.failed_to_approve_content'));
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
      toast.warning(t('reports.please_provide_a_rejection_reason'));
      return;
    }

    setActionLoading(rejectItemId);
    try {
      const res = await api.post(`/v2/admin/moderation/${rejectItemId}/review`, {
        decision: 'rejected',
        rejection_reason: rejectReason.trim(),
      });
      if (res.data) {
        toast.success(t('reports.content_rejected'));
        onRejectClose();
        await loadQueue();
        await loadStats();
      }
    } catch {
      toast.error(t('reports.failed_to_reject_content'));
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
        toast.success(t('reports.moderation_settings_updated'));
        await loadSettings();
        onSettingsClose();
      }
    } catch {
      toast.error(t('reports.failed_to_update_settings'));
    } finally {
      setSavingSettings(false);
    }
  };

  // Truncate preview text
  const truncate = (text: string | null, len: number) => {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + t('reports.ellipsis') : text;
  };

  return (
    <div>
      <PageHeader
        title={t('reports.moderation_queue_page_title')}
        description={t('reports.moderation_queue_page_desc')}
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
              {t('reports.settings')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={() => { loadQueue(); loadStats(); }}
              isLoading={loading}
              isDisabled={loading}
              size="sm"
            >
              {t('reports.refresh')}
            </Button>
          </div>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label={t('reports.label_pending_review')}
          value={stats?.pending ?? '\u2014'}
          icon={Clock}
          color="warning"
          loading={!stats}
        />
        <StatCard
          label={t('reports.label_flagged')}
          value={stats?.flagged ?? '\u2014'}
          icon={AlertTriangle}
          color="danger"
          loading={!stats}
        />
        <StatCard
          label={t('reports.label_approved')}
          value={stats?.approved ?? '\u2014'}
          icon={CheckCircle}
          color="success"
          loading={!stats}
        />
        <StatCard
          label={t('reports.label_rejected')}
          value={stats?.rejected ?? '\u2014'}
          icon={XCircle}
          color="default"
          loading={!stats}
        />
        <StatCard
          label={t('reports.auto_flagged')}
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
            <h3 className="font-semibold">{t('reports.by_content_type')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {Object.entries(stats.by_type).map(([type, counts]) => (
                <div key={type} className="rounded-lg border border-divider p-3">
                    <div className="flex items-center gap-2 mb-2">
                      <Chip size="sm" variant="flat" color={TYPE_COLORS[type] ?? 'default'}>
                        {t(`reports.content_type_${type}`, { defaultValue: type })}
                      </Chip>
                    </div>
                    <div className="grid grid-cols-2 gap-1 text-xs">
                      <span className="text-default-400">{t('reports.moderation_pending')}</span>
                      <span className="text-warning font-medium">{counts.pending}</span>
                      <span className="text-default-400">{t('reports.label_flagged')}</span>
                      <span className="text-danger font-medium">{counts.flagged}</span>
                      <span className="text-default-400">{t('reports.label_approved')}</span>
                      <span className="text-success font-medium">{counts.approved}</span>
                      <span className="text-default-400">{t('reports.label_rejected')}</span>
                      <span className="text-default-600 font-medium">{counts.rejected}</span>
                    </div>
                </div>
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
          aria-label={t('reports.label_status_filter')}
        >
          {STATUS_OPTION_KEYS.map((opt) => (
            <SelectItem key={opt.key}>{t(opt.i18nKey)}</SelectItem>
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
          aria-label={t('reports.label_content_type_filter')}
        >
          {CONTENT_TYPE_OPTION_KEYS.map((opt) => (
            <SelectItem key={opt.key}>{t(opt.i18nKey)}</SelectItem>
          ))}
        </Select>
        <Input type="search" name="admin-search" autoComplete="off"
          size="sm"
          placeholder={t('reports.placeholder_search_content')}
          aria-label={t('reports.label_search_moderation_queue')}
          value={search}
          onValueChange={setSearch}
          className="w-48"
          variant="bordered"
          isClearable
        />
      </div>

      {/* Queue Table */}
      <Table aria-label={t('reports.label_moderation_queue')} shadow="sm">
        <TableHeader>
          <TableColumn>{t('reports.col_content')}</TableColumn>
          <TableColumn>{t('reports.col_type')}</TableColumn>
          <TableColumn>{t('reports.col_author')}</TableColumn>
          <TableColumn>{t('reports.col_status')}</TableColumn>
          <TableColumn>{t('reports.col_submitted')}</TableColumn>
          <TableColumn>{t('reports.col_actions')}</TableColumn>
        </TableHeader>
        <TableBody
          emptyContent={t('reports.no_items_in_queue')}
          isLoading={loading}
          loadingContent={<Spinner />}
        >
          {items.map((item) => (
            <TableRow key={item.id}>
              <TableCell>
                <div className="max-w-xs">
                  <p className="text-sm font-medium text-foreground truncate">{item.title || t('reports.untitled')}</p>
                  {item.body && (
                    <p className="text-xs text-default-400 truncate">{truncate(item.body, 80)}</p>
                  )}
                  {item.auto_flagged && item.auto_flag_reason && (
                    <p className="text-xs text-danger mt-1">
                      {t('reports.auto_flagged_reason', { reason: item.auto_flag_reason })}
                    </p>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat" color={TYPE_COLORS[item.content_type] ?? 'default'}>
                  {t(`reports.content_type_${item.content_type}`, { defaultValue: item.content_type })}
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
                  {t(`reports.status_${item.status}`, { defaultValue: item.status })}
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
                      isDisabled={actionLoading !== null}
                      aria-label={t('reports.label_approve')}
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
                      isDisabled={actionLoading !== null}
                      aria-label={t('reports.label_reject')}
                    >
                      <XCircle size={16} />
                    </Button>
                  </div>
                )}
                {item.status === 'approved' && (
                  <span className="text-xs text-success">{t('reports.label_approved')}</span>
                )}
                {item.status === 'rejected' && (
                  <span className="text-xs text-default-400">{t('reports.label_rejected')}</span>
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
          <ModalHeader>{t('reports.reject_content')}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-3">
              {t('reports.reject_content_description')}
            </p>
            <Textarea
              label={t('reports.label_rejection_reason')}
              placeholder={t('reports.placeholder_explain_why_this_content_was_rejected')}
              value={rejectReason}
              onValueChange={setRejectReason}
              variant="bordered"
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onRejectClose}>{t('reports.cancel')}</Button>
            <Button color="danger" onPress={handleReject} isLoading={actionLoading !== null} isDisabled={actionLoading !== null}>
              {t('reports.reject_content')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Settings Modal */}
      <Modal isOpen={isSettingsOpen} onClose={onSettingsClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('reports.moderation_settings')}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-4">
              {t('reports.settings_description')}
            </p>
            {localSettings && (
              <div className="space-y-4">
                <Switch
                  isSelected={localSettings.enabled}
                  onValueChange={(v) => setLocalSettings({ ...localSettings, enabled: v })}
                >
                  <span className="text-sm font-medium">{t('reports.enable_moderation')}</span>
                </Switch>

                <Divider />

                <p className="text-sm font-medium text-foreground">{t('reports.require_moderation_for')}</p>

                <div className="space-y-3 pl-2">
                  <Switch
                    isSelected={localSettings.require_post}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_post: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">{t('reports.feed_posts')}</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_listing}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_listing: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">{t('reports.content_type_listing')}</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_event}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_event: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">{t('reports.content_type_event')}</span>
                  </Switch>
                  <Switch
                    isSelected={localSettings.require_comment}
                    onValueChange={(v) => setLocalSettings({ ...localSettings, require_comment: v })}
                    isDisabled={!localSettings.enabled}
                  >
                    <span className="text-sm">{t('reports.content_type_comment')}</span>
                  </Switch>
                </div>

                <Divider />

                <Switch
                  isSelected={localSettings.auto_filter}
                  onValueChange={(v) => setLocalSettings({ ...localSettings, auto_filter: v })}
                  isDisabled={!localSettings.enabled}
                >
                  <div>
                    <span className="text-sm font-medium">{t('reports.auto_flag_suspicious_content')}</span>
                    <p className="text-xs text-default-400">
                      {t('reports.auto_flag_suspicious_content_desc')}
                    </p>
                  </div>
                </Switch>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onSettingsClose}>{t('reports.cancel')}</Button>
            <Button color="primary" onPress={handleSaveSettings} isLoading={savingSettings} isDisabled={savingSettings}>
              {t('reports.save_settings')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ModerationQueuePage;
