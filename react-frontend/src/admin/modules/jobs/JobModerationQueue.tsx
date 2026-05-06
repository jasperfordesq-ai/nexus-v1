// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Job Moderation Queue
 *
 * Displays jobs pending moderation review with approve/reject/flag actions,
 * moderation statistics, and spam detection score indicators.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Card,
  CardBody,
  CardHeader,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Spinner,
  Avatar,
  Tooltip,
  Divider,
} from '@heroui/react';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import XCircle from 'lucide-react/icons/circle-x';
import Flag from 'lucide-react/icons/flag';
import Clock from 'lucide-react/icons/clock';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import ShieldCheck from 'lucide-react/icons/shield-check';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import BarChart3 from 'lucide-react/icons/chart-column';
import Inbox from 'lucide-react/icons/inbox';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { PageHeader, StatCard, EmptyState } from '../../components';

interface PendingJob {
  id: number;
  title: string;
  description: string;
  type?: string;
  category?: string;
  location?: string;
  status: string;
  moderation_status: string;
  moderation_notes?: string;
  spam_score?: number;
  spam_flags?: string[];
  created_at: string;
  poster_name?: string;
  poster_avatar?: string;
  user_id: number;
}

interface ModerationStats {
  pending: number;
  approved_today: number;
  rejected_today: number;
  flagged: number;
  total_reviewed: number;
}

interface SpamStats {
  total_analyzed: number;
  blocked: number;
  flagged: number;
  avg_score: number;
  top_flags: Record<string, number>;
}

type ModerationAction = 'approve' | 'reject' | 'flag';

// Flag labels are resolved via t() in the component; this is a fallback map
const FLAG_LABELS: Record<string, string> = {
  duplicate_content: 'Duplicate Content',
  suspicious_links: 'Suspicious Links',
  excessive_posting_rate: 'Excessive Posting',
  suspicious_patterns: 'Suspicious Patterns',
  new_account: 'New Account',
};

function getSpamScoreColor(score: number): 'success' | 'warning' | 'danger' | 'default' {
  if (score > 70) return 'danger';
  if (score > 40) return 'warning';
  if (score > 0) return 'success';
  return 'default';
}

