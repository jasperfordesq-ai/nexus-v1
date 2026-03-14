// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Chip,
  Tabs,
  Tab,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Skeleton,
  Spinner,
} from '@heroui/react';
import {
  Briefcase,
  MapPin,
  Wifi,
  Clock,
  ChevronDown,
  ChevronUp,
  Calendar,
  AlertTriangle,
  History,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

import { useTranslation } from 'react-i18next';
// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ApplicationVacancy {
  id: number;
  title: string;
  type: 'paid' | 'volunteer' | 'timebank';
  commitment: 'full_time' | 'part_time' | 'flexible' | 'one_off';
  status: string;
  location: string | null;
  is_remote: boolean;
  deadline: string | null;
}

interface JobApplication {
  id: number;
  vacancy_id: number;
  user_id: number;
  status: string;
  stage: string;
  message: string | null;
  reviewer_notes: string | null;
  created_at: string;
  updated_at: string;
  vacancy: ApplicationVacancy;
}

interface ApplicationsResponse {
  items: JobApplication[];
  cursor: string | null;
  has_more: boolean;
}

interface HistoryEntry {
  id: number;
  application_id: number;
  from_status: string | null;
  to_status: string;
  changed_at: string;
  notes: string | null;
  changed_by_name: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const STATUS_COLORS: Record<string, 'default' | 'primary' | 'warning' | 'secondary' | 'success' | 'danger'> = {
  applied: 'default',
  pending: 'default',
  screening: 'primary',
  reviewed: 'primary',
  interview: 'warning',
  offer: 'secondary',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'default',
};

const TYPE_CHIP_COLORS: Record<string, 'primary' | 'success' | 'secondary'> = {
  paid: 'primary',
  volunteer: 'success',
  timebank: 'secondary',
};

const ACTIVE_STATUSES = new Set(['applied', 'pending', 'screening', 'reviewed', 'interview', 'offer']);
const ITEMS_PER_PAGE = 20;

type FilterTab = 'all' | 'active' | 'accepted' | 'rejected';

// ---------------------------------------------------------------------------
// Animation variants
// ---------------------------------------------------------------------------

const containerVariants = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.06 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.3, ease: 'easeOut' } },
};

// ---------------------------------------------------------------------------
// ApplicationCard sub-component
// ---------------------------------------------------------------------------

interface ApplicationCardProps {
  application: JobApplication;
  onWithdraw: (app: JobApplication) => void;
  tenantPath: (path: string) => string;
}

