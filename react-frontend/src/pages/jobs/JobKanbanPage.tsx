// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Job Kanban Pipeline Page
 *
 * Drag-and-drop board for employers to manage applications to a job posting.
 * Columns: Applied | Screening | Interview | Offer | Accepted | Rejected
 * Uses HTML5 native drag events — no external DnD library.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Input,
  Textarea,
} from '@heroui/react';
import {
  ArrowLeft,
  Briefcase,
  Download,
  FileText,
  Calendar,
  Percent,
  DollarSign,
  Star,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api, API_BASE } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { resolveAvatarUrl } from '@/lib/helpers';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface KanbanApplication {
  id: number;
  vacancy_id: number;
  user_id: number;
  status: string;
  stage: string;
  message: string | null;
  created_at: string;
  match_percentage?: number | null;
  cv_path?: string | null;
  applicant: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface KanbanVacancy {
  id: number;
  title: string;
  user_id: number;
  status: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const COLUMNS: Array<{
  id: string;
  status: string;
  color: 'default' | 'primary' | 'secondary' | 'warning' | 'success' | 'danger';
}> = [
  { id: 'Applied',    status: 'pending',    color: 'default' },
  { id: 'Screening',  status: 'screening',  color: 'primary' },
  { id: 'Interview',  status: 'interview',  color: 'secondary' },
  { id: 'Offer',      status: 'offer',      color: 'warning' },
  { id: 'Accepted',   status: 'accepted',   color: 'success' },
  { id: 'Rejected',   status: 'rejected',   color: 'danger' },
];

const STATUS_TO_COLUMN: Record<string, string> = {
  applied:   'Applied',
  pending:   'Applied',
  screening: 'Screening',
  reviewed:  'Screening',
  interview: 'Interview',
  offer:     'Offer',
  accepted:  'Accepted',
  rejected:  'Rejected',
};

// ---------------------------------------------------------------------------
// ApplicationKanbanCard
// ---------------------------------------------------------------------------

interface AppCardProps {
  application: KanbanApplication;
  onDragStart: (appId: number) => void;
  onDownloadCv?: (appId: number, applicantName: string) => void;
  onScheduleInterview?: (app: KanbanApplication) => void;
  onSendOffer?: (app: KanbanApplication) => void;
  onScoreApplicant?: (app: KanbanApplication) => void;
  onSelect?: (id: number, checked: boolean) => void;
  isSelected?: boolean;
}

function AppKanbanCard({ application, onDragStart, onDownloadCv, onScheduleInterview, onSendOffer, onScoreApplicant, onSelect, isSelected }: AppCardProps) {
  const { t } = useTranslation('jobs');
  const appliedDate = new Date(application.created_at);
  const stage = application.stage ?? application.status;
  const [isDragging, setIsDragging] = useState(false);

  return (
    <motion.div
      layout
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.9 }}
      draggable
      role="listitem"
      aria-label={`${application.applicant.name} — ${t(`application_status.${stage}`, { defaultValue: stage })}`}
      aria-grabbed={isDragging}
      onDragStart={() => { setIsDragging(true); onDragStart(application.id); }}
      onDragEnd={() => setIsDragging(false)}
      className="cursor-grab active:cursor-grabbing select-none"
    >
      <GlassCard className="p-3 space-y-2 hover:shadow-md transition-shadow">
        {/* Selection checkbox */}
        {onSelect && (
          <div className="flex items-center gap-2 mb-2">
            <input
              type="checkbox"
              checked={isSelected ?? false}
              onChange={(e) => onSelect(application.id, e.target.checked)}
              className="w-4 h-4 accent-primary cursor-pointer"
              aria-label={t('kanban.select_applicant', 'Select {{name}}', { name: application.applicant.name })}
              onClick={(e) => e.stopPropagation()}
            />
          </div>
        )}

        {/* Applicant header */}
        <div className="flex items-center gap-2">
          <Avatar
            name={application.applicant.name}
            src={resolveAvatarUrl(application.applicant.avatar_url)}
            size="sm"
            isBordered
          />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-theme-primary truncate">
              {application.applicant.name}
            </p>
          </div>
        </div>

        {/* Meta */}
        <div className="flex flex-wrap items-center gap-2 text-xs text-theme-subtle">
          <span className="flex items-center gap-1">
            <Calendar size={11} aria-hidden="true" />
            {appliedDate.toLocaleDateString()}
          </span>
          {application.match_percentage != null && (
            <span className="flex items-center gap-1 text-primary font-medium">
              <Percent size={11} aria-hidden="true" />
              {application.match_percentage}% {t('match.title')}
            </span>
          )}
        </div>

        {/* Cover message preview */}
        {application.message && (
          <p className="text-xs text-theme-muted line-clamp-2 italic">
            &ldquo;{application.message}&rdquo;
          </p>
        )}

        {/* CV download */}
        {application.cv_path && onDownloadCv && (
          <Button
            size="sm"
            variant="flat"
            className="w-full bg-theme-elevated text-theme-muted text-xs"
            startContent={<Download size={12} aria-hidden="true" />}
            onPress={() => onDownloadCv(application.id, application.applicant.name)}
          >
            {t('kanban.download_cv', 'Download CV')}
          </Button>
        )}

        {/* Schedule Interview button — shown for screening/reviewed stage */}
        {(stage === 'screening' || stage === 'reviewed') && onScheduleInterview && (
          <Button
            size="sm"
            variant="flat"
            color="secondary"
            className="w-full text-xs"
            startContent={<Calendar size={14} aria-hidden="true" />}
            onPress={() => onScheduleInterview(application)}
          >
            {t('interview.schedule', 'Schedule Interview')}
          </Button>
        )}

        {/* Send Offer button — shown for interview stage */}
        {stage === 'interview' && onSendOffer && (
          <Button
            size="sm"
            variant="flat"
            color="warning"
            className="w-full text-xs"
            startContent={<DollarSign size={14} aria-hidden="true" />}
            onPress={() => onSendOffer(application)}
          >
            {t('offer.send', 'Send Offer')}
          </Button>
        )}

        {/* Feature 4: Score button */}
        {onScoreApplicant && (
          <Button
            size="sm"
            variant="flat"
            className="w-full text-xs bg-theme-elevated text-theme-muted"
            startContent={<Star size={12} aria-hidden="true" />}
            onPress={() => onScoreApplicant(application)}
          >
            {t('scorecard.title', 'Score')}
          </Button>
        )}
      </GlassCard>
    </motion.div>
  );
}