export function JobModerationQueue() {
  const { t } = useTranslation('jobs');
  usePageTitle(t('moderation.title', 'Moderation Queue'));
  const toast = useToast();

  // State
  const [pendingJobs, setPendingJobs] = useState<PendingJob[]>([]);
  const [stats, setStats] = useState<ModerationStats | null>(null);
  const [spamStats, setSpamStats] = useState<SpamStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [statsLoading, setStatsLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Modal state
  const [actionModal, setActionModal] = useState<{
    isOpen: boolean;
    action: ModerationAction;
    job: PendingJob | null;
  }>({ isOpen: false, action: 'approve', job: null });
  const [actionReason, setActionReason] = useState('');

  // Load pending jobs
  const loadPendingJobs = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: PendingJob[]; total: number }>(
        '/v2/admin/jobs/moderation-queue'
      );
      if (res.success && res.data) {
        setPendingJobs(res.data.items ?? []);
      }
    } catch {
      toast.error(t('moderation.approve_error', 'Failed to load moderation queue'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);


  // Load stats
  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const [modRes, spamRes] = await Promise.all([
        api.get<ModerationStats>('/v2/admin/jobs/moderation-stats'),
        api.get<SpamStats>('/v2/admin/jobs/spam-stats'),
      ]);
      if (modRes.success && modRes.data) setStats(modRes.data);
      if (spamRes.success && spamRes.data) setSpamStats(spamRes.data);
    } catch {
      // Stats are non-critical, just log
    } finally {
      setStatsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadPendingJobs();
    loadStats();
  }, [loadPendingJobs, loadStats]);

  // Open action modal
  const openActionModal = (action: ModerationAction, job: PendingJob) => {
    setActionModal({ isOpen: true, action, job });
    setActionReason('');
  };

  // Execute moderation action
  const executeAction = async () => {
    const { action, job } = actionModal;
    if (!job) return;

    // Reject and flag require a reason
    if ((action === 'reject' || action === 'flag') && !actionReason.trim()) {
      toast.error(t('moderation.reason_required', 'A reason is required'));
      return;
    }

    setActionLoading(true);
    try {
      const endpoint = `/v2/admin/jobs/${job.id}/${action}`;
      const body: Record<string, string> = {};

      if (action === 'approve') {
        if (actionReason.trim()) body.notes = actionReason.trim();
      } else {
        body.reason = actionReason.trim();
      }

      const res = await api.post(endpoint, body);

      if (res.success) {
        const successKey = `moderation.${action}_success`;
        toast.success(t(successKey, `Job ${action}d successfully`));
        setPendingJobs((prev) => prev.filter((j) => j.id !== job.id));
        loadStats();
      } else {
        const errorKey = `moderation.${action}_error`;
        toast.error(t(errorKey, `Failed to ${action} job`));
      }
    } catch {
      toast.error(t('something_wrong', 'Something went wrong. Please try again.'));
    } finally {
      setActionLoading(false);
      setActionModal({ isOpen: false, action: 'approve', job: null });
    }
  };

  const actionConfig: Record<ModerationAction, { color: 'success' | 'danger' | 'warning'; icon: typeof CheckCircle2; label: string }> = {
    approve: { color: 'success', icon: CheckCircle2, label: t('moderation.approve', 'Approve') },
    reject: { color: 'danger', icon: XCircle, label: t('moderation.reject', 'Reject') },
    flag: { color: 'warning', icon: Flag, label: t('moderation.flag', 'Flag for Review') },
  };

  return (
    <div>
      <PageHeader
        title={t('moderation.title', 'Moderation Queue')}
        description={t('moderation.description', 'Review and approve job postings before they go live')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => { loadPendingJobs(); loadStats(); }}
          >
            {t('moderation.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label={t('moderation.stats.pending_count', 'Pending')}
          value={stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('moderation.stats.approved_today', 'Approved Today')}
          value={stats?.approved_today ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label={t('moderation.stats.rejected_today', 'Rejected Today')}
          value={stats?.rejected_today ?? 0}
          icon={XCircle}
          color="danger"
          loading={statsLoading}
        />
        <StatCard
          label={t('moderation.stats.flagged_count', 'Flagged')}
          value={stats?.flagged ?? 0}
          icon={Flag}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('moderation.stats.total_reviewed', 'Total Reviewed')}
          value={stats?.total_reviewed ?? 0}
          icon={BarChart3}
          color="primary"
          loading={statsLoading}
        />
      </div>

      {/* Spam Stats Summary */}
      {spamStats && spamStats.total_analyzed > 0 && (
        <Card className="mb-6" shadow="sm">
          <CardHeader className="flex items-center gap-2 pb-2">
            <ShieldAlert size={18} className="text-warning" />
            <h3 className="text-sm font-semibold text-foreground">
              {t('spam.title', 'Spam Detection')}
            </h3>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="flex flex-wrap gap-6 text-sm">
              <div>
                <span className="text-default-500">{t('spam.total_analyzed', 'Total Analyzed')}:</span>{' '}
                <span className="font-medium">{spamStats.total_analyzed}</span>
              </div>
              <div>
                <span className="text-default-500">{t('spam.blocked_count', 'Blocked')}:</span>{' '}
                <span className="font-medium text-danger">{spamStats.blocked}</span>
              </div>
              <div>
                <span className="text-default-500">{t('spam.flagged_count', 'Flagged')}:</span>{' '}
                <span className="font-medium text-warning">{spamStats.flagged}</span>
              </div>
              <div>
                <span className="text-default-500">{t('spam.avg_score', 'Avg. Score')}:</span>{' '}
                <span className="font-medium">{spamStats.avg_score}</span>
              </div>
            </div>
            {Object.keys(spamStats.top_flags).length > 0 && (
              <div className="mt-3 flex flex-wrap gap-2">
                {Object.entries(spamStats.top_flags).map(([flag, count]) => (
                  <Chip key={flag} size="sm" variant="flat" color="default">
                    {FLAG_LABELS[flag] ?? flag}: {count}
                  </Chip>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Pending Jobs List */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner label={t('moderation.loading', 'Loading moderation queue...')} />
        </div>
      ) : pendingJobs.length === 0 ? (
        <EmptyState
          icon={Inbox}
          title={t('moderation.no_pending', 'No pending jobs')}
          description={t('moderation.no_pending_description', 'All job postings have been reviewed. New submissions will appear here.')}
        />
      ) : (
        <div className="flex flex-col gap-4">
          {pendingJobs.map((job) => (
            <Card key={job.id} shadow="sm">
              <CardBody className="p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  {/* Job Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-3 mb-2">
                      <Avatar
                        src={resolveAvatarUrl(job.poster_avatar) || undefined}
                        name={job.poster_name ?? 'Unknown'}
                        size="sm"
                        className="shrink-0"
                      />
                      <div className="min-w-0">
                        <h3 className="text-base font-semibold text-foreground truncate">
                          {job.title}
                        </h3>
                        <p className="text-xs text-default-500">
                          {job.poster_name
                            ? t('moderation.posted_by', 'Posted by {{name}}', { name: job.poster_name })
                            : `User #${job.user_id}`}
                          {' '}&middot;{' '}
                          {new Date(job.created_at).toLocaleDateString(undefined, {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </p>
                      </div>
                    </div>

                    {/* Description preview */}
                    <p className="text-sm text-default-600 line-clamp-3 mb-2">
                      {job.description || t('moderation.no_description', 'No description provided')}
                    </p>

                    {/* Meta chips */}
                    <div className="flex flex-wrap gap-2">
                      {job.type && (
                        <Chip size="sm" variant="flat" color="primary" className="capitalize">
                          {job.type}
                        </Chip>
                      )}
                      {job.category && (
                        <Chip size="sm" variant="flat" color="default">
                          {job.category}
                        </Chip>
                      )}
                      {job.location && (
                        <Chip size="sm" variant="flat" color="default">
                          {job.location}
                        </Chip>
                      )}
                      {job.spam_score != null && job.spam_score > 0 && (
                        <Tooltip content={
                          job.spam_flags && job.spam_flags.length > 0
                            ? `Flags: ${job.spam_flags.map((f) => FLAG_LABELS[f] ?? f).join(', ')}`
                            : t('moderation.no_flags', 'No specific flags')
                        }>
                          <Chip
                            size="sm"
                            variant="flat"
                            color={getSpamScoreColor(job.spam_score)}
                            startContent={<AlertTriangle size={12} />}
                          >
                            {t('moderation.spam_score', 'Spam Score: {{score}}', { score: job.spam_score })}
                          </Chip>
                        </Tooltip>
                      )}
                    </div>
                  </div>

                  <Divider className="sm:hidden" />

                  {/* Actions */}
                  <div className="flex gap-2 shrink-0 sm:flex-col sm:items-end">
                    <Button
                      size="sm"
                      color="success"
                      variant="flat"
                      startContent={<CheckCircle2 size={14} />}
                      onPress={() => openActionModal('approve', job)}
                    >
                      {t('moderation.approve', 'Approve')}
                    </Button>
                    <Button
                      size="sm"
                      color="danger"
                      variant="flat"
                      startContent={<XCircle size={14} />}
                      onPress={() => openActionModal('reject', job)}
                    >
                      {t('moderation.reject', 'Reject')}
                    </Button>
                    <Button
                      size="sm"
                      color="warning"
                      variant="flat"
                      startContent={<Flag size={14} />}
                      onPress={() => openActionModal('flag', job)}
                    >
                      {t('moderation.flag', 'Flag')}
                    </Button>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Action Modal */}
      <Modal
        isOpen={actionModal.isOpen}
        onClose={() => setActionModal({ isOpen: false, action: 'approve', job: null })}
        size="md"
      >
        <ModalContent>
          {actionModal.job && (() => {
            const config = actionConfig[actionModal.action];
            const ActionIcon = config.icon;
            const requiresReason = actionModal.action === 'reject' || actionModal.action === 'flag';

            return (
              <>
                <ModalHeader className="flex items-center gap-2">
                  <ActionIcon size={20} className={`text-${config.color}`} />
                  {config.label}: {actionModal.job.title}
                </ModalHeader>
                <ModalBody>
                  <p className="text-sm text-default-600 mb-3">
                    {actionModal.action === 'approve' && t('moderation.confirm_approve', 'Approve this job posting? It will be published immediately.')}
                    {actionModal.action === 'reject' && t('moderation.confirm_reject', 'Are you sure you want to reject this job posting?')}
                    {actionModal.action === 'flag' && t('moderation.confirm_flag', 'Flag this job posting for further review?')}
                  </p>
                  <Textarea
                    label={requiresReason
                      ? t('moderation.reason_label', 'Reason')
                      : t('moderation.notes_label', 'Notes (optional)')
                    }
                    placeholder={requiresReason
                      ? t('moderation.reason_placeholder', 'Provide a reason for this action...')
                      : t('moderation.notes_placeholder', 'Add optional notes...')
                    }
                    value={actionReason}
                    onValueChange={setActionReason}
                    isRequired={requiresReason}
                    minRows={3}
                    maxRows={6}
                  />
                </ModalBody>
                <ModalFooter>
                  <Button
                    variant="flat"
                    onPress={() => setActionModal({ isOpen: false, action: 'approve', job: null })}
                  >
                    {t('moderation.cancel', 'Cancel')}
                  </Button>
                  <Button
                    color={config.color}
                    isLoading={actionLoading}
                    isDisabled={requiresReason && !actionReason.trim()}
                    onPress={executeAction}
                    startContent={!actionLoading && <ActionIcon size={16} />}
                  >
                    {config.label}
                  </Button>
                </ModalFooter>
              </>
            );
          })()}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default JobModerationQueue;