function ApplicationCard({ application, onWithdraw, tenantPath }: ApplicationCardProps) {
  const { t } = useTranslation('jobs');
  const [messageExpanded, setMessageExpanded] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const { vacancy } = application;

  const appliedDate = new Date(application.created_at);
  const updatedDate = new Date(application.updated_at);
  const datesAreDifferent = Math.abs(updatedDate.getTime() - appliedDate.getTime()) > 60_000;

  let deadlineLabel: React.ReactNode = null;
  if (vacancy.deadline) {
    const deadlineDate = new Date(vacancy.deadline);
    const msPerDay = 1000 * 60 * 60 * 24;
    const daysUntilDeadline = Math.floor((deadlineDate.getTime() - Date.now()) / msPerDay);
    if (daysUntilDeadline < 0) {
      deadlineLabel = (
        <span className='flex items-center gap-1 text-xs text-danger'>
          <AlertTriangle size={12} aria-hidden="true" />
          {t('deadline_passed')}
        </span>
      );
    } else if (daysUntilDeadline < 7) {
      deadlineLabel = (
        <span className='flex items-center gap-1 text-xs text-warning font-medium'>
          <AlertTriangle size={12} aria-hidden="true" />
          {daysUntilDeadline === 0
            ? t('my_applications.deadline_today', 'Deadline today')
            : t('my_applications.days_left', '{{count}}d left', { count: daysUntilDeadline })}
        </span>
      );
    } else {
      deadlineLabel = (
        <span className='flex items-center gap-1 text-xs text-theme-muted'>
          <Calendar size={12} />
          {deadlineDate.toLocaleDateString()}
        </span>
      );
    }
  }

  const isActive = ACTIVE_STATUSES.has(application.status);

  return (
    <motion.div variants={itemVariants}>
      <GlassCard className='p-5'>
        {/* Header row */}
        <div className='flex flex-wrap items-start justify-between gap-3 mb-3'>
          <div className='flex-1 min-w-0'>
            <Link
              to={tenantPath(`/jobs/${vacancy.id}`)}
              className='text-base font-semibold text-theme-primary hover:text-primary transition-colors line-clamp-2'>
              {vacancy.title}
            </Link>
          </div>
          <div className='flex flex-wrap items-center gap-2 shrink-0'>
            <Chip
              color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'}
              size='sm'
              variant='flat'>
              {t(`type.${vacancy.type}`)}
            </Chip>
            <Chip
              color={STATUS_COLORS[application.status] ?? 'default'}
              size='sm'
              variant='flat'>
              {t(`application_status.${application.status}`, { defaultValue: application.status })}
            </Chip>
          </div>
        </div>

        {/* Meta row */}
        <div className='flex flex-wrap items-center gap-3 mb-3 text-xs text-theme-muted'>
          <span className='flex items-center gap-1'>
            <Clock size={12} />
            {t(`commitment.${vacancy.commitment}`)}
          </span>
          {vacancy.is_remote ? (
            <span className='flex items-center gap-1'>
              <Wifi size={12} aria-hidden="true" />
              {t('remote')}
            </span>
          ) : vacancy.location ? (
            <span className='flex items-center gap-1'>
              <MapPin size={12} />
              {vacancy.location}
            </span>
          ) : null}
          {deadlineLabel}
        </div>

        {/* Dates row */}
        <div className='flex flex-wrap gap-3 mb-3 text-xs text-theme-muted'>
          <span>{t('my_applications.applied_date', 'Applied: {{date}}', { date: appliedDate.toLocaleDateString() })}</span>
          {datesAreDifferent && (
            <span>{t('my_applications.updated_date', 'Updated: {{date}}', { date: updatedDate.toLocaleDateString() })}</span>
          )}
        </div>

        {/* Cover message */}
        {application.message && (
          <div className='mb-3'>
            <Button
              variant="light"
              size="sm"
              onPress={() => setMessageExpanded((v) => !v)}
              className="flex items-center gap-1 text-xs text-theme-muted hover:text-theme-primary transition-colors mb-1 h-auto p-0 min-w-0"
              startContent={messageExpanded ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
            >
              {messageExpanded
                ? t('my_applications.hide_cover_message', 'Hide cover message')
                : t('my_applications.show_cover_message', 'Show cover message')}
            </Button>
            <AnimatePresence initial={false}>
              {messageExpanded && (
                <motion.div
                  key='msg'
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  transition={{ duration: 0.2 }}
                  className='overflow-hidden'>
                  <p className='text-sm text-theme-secondary bg-white/5 rounded-lg p-3 whitespace-pre-wrap'>
                    {application.message}
                  </p>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        )}

        {/* Reviewer notes */}
        {application.reviewer_notes && (
          <blockquote className='border-l-2 border-primary/40 pl-3 mb-3 text-sm text-theme-muted italic'>
            {application.reviewer_notes}
          </blockquote>
        )}

        {/* Actions */}
        <div className='flex justify-end gap-2'>
          <Button
            size='sm'
            variant='flat'
            startContent={<History size={13} />}
            onPress={() => {
              setHistoryOpen((v) => !v);
              if (!historyOpen && history.length === 0) {
                setHistoryLoading(true);
                api.get<HistoryEntry[]>(`/v2/jobs/applications/${application.id}/history`)
                  .then((res) => {
                    if (res.success && Array.isArray(res.data)) setHistory(res.data);
                  })
                  .catch((err: unknown) => {
                    logError('MyApplicationsPage: failed to load application history', err);
                  })
                  .finally(() => setHistoryLoading(false));
              }
            }}>
            {t('history.title')}
          </Button>
          {isActive && (
            <Button
              size='sm'
              color='danger'
              variant='flat'
              onPress={() => onWithdraw(application)}>
              {t('my_applications.withdraw', 'Withdraw')}
            </Button>
          )}
        </div>

        {/* Inline history panel */}
        <AnimatePresence initial={false}>
          {historyOpen && (
            <motion.div
              key='history'
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className='overflow-hidden'>
              <div className='mt-3 pt-3 border-t border-divider'>
                <p className='text-xs font-semibold text-default-500 uppercase tracking-wide mb-2'>{t('history.status_history', 'Status History')}</p>
                {historyLoading && <div className='flex justify-center py-3'><Spinner size='sm' /></div>}
                {!historyLoading && history.length === 0 && (
                  <p className='text-xs text-default-400'>{t('history.empty')}</p>
                )}
                {!historyLoading && history.length > 0 && (
                  <ol className='space-y-2'>
                    {history.map((entry) => (
                      <li key={entry.id} className='flex gap-2 text-xs'>
                        <span className='shrink-0 w-1.5 h-1.5 rounded-full bg-primary/60 mt-1.5' />
                        <div className='min-w-0'>
                          <span className='text-default-600'>
                            {entry.from_status
                              ? <>{t(`application_status.${entry.from_status}`, { defaultValue: entry.from_status })} → {t(`application_status.${entry.to_status}`, { defaultValue: entry.to_status })}</>
                              : <>{t('history.initial')}</>}
                          </span>
                          {entry.notes && <span className='text-default-400 italic ml-1'>— {entry.notes}</span>}
                          <div className='text-default-400'>
                            {new Date(entry.changed_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
                            {entry.changed_by_name && <span className='ml-1'>{t('history.by', 'by {{name}}', { name: entry.changed_by_name })}</span>}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ol>
                )}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </GlassCard>
    </motion.div>
  );
}

// ---------------------------------------------------------------------------
// Skeleton placeholder
// ---------------------------------------------------------------------------

function ApplicationSkeleton() {
  return (
    <GlassCard className='p-5 space-y-3'>
      <div className='flex justify-between'>
        <Skeleton className='h-5 w-2/3 rounded-lg' />
        <Skeleton className='h-5 w-20 rounded-full' />
      </div>
      <div className='flex gap-3'>
        <Skeleton className='h-4 w-24 rounded-lg' />
        <Skeleton className='h-4 w-20 rounded-lg' />
      </div>
      <Skeleton className='h-4 w-32 rounded-lg' />
    </GlassCard>
  );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export function MyApplicationsPage() {
  const { t } = useTranslation('jobs');
  usePageTitle(t('my_applications.title'));
  useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [applications, setApplications] = useState<JobApplication[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const cursorRef = useRef<string | null>(null);

  const [activeTab, setActiveTab] = useState<FilterTab>('all');
  const [withdrawTarget, setWithdrawTarget] = useState<JobApplication | null>(null);
  const [isWithdrawing, setIsWithdrawing] = useState(false);
  const { isOpen: isWithdrawOpen, onOpen: openWithdraw, onClose: closeWithdraw } = useDisclosure();

  const tabToStatus = (tab: FilterTab): string => {
    switch (tab) {
      case 'active':
        return 'applied,pending,screening,reviewed,interview,offer';
      case 'accepted':
        return 'accepted';
      case 'rejected':
        return 'rejected';
      default:
        return '';
    }
  };

  const loadApplications = useCallback(
    async (append = false) => {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      try {
        const params = new URLSearchParams();
        params.set('per_page', String(ITEMS_PER_PAGE));
        const status = tabToStatus(activeTab);
        if (status) params.set('status', status);
        if (append && cursorRef.current) params.set('cursor', cursorRef.current);

        const res = await api.get<ApplicationsResponse>(`/v2/jobs/my-applications?${params.toString()}`);

        if (res.success && res.data && 'items' in res.data) {
          const data = res.data as ApplicationsResponse;
          cursorRef.current = data.cursor ?? null;
          setHasMore(data.has_more);
          setApplications((prev) => (append ? [...prev, ...data.items] : data.items));
        } else {
          const msg = (res as { error?: string }).error ?? t('my_applications.load_error', 'Failed to load applications');
          if (!append) setError(msg);
          toast.error(msg);
        }
      } catch (err) {
        logError('MyApplicationsPage', err);
        const msg = t('something_wrong', 'Something went wrong. Please try again.');
        if (!append) setError(msg);
        toast.error(msg);
      } finally {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    },
    [activeTab, toast, t],
  );

  useEffect(() => {
    cursorRef.current = null;
    setHasMore(false);
    setApplications([]);
    loadApplications();
  }, [activeTab]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleWithdrawClick = (app: JobApplication) => {
    setWithdrawTarget(app);
    openWithdraw();
  };

  const confirmWithdraw = useCallback(async () => {
    if (!withdrawTarget) return;
    setIsWithdrawing(true);
    try {
      const res = await api.put(`/v2/jobs/applications/${withdrawTarget.id}`, { status: 'withdrawn' });
      if (res.success) {
        toast.success(t('application_withdrawn'));
        closeWithdraw();
        // Refresh the list
        cursorRef.current = null;
        setHasMore(false);
        setApplications([]);
        loadApplications();
      } else {
        toast.error((res as { error?: string }).error ?? t('my_applications.withdraw_error', 'Failed to withdraw application.'));
      }
    } catch (err) {
      logError('MyApplicationsPage.confirmWithdraw', err);
      toast.error(t('something_wrong'));
    } finally {
      setIsWithdrawing(false);
    }
  }, [withdrawTarget, toast, closeWithdraw, loadApplications]);

  return (
    <div className='max-w-3xl mx-auto px-4 py-8'>
      {/* Page header */}
      <div className='mb-6'>
        <h1 className='text-2xl font-bold text-theme-primary flex items-center gap-2'>
          <Briefcase size={24} aria-hidden="true" />
          {t('my_applications.title')}
        </h1>
        <p className='text-sm text-theme-muted mt-1'>
          {t('my_applications.subtitle', 'Track the status of your job and volunteer applications.')}
        </p>
      </div>

      {/* Filter tabs */}
      <div className='mb-6'>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as FilterTab)}
          variant='underlined'
          aria-label={t('my_applications.filter_aria', 'Application filter')}
          classNames={{ tab: 'text-theme-muted data-[selected=true]:text-theme-primary' }}>
          <Tab key='all' title={t('my_applications.tab_all', 'All')} />
          <Tab key='active' title={t('my_applications.tab_active', 'Active')} />
          <Tab key='accepted' title={t('my_applications.tab_accepted', 'Accepted')} />
          <Tab key='rejected' title={t('my_applications.tab_rejected', 'Rejected')} />
        </Tabs>
      </div>

      {/* Loading skeleton */}
      {isLoading && (
        <div className='space-y-4'>
          <ApplicationSkeleton />
          <ApplicationSkeleton />
          <ApplicationSkeleton />
        </div>
      )}

      {/* Error state */}
      {!isLoading && error && (
        <GlassCard className='p-8 text-center'>
          <p className='text-danger mb-4'>{error}</p>
          <Button color='primary' variant='flat' onPress={() => loadApplications()}>
            {t('try_again', 'Retry')}
          </Button>
        </GlassCard>
      )}

      {/* Empty state */}
      {!isLoading && !error && applications.length === 0 && (
        <GlassCard className='p-12 text-center'>
          <Briefcase size={48} className='mx-auto mb-4 text-theme-muted/50' />
          <h3 className='text-lg font-semibold text-theme-primary mb-2'>
            {t('my_applications.empty_title', 'No applications yet')}
          </h3>
          <p className='text-sm text-theme-muted mb-6'>
            {activeTab === 'all'
              ? t('my_applications.empty_all', "You haven't applied to any positions yet.")
              : t('my_applications.empty_filtered', 'No {{filter}} applications found.', { filter: t(`my_applications.tab_${activeTab}`) })}
          </p>
          <Button as={Link} to={tenantPath('/jobs')} color='primary' variant='flat'>
            {t('my_applications.browse_jobs', 'Browse Jobs')}
          </Button>
        </GlassCard>
      )}

      {/* Applications list */}
      {!isLoading && !error && applications.length > 0 && (
        <>
          <motion.div
            variants={containerVariants}
            initial='hidden'
            animate='visible'
            className='space-y-4'>
            {applications.map((app) => (
              <ApplicationCard
                key={app.id}
                application={app}
                onWithdraw={handleWithdrawClick}
                tenantPath={tenantPath}
              />
            ))}
          </motion.div>

          {/* Load more */}
          {hasMore && (
            <div className='mt-6 flex justify-center'>
              <Button
                variant='flat'
                color='primary'
                isLoading={isLoadingMore}
                onPress={() => loadApplications(true)}>
                {t('load_more')}
              </Button>
            </div>
          )}
        </>
      )}

      {/* Withdraw confirmation modal */}
      <Modal isOpen={isWithdrawOpen} onClose={closeWithdraw} size='sm'>
        <ModalContent>
          <ModalHeader>{t('my_applications.withdraw_title', 'Withdraw Application')}</ModalHeader>
          <ModalBody>
            <p className='text-sm text-theme-secondary'>
              {t('my_applications.withdraw_confirm', 'Are you sure you want to withdraw your application for {{title}}? This action cannot be undone.', {
                title: withdrawTarget?.vacancy.title ?? '',
              })}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant='flat' onPress={closeWithdraw} isDisabled={isWithdrawing}>
              {t('apply.cancel')}
            </Button>
            <Button color='danger' onPress={confirmWithdraw} isLoading={isWithdrawing}>
              {t('my_applications.withdraw', 'Withdraw')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MyApplicationsPage;