// ---------------------------------------------------------------------------
// KanbanColumn
// ---------------------------------------------------------------------------

interface ColumnProps {
  column: typeof COLUMNS[number];
  applications: KanbanApplication[];
  onDragStart: (appId: number) => void;
  onDrop: (columnStatus: string) => void;
  onDownloadCv?: (appId: number, applicantName: string) => void;
  onScheduleInterview?: (app: KanbanApplication) => void;
  onSendOffer?: (app: KanbanApplication) => void;
  onScoreApplicant?: (app: KanbanApplication) => void;
  onSelect?: (id: number, checked: boolean) => void;
  selectedAppIds?: Set<number>;
}

function KanbanColumn({ column, applications, onDragStart, onDrop, onDownloadCv, onScheduleInterview, onSendOffer, onScoreApplicant, onSelect, selectedAppIds }: ColumnProps) {
  const { t } = useTranslation('jobs');
  const [isDragOver, setIsDragOver] = useState(false);

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragOver(true);
  };

  const handleDragLeave = () => {
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragOver(false);
    onDrop(column.status);
  };

  return (
    <div className="flex-shrink-0 w-64">
      {/* Column header */}
      <div className="flex items-center gap-2 mb-3">
        <Chip size="sm" color={column.color} variant="flat" className="font-medium">
          {column.id}
        </Chip>
        <span className="text-xs text-theme-subtle font-medium bg-theme-elevated rounded-full px-2 py-0.5">
          {applications.length}
        </span>
      </div>

      {/* Drop zone */}
      <div
        role="list"
        aria-label={column.id}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        className={`min-h-48 rounded-xl p-2 space-y-2 transition-colors ${
          isDragOver
            ? 'bg-primary/10 border-2 border-primary/40 border-dashed'
            : 'bg-theme-elevated/50 border-2 border-transparent'
        }`}
      >
        {applications.length === 0 ? (
          <div className="flex items-center justify-center h-24 text-theme-subtle text-xs">
            <FileText size={16} className="mr-1" aria-hidden="true" />
            {t('kanban.drop_here', 'Drop here')}
          </div>
        ) : (
          applications.map((app) => (
            <AppKanbanCard
              key={app.id}
              application={app}
              onDragStart={onDragStart}
              onDownloadCv={onDownloadCv}
              onScheduleInterview={onScheduleInterview}
              onSendOffer={onSendOffer}
              onScoreApplicant={onScoreApplicant}
              onSelect={onSelect}
              isSelected={selectedAppIds?.has(app.id)}
            />
          ))
        )}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// JobKanbanPage
// ---------------------------------------------------------------------------

export function JobKanbanPage() {
  const { t } = useTranslation('jobs');
  const { id } = useParams<{ id: string }>();
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [vacancy, setVacancy] = useState<KanbanVacancy | null>(null);
  const [applications, setApplications] = useState<KanbanApplication[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draggingId, setDraggingId] = useState<number | null>(null);

  // Feature 1: Interview scheduling
  const [interviewModalApp, setInterviewModalApp] = useState<KanbanApplication | null>(null);
  const [interviewForm, setInterviewForm] = useState({
    interview_type: 'video' as 'video' | 'phone' | 'in_person',
    scheduled_at: '',
    duration_mins: 60,
    location_notes: '',
  });
  const [isSubmittingInterview, setIsSubmittingInterview] = useState(false);

  // Feature 2: Offer
  const [offerModalApp, setOfferModalApp] = useState<KanbanApplication | null>(null);
  const [offerForm, setOfferForm] = useState({
    salary_offered: '',
    salary_currency: 'EUR',
    salary_type: 'annual' as 'hourly' | 'monthly' | 'annual',
    start_date: '',
    message: '',
  });
  const [isSubmittingOffer, setIsSubmittingOffer] = useState(false);

  // Feature 4: Scorecard
  const [scorecardModalApp, setScorecardModalApp] = useState<KanbanApplication | null>(null);

  // Bulk selection state
  const [selectedAppIds, setSelectedAppIds] = useState<Set<number>>(new Set());
  const [bulkStatus, setBulkStatus] = useState('');
  const [isBulkUpdating, setIsBulkUpdating] = useState(false);
  const [scorecardCriteria, setScorecardCriteria] = useState([
    { label: t('scorecard.communication'), score: 5 },
    { label: t('scorecard.technical'), score: 5 },
    { label: t('scorecard.cultural_fit'), score: 5 },
    { label: t('scorecard.experience'), score: 5 },
    { label: t('scorecard.motivation'), score: 5 },
  ]);
  const [scorecardNotes, setScorecardNotes] = useState('');
  const [isSubmittingScorecard, setIsSubmittingScorecard] = useState(false);

  usePageTitle(vacancy ? `${t('kanban.pipeline_title', 'Kanban Pipeline')} — ${vacancy.title}` : t('kanban.pipeline_title', 'Kanban Pipeline'));

  const abortRef = useRef<AbortController | null>(null);
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const loadData = useCallback(async () => {
    if (!id) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setIsLoading(true);
    setError(null);
    try {
      const [vacancyRes, appsRes] = await Promise.all([
        api.get<KanbanVacancy>(`/v2/jobs/${id}`),
        api.get<KanbanApplication[]>(`/v2/jobs/${id}/applications`),
      ]);
      if (controller.signal.aborted) return;

      if (vacancyRes.success && vacancyRes.data) {
        setVacancy(vacancyRes.data);
      } else {
        setError(tRef.current('detail.not_found'));
        return;
      }
      if (appsRes.success && appsRes.data) {
        setApplications(appsRes.data);
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('JobKanbanPage: failed to load data', err);
      setError(tRef.current('detail.unable_to_load'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
    return () => { abortRef.current?.abort(); };
  }, [loadData]);

  const isOwner = vacancy && user && vacancy.user_id === user.id;

  const handleDragStart = (appId: number) => {
    setDraggingId(appId);
  };

  const handleDrop = async (targetStatus: string) => {
    if (draggingId == null) return;
    const capturedId = draggingId;
    const app = applications.find((a) => a.id === capturedId);
    if (!app) return;

    // Normalize current status to column status
    const currentColumn = STATUS_TO_COLUMN[app.stage ?? app.status];
    const targetColumn = COLUMNS.find((c) => c.status === targetStatus)?.id;
    if (currentColumn === targetColumn) {
      setDraggingId(null);
      return;
    }

    // Optimistic update
    setApplications((prev) =>
      prev.map((a) => a.id === capturedId ? { ...a, status: targetStatus, stage: targetStatus } : a)
    );
    setDraggingId(null);

    try {
      const response = await api.put(`/v2/jobs/applications/${capturedId}`, { status: targetStatus });
      if (!response.success) {
        // Revert on failure
        setApplications((prev) =>
          prev.map((a) => a.id === capturedId ? { ...a, status: app.status, stage: app.stage } : a)
        );
        toastRef.current.error(tRef.current('detail.status_update_error'));
      } else {
        toastRef.current.success(tRef.current('detail.status_updated'));
      }
    } catch (err) {
      logError('JobKanbanPage: failed to update status', err);
      setApplications((prev) =>
        prev.map((a) => a.id === capturedId ? { ...a, status: app.status, stage: app.stage } : a)
      );
      toastRef.current.error(tRef.current('detail.status_update_error'));
    }
  };

  const handleDownloadCv = (appId: number, applicantName: string) => {
    const token = localStorage.getItem('nexus_access_token');
    // Open CV download URL in a new tab
    const url = `${API_BASE}/v2/jobs/applications/${appId}/cv`;
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    if (token) {
      // For authenticated downloads, append token as query param (fallback)
      link.href = `${url}?token=${encodeURIComponent(token)}`;
    }
    link.download = `CV-${applicantName.replace(/\s+/g, '-')}.pdf`;
    link.click();
  };

  // Helper to move a card to a target status (used by interview/offer submit)
  const handleMoveCard = async (appId: number, targetStatus: string) => {
    const app = applications.find((a) => a.id === appId);
    if (!app) return;
    setApplications((prev) =>
      prev.map((a) => a.id === appId ? { ...a, status: targetStatus, stage: targetStatus } : a)
    );
    try {
      await api.put(`/v2/jobs/applications/${appId}`, { status: targetStatus });
    } catch (err) {
      logError('JobKanbanPage: failed to move card', err);
      // Revert on failure
      setApplications((prev) =>
        prev.map((a) => a.id === appId ? { ...a, status: app.status, stage: app.stage } : a)
      );
    }
  };

  // Feature 1: Propose interview
  const handleProposeInterview = async () => {
    if (!interviewModalApp) return;
    const appId = interviewModalApp.id;
    setIsSubmittingInterview(true);
    try {
      await api.post(`/v2/jobs/applications/${appId}/interview`, interviewForm);
      toastRef.current.success(tRef.current('interview.send_request', 'Interview request sent'));
      setInterviewModalApp(null);
      await handleMoveCard(appId, 'interview');
    } catch (err) {
      logError('JobKanbanPage: failed to propose interview', err);
      toastRef.current.error(tRef.current('detail.status_update_error'));
    } finally {
      setIsSubmittingInterview(false);
    }
  };

  // Feature 2: Send offer
  const handleSendOffer = async () => {
    if (!offerModalApp) return;
    const appId = offerModalApp.id;
    setIsSubmittingOffer(true);
    try {
      await api.post(`/v2/jobs/applications/${appId}/offer`, offerForm);
      toastRef.current.success(tRef.current('offer.send', 'Offer sent'));
      setOfferModalApp(null);
      setOfferForm({ salary_offered: '', salary_currency: 'EUR', salary_type: 'annual', start_date: '', message: '' });
      await handleMoveCard(appId, 'offer');
      await loadData();
    } catch (err) {
      logError('JobKanbanPage: failed to send offer', err);
      toastRef.current.error(tRef.current('detail.status_update_error'));
    } finally {
      setIsSubmittingOffer(false);
    }
  };

  // Feature 4: Submit scorecard
  const handleSubmitScorecard = async () => {
    if (!scorecardModalApp) return;
    const appId = scorecardModalApp.id;
    setIsSubmittingScorecard(true);
    try {
      const payload = {
        criteria: scorecardCriteria.map((c) => ({ label: c.label, score: c.score, max_score: 10 })),
        notes: scorecardNotes,
      };
      const response = await api.put(`/v2/jobs/applications/${appId}/scorecard`, payload);
      if (response.success) {
        toastRef.current.success(tRef.current('scorecard.saved', 'Scorecard saved'));
        setScorecardModalApp(null);
        setScorecardNotes('');
        setScorecardCriteria([
          { label: 'Communication', score: 5 },
          { label: 'Technical Skills', score: 5 },
          { label: 'Cultural Fit', score: 5 },
          { label: 'Experience', score: 5 },
          { label: 'Motivation', score: 5 },
        ]);
      } else {
        toastRef.current.error(tRef.current('detail.status_update_error'));
      }
    } catch (err) {
      logError('JobKanbanPage: failed to submit scorecard', err);
      toastRef.current.error(tRef.current('detail.status_update_error'));
    } finally {
      setIsSubmittingScorecard(false);
    }
  };

  // Bulk selection handler
  const handleSelectApp = (appId: number, checked: boolean) => {
    setSelectedAppIds((prev) => {
      const next = new Set(prev);
      if (checked) {
        next.add(appId);
      } else {
        next.delete(appId);
      }
      return next;
    });
  };

  // Bulk status update handler
  const handleBulkStatusUpdate = async () => {
    if (!id || !bulkStatus || selectedAppIds.size === 0) return;
    setIsBulkUpdating(true);
    try {
      await api.post(`/v2/jobs/${id}/applications/bulk-status`, {
        application_ids: Array.from(selectedAppIds),
        status: bulkStatus,
      });
      toastRef.current.success(tRef.current('bulk.success', `${selectedAppIds.size} applications updated`));
      setSelectedAppIds(new Set());
      setBulkStatus('');
      await loadData();
    } catch (err) {
      logError('JobKanbanPage: bulk update failed', err);
      toastRef.current.error(tRef.current('bulk.error', 'Bulk update failed'));
    } finally {
      setIsBulkUpdating(false);
    }
  };

  // Map applications to columns
  const columnApplications = (columnStatus: string) =>
    applications.filter((app) => {
      const colId = STATUS_TO_COLUMN[app.stage ?? app.status] ?? 'Applied';
      const col = COLUMNS.find((c) => c.status === columnStatus);
      return col && colId === col.id;
    });

  // ---------------------------------------------------------------------------
  // Guards
  // ---------------------------------------------------------------------------

  if (!isAuthenticated) {
    return (
      <div className="text-center py-16">
        <p className="text-theme-muted">{t('apply.login_required')}</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error || !vacancy) {
    return (
      <div className="text-center py-16 space-y-4">
        <Briefcase className="w-12 h-12 mx-auto text-theme-subtle" aria-hidden="true" />
        <p className="text-theme-muted">{error || t('detail.not_found')}</p>
        <Link to={tenantPath('/jobs')}>
          <Button variant="flat" className="bg-theme-elevated text-theme-muted">
            {t('detail.browse_vacancies')}
          </Button>
        </Link>
      </div>
    );
  }

  if (!isOwner) {
    return (
      <div className="text-center py-16 space-y-4">
        <Briefcase className="w-12 h-12 mx-auto text-theme-subtle" aria-hidden="true" />
        <p className="text-theme-muted">{t('kanban.not_authorized', 'You are not authorized to view this pipeline.')}</p>
        <Link to={tenantPath(`/jobs/${id}`)}>
          <Button variant="flat" className="bg-theme-elevated text-theme-muted">
            {t('detail.browse_vacancies')}
          </Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <Link
            to={tenantPath(`/jobs/${id}`)}
            className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            {t('detail.browse_vacancies')}
          </Link>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Briefcase className="w-7 h-7 text-indigo-400" aria-hidden="true" />
            {t('kanban.pipeline_title', 'Kanban Pipeline')}
          </h1>
          <p className="text-theme-muted text-sm mt-1">{vacancy.title}</p>
        </div>
        <Chip variant="flat" color="default">
          {applications.length} {t('applications', { count: applications.length })}
        </Chip>
      </div>

      {/* Bulk action bar */}
      {selectedAppIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center gap-3 p-3 mb-4 bg-primary/10 border border-primary/30 rounded-xl backdrop-blur-sm">
          <span className="text-sm font-medium text-primary">{t('bulk.selected_count', '{{count}} selected', { count: selectedAppIds.size })}</span>
          <Select
            size="sm"
            placeholder={t('bulk.select_action', 'Move to stage...')}
            className="w-48"
            selectedKeys={bulkStatus ? [bulkStatus] : []}
            onSelectionChange={(keys) => setBulkStatus(Array.from(keys)[0] as string ?? '')}
          >
            {(['screening', 'reviewed', 'interview', 'rejected'] as const).map((s) => (
              <SelectItem key={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</SelectItem>
            ))}
          </Select>
          <Button
            size="sm"
            color="primary"
            isLoading={isBulkUpdating}
            isDisabled={!bulkStatus}
            onPress={handleBulkStatusUpdate}
          >
            {t('bulk.apply', 'Apply')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            onPress={() => { setSelectedAppIds(new Set()); setBulkStatus(''); }}
          >
            {t('bulk.clear', 'Clear')}
          </Button>
        </div>
      )}

      {/* Kanban board */}
      <div className="overflow-x-auto pb-4">
        <div className="flex gap-4" style={{ minWidth: `${COLUMNS.length * 17}rem` }}>
          {COLUMNS.map((column) => (
            <KanbanColumn
              key={column.id}
              column={column}
              applications={columnApplications(column.status)}
              onDragStart={handleDragStart}
              onDrop={handleDrop}
              onDownloadCv={handleDownloadCv}
              onScheduleInterview={setInterviewModalApp}
              onSendOffer={setOfferModalApp}
              onScoreApplicant={setScorecardModalApp}
              onSelect={handleSelectApp}
              selectedAppIds={selectedAppIds}
            />
          ))}
        </div>
      </div>

      {/* Feature 1: Interview Scheduling Modal */}
      <Modal isOpen={!!interviewModalApp} onClose={() => setInterviewModalApp(null)} size="md">
        <ModalContent>
          <ModalHeader>{t('interview.schedule', 'Schedule Interview')} — {interviewModalApp?.applicant.name}</ModalHeader>
          <ModalBody className="space-y-4">
            <Select
              label={t('interview.type_video', 'Interview Type')}
              selectedKeys={[interviewForm.interview_type]}
              onSelectionChange={(keys) => {
                const key = [...keys][0] as 'video' | 'phone' | 'in_person';
                setInterviewForm((f) => ({ ...f, interview_type: key }));
              }}
            >
              <SelectItem key="video">{t('interview.type_video', 'Video Call')}</SelectItem>
              <SelectItem key="phone">{t('interview.type_phone', 'Phone Call')}</SelectItem>
              <SelectItem key="in_person">{t('interview.type_in_person', 'In Person')}</SelectItem>
            </Select>
            <Input
              type="datetime-local"
              label={t('interview.datetime', 'Date & Time')}
              isRequired
              value={interviewForm.scheduled_at}
              onChange={(e) => setInterviewForm((f) => ({ ...f, scheduled_at: e.target.value }))}
            />
            <Select
              label={t('interview.duration', 'Duration')}
              selectedKeys={[String(interviewForm.duration_mins)]}
              onSelectionChange={(keys) => {
                const val = Number([...keys][0]);
                setInterviewForm((f) => ({ ...f, duration_mins: val }));
              }}
            >
              <SelectItem key="30">{t('self_scheduling.duration_30', '30 minutes')}</SelectItem>
              <SelectItem key="45">{t('self_scheduling.duration_45', '45 minutes')}</SelectItem>
              <SelectItem key="60">{t('self_scheduling.duration_60', '1 hour')}</SelectItem>
              <SelectItem key="90">{t('kanban.duration_90', '1.5 hours')}</SelectItem>
            </Select>
            <Input
              label={t('interview.location', 'Meeting Link / Location')}
              placeholder="https://meet.google.com/..."
              value={interviewForm.location_notes}
              onChange={(e) => setInterviewForm((f) => ({ ...f, location_notes: e.target.value }))}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setInterviewModalApp(null)}>
              {t('apply.cancel')}
            </Button>
            <Button color="primary" isLoading={isSubmittingInterview} onPress={handleProposeInterview}>
              {t('interview.send_request', 'Send Interview Request')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Feature 4: Scorecard Modal */}
      <Modal isOpen={!!scorecardModalApp} onClose={() => setScorecardModalApp(null)} size="md">
        <ModalContent>
          <ModalHeader>
            <span className="flex items-center gap-2">
              <Star size={16} aria-hidden="true" />
              {t('scorecard.title', 'Score Applicant')}: {scorecardModalApp?.applicant.name}
            </span>
          </ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm font-medium text-theme-primary">{t('scorecard.criteria', 'Scoring Criteria')}</p>
            {scorecardCriteria.map((criterion, idx) => (
              <div key={idx} className="flex items-center gap-3">
                <span className="text-sm text-theme-secondary w-36 flex-shrink-0">{criterion.label}</span>
                <Input
                  type="number"
                  min={1}
                  max={10}
                  value={String(criterion.score)}
                  onChange={(e) => {
                    const val = Math.min(10, Math.max(1, parseInt(e.target.value, 10) || 1));
                    setScorecardCriteria((prev) =>
                      prev.map((c, i) => (i === idx ? { ...c, score: val } : c))
                    );
                  }}
                  className="w-24"
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />
                <span className="text-xs text-theme-subtle">/ 10</span>
              </div>
            ))}
            <Textarea
              label={t('scorecard.notes', 'Reviewer Notes')}
              placeholder={t('scorecard.notes_placeholder', 'Optional notes about this applicant...')}
              value={scorecardNotes}
              onValueChange={setScorecardNotes}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setScorecardModalApp(null)}>
              {t('apply.cancel')}
            </Button>
            <Button color="primary" isLoading={isSubmittingScorecard} onPress={handleSubmitScorecard}>
              {t('scorecard.save', 'Save Scorecard')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Feature 2: Send Offer Modal */}
      <Modal isOpen={!!offerModalApp} onClose={() => setOfferModalApp(null)} size="md">
        <ModalContent>
          <ModalHeader>{t('offer.modal_title', 'Send Job Offer')} — {offerModalApp?.applicant.name}</ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              type="number"
              label={t('offer.salary', 'Salary Offered')}
              placeholder="e.g. 45000"
              value={offerForm.salary_offered}
              onChange={(e) => setOfferForm((f) => ({ ...f, salary_offered: e.target.value }))}
            />
            <Select
              label={t('salary.form_currency_label', 'Currency')}
              selectedKeys={[offerForm.salary_currency]}
              onSelectionChange={(keys) => {
                const key = [...keys][0] as string;
                setOfferForm((f) => ({ ...f, salary_currency: key }));
              }}
            >
              <SelectItem key="EUR">EUR</SelectItem>
              <SelectItem key="GBP">GBP</SelectItem>
              <SelectItem key="USD">USD</SelectItem>
            </Select>
            <Select
              label={t('salary.form_type_label', 'Pay Type')}
              selectedKeys={[offerForm.salary_type]}
              onSelectionChange={(keys) => {
                const key = [...keys][0] as 'hourly' | 'monthly' | 'annual';
                setOfferForm((f) => ({ ...f, salary_type: key }));
              }}
            >
              <SelectItem key="hourly">{t('salary.hourly', 'per hour')}</SelectItem>
              <SelectItem key="monthly">{t('kanban.salary_monthly', 'Monthly')}</SelectItem>
              <SelectItem key="annual">{t('salary.annual', 'per year')}</SelectItem>
            </Select>
            <Input
              type="date"
              label={t('offer.start_date', 'Start Date')}
              value={offerForm.start_date}
              onChange={(e) => setOfferForm((f) => ({ ...f, start_date: e.target.value }))}
            />
            <Textarea
              label={t('kanban.personal_message', 'Personal Message')}
              placeholder={t('kanban.personal_message_placeholder', 'A note to the candidate...')}
              value={offerForm.message}
              onValueChange={(val) => setOfferForm((f) => ({ ...f, message: val }))}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setOfferModalApp(null)}>
              {t('apply.cancel')}
            </Button>
            <Button color="warning" isLoading={isSubmittingOffer} onPress={handleSendOffer}>
              {t('offer.send', 'Send Offer')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default JobKanbanPage;
