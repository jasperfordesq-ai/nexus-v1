// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Job Detail Page - Full vacancy detail with apply, save, match, pipeline
 *
 * Features:
 * - Full description, skills, contact info, salary display
 * - Apply modal with message textarea
 * - Save/bookmark toggle (J1)
 * - Skills match percentage badge (J2)
 * - Application pipeline stages (J3)
 * - Application status history (J4)
 * - "Am I Qualified?" tool (J5)
 * - Job renewal for owners (J7)
 * - Featured badge (J10)
 * - Salary/compensation display (J9)
 * - Analytics link for owners (J8)
 * - Edit/delete for owner
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
  Divider,
  Progress,
  Tooltip,
} from '@heroui/react';
import {
  Briefcase,
  MapPin,
  Clock,
  Eye,
  FileText,
  ArrowLeft,
  Edit3,
  Trash2,
  Mail,
  Phone,
  Wifi,
  DollarSign,
  Heart,
  Timer,
  Tag,
  Calendar,
  CheckCircle,
  XCircle,
  RefreshCw,
  Users,
  Bookmark,
  BookmarkCheck,
  Target,
  BarChart3,
  Star,
  ChevronRight,
  History,
  Check,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { resolveAvatarUrl } from '@/lib/helpers';

interface JobVacancy {
  id: number;
  title: string;
  description: string;
  location: string | null;
  is_remote: boolean;
  type: 'paid' | 'volunteer' | 'timebank';
  commitment: 'full_time' | 'part_time' | 'flexible' | 'one_off';
  category: string | null;
  skills: string[];
  skills_required: string | null;
  hours_per_week: number | null;
  time_credits: number | null;
  contact_email: string | null;
  contact_phone: string | null;
  deadline: string | null;
  status: string;
  views_count: number;
  applications_count: number;
  created_at: string;
  user_id: number;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  organization: {
    id: number;
    name: string;
    logo_url: string | null;
  } | null;
  has_applied: boolean;
  application_status: string | null;
  application_stage: string | null;
  is_saved: boolean;
  is_featured: boolean;
  featured_until: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_type: string | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
  expired_at: string | null;
  renewed_at: string | null;
  renewal_count: number;
}

interface Application {
  id: number;
  vacancy_id: number;
  user_id: number;
  message: string | null;
  status: string;
  stage: string;
  reviewer_notes: string | null;
  created_at: string;
  applicant: {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string | null;
  };
}

interface MatchResult {
  percentage: number;
  matched: string[];
  missing: string[];
  user_skills: string[];
  required_skills: string[];
}

interface QualificationResult {
  percentage: number;
  level: string;
  total_required: number;
  total_matched: number;
  total_missing: number;
  breakdown: Array<{ skill: string; matched: boolean }>;
  matched_skills: string[];
  missing_skills: string[];
}

interface HistoryEntry {
  id: number;
  from_status: string | null;
  to_status: string;
  changed_by_name: string;
  changed_at: string;
  notes: string | null;
}

const TYPE_CHIP_COLORS: Record<string, 'success' | 'secondary' | 'primary'> = {
  paid: 'success',
  volunteer: 'secondary',
  timebank: 'primary',
};

const TYPE_ICONS: Record<string, typeof DollarSign> = {
  paid: DollarSign,
  volunteer: Heart,
  timebank: Timer,
};

const STATUS_COLORS: Record<string, 'warning' | 'primary' | 'success' | 'danger' | 'default' | 'secondary'> = {
  applied: 'warning',
  pending: 'warning',
  screening: 'primary',
  reviewed: 'primary',
  interview: 'secondary',
  offer: 'success',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'default',
};

export function JobDetailPage() {
  const { t } = useTranslation('jobs');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const applyModal = useDisclosure();
  const qualifiedModal = useDisclosure();
  const renewModal = useDisclosure();
  const deleteModal = useDisclosure();

  const [vacancy, setVacancy] = useState<JobVacancy | null>(null);
  const [applications, setApplications] = useState<Application[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [applyMessage, setApplyMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showApplications, setShowApplications] = useState(true);
  const [isLoadingApps, setIsLoadingApps] = useState(false);

  // J1: Saved state
  const [isSaved, setIsSaved] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // J2: Match percentage
  const [matchResult, setMatchResult] = useState<MatchResult | null>(null);

  // J5: Qualification assessment
  const [qualification, setQualification] = useState<QualificationResult | null>(null);
  const [isLoadingQualification, setIsLoadingQualification] = useState(false);

  // J7: Renewal
  const [renewDays, setRenewDays] = useState(30);
  const [isRenewing, setIsRenewing] = useState(false);

  usePageTitle(vacancy?.title ?? t('detail.loading'));

  const isOwner = vacancy && user && vacancy.user_id === user.id;

  const loadVacancy = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<JobVacancy>(`/v2/jobs/${id}`);
      if (response.success && response.data) {
        setVacancy(response.data);
        setIsSaved(response.data.is_saved ?? false);
      } else {
        setError(t('detail.not_found'));
      }
    } catch (err) {
      logError('Failed to load job vacancy', err);
      setError(t('detail.unable_to_load'));
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    loadVacancy();
  }, [loadVacancy]);

  // J2: Load match percentage when user is authenticated
  useEffect(() => {
    if (!id || !isAuthenticated || !vacancy || isOwner) return;
    const loadMatch = async () => {
      try {
        const response = await api.get<MatchResult>(`/v2/jobs/${id}/match`);
        if (response.success && response.data) {
          setMatchResult(response.data);
        }
      } catch {
        // Non-critical
      }
    };
    loadMatch();
  }, [id, isAuthenticated, vacancy, isOwner]);

  const loadApplications = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoadingApps(true);
      const response = await api.get<Application[]>(`/v2/jobs/${id}/applications`);
      if (response.success && response.data) {
        setApplications(response.data);
      }
    } catch (err) {
      logError('Failed to load applications', err);
    } finally {
      setIsLoadingApps(false);
    }
  }, [id]);

  useEffect(() => {
    if (showApplications && isOwner) {
      loadApplications();
    }
  }, [showApplications, isOwner, loadApplications]);

  const handleApply = async () => {
    if (!id) return;
    setIsSubmitting(true);
    try {
      const response = await api.post(`/v2/jobs/${id}/apply`, {
        message: applyMessage || null,
      });
      if (response.success) {
        toast.success(t('apply.success'));
        applyModal.onClose();
        setApplyMessage('');
        loadVacancy();
      } else {
        toast.error(response.error || t('apply.error'));
      }
    } catch (err) {
      logError('Failed to apply for job', err);
      toast.error(t('apply.error'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!id) return;
    try {
      const response = await api.delete(`/v2/jobs/${id}`);
      if (response.success) {
        toast.success(t('detail.deleted'));
        deleteModal.onClose();
        navigate(tenantPath('/jobs'));
      } else {
        toast.error(t('detail.delete_error'));
      }
    } catch (err) {
      logError('Failed to delete vacancy', err);
      toast.error(t('detail.delete_error'));
    }
  };

  // J1: Save/unsave
  const handleToggleSave = async () => {
    if (!id || isSaving) return;
    setIsSaving(true);
    try {
      if (isSaved) {
        const response = await api.delete(`/v2/jobs/${id}/save`);
        if (response.success) {
          setIsSaved(false);
          toast.success(t('saved.unsave_success'));
        }
      } else {
        const response = await api.post(`/v2/jobs/${id}/save`, {});
        if (response.success) {
          setIsSaved(true);
          toast.success(t('saved.save_success'));
        }
      }
    } catch (err) {
      logError('Failed to toggle save', err);
      toast.error(t('saved.save_error'));
    } finally {
      setIsSaving(false);
    }
  };

  // J3: Update application status (now supports pipeline stages)
  const handleUpdateAppStatus = async (applicationId: number, status: string) => {
    try {
      const response = await api.put(`/v2/jobs/applications/${applicationId}`, { status });
      if (response.success) {
        toast.success(t('detail.status_updated'));
        loadApplications();
      } else {
        toast.error(t('detail.status_update_error'));
      }
    } catch (err) {
      logError('Failed to update application status', err);
      toast.error(t('detail.status_update_error'));
    }
  };

  // J5: Load qualification assessment
  const handleCheckQualification = async () => {
    if (!id) return;
    setIsLoadingQualification(true);
    qualifiedModal.onOpen();
    try {
      const response = await api.get<QualificationResult>(`/v2/jobs/${id}/qualified`);
      if (response.success && response.data) {
        setQualification(response.data);
      }
    } catch (err) {
      logError('Failed to load qualification', err);
    } finally {
      setIsLoadingQualification(false);
    }
  };

  // J7: Renew job
  const handleRenew = async () => {
    if (!id) return;
    setIsRenewing(true);
    try {
      const response = await api.post(`/v2/jobs/${id}/renew`, { days: renewDays });
      if (response.success) {
        toast.success(t('renew.success'));
        renewModal.onClose();
        loadVacancy();
      } else {
        toast.error(t('renew.error'));
      }
    } catch (err) {
      logError('Failed to renew job', err);
      toast.error(t('renew.error'));
    } finally {
      setIsRenewing(false);
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="space-y-6">
        <GlassCard className="p-6 animate-pulse">
          <div className="h-8 bg-theme-hover rounded w-1/2 mb-4" />
          <div className="h-4 bg-theme-hover rounded w-3/4 mb-2" />
          <div className="h-4 bg-theme-hover rounded w-1/2 mb-4" />
          <div className="h-24 bg-theme-hover rounded mb-4" />
        </GlassCard>
      </div>
    );
  }

  // Error state
  if (error || !vacancy) {
    return (
      <EmptyState
        icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
        title={error || t('detail.not_found')}
        description={t('detail.not_found_desc')}
        action={
          <div className="flex gap-2">
            <Link to={tenantPath('/jobs')}>
              <Button variant="flat" className="bg-theme-elevated text-theme-muted">
                {t('detail.browse_vacancies')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadVacancy}
            >
              {t('detail.try_again')}
            </Button>
          </div>
        }
      />
    );
  }

  const deadlineDate = vacancy.deadline ? new Date(vacancy.deadline) : null;
  const isPastDeadline = deadlineDate ? deadlineDate < new Date() : false;
  const TypeIcon = TYPE_ICONS[vacancy.type] ?? Briefcase;

  // J9: Format salary display
  const formatSalary = () => {
    if (!vacancy.salary_min && !vacancy.salary_max) return null;
    const currency = vacancy.salary_currency || '';
    const typeLabel = vacancy.salary_type ? t(`salary.${vacancy.salary_type}`) : '';
    if (vacancy.salary_min && vacancy.salary_max) {
      return `${currency} ${vacancy.salary_min.toLocaleString()} - ${vacancy.salary_max.toLocaleString()} ${typeLabel}`;
    }
    if (vacancy.salary_min) return `${t('salary.min_only', { min: `${currency} ${vacancy.salary_min.toLocaleString()}` })} ${typeLabel}`;
    if (vacancy.salary_max) return `${t('salary.max_only', { max: `${currency} ${vacancy.salary_max.toLocaleString()}` })} ${typeLabel}`;
    return null;
  };

  return (
    <div className="space-y-6">
      {/* Back nav */}
      <Link to={tenantPath('/jobs')} className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors">
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {t('detail.browse_vacancies')}
      </Link>

      {/* Header Card */}
      <GlassCard className="p-6">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div className="flex-1">
            <div className="flex items-center gap-3 flex-wrap mb-2">
              <h1 className="text-2xl font-bold text-theme-primary">{vacancy.title}</h1>
              {/* J10: Featured badge */}
              {vacancy.is_featured && (
                <Chip size="sm" variant="flat" color="warning" startContent={<Star className="w-3 h-3" aria-hidden="true" />}>
                  {t('featured')}
                </Chip>
              )}
              <Chip size="sm" variant="flat" color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'}>
                <span className="flex items-center gap-1">
                  <TypeIcon className="w-3 h-3" aria-hidden="true" />
                  {t(`type.${vacancy.type}`)}
                </span>
              </Chip>
              <Chip size="sm" variant="flat" color="default">
                {t(`commitment.${vacancy.commitment}`)}
              </Chip>
              <Chip size="sm" variant="flat" color={vacancy.status === 'open' ? 'success' : 'default'}>
                {t(`status.${vacancy.status}`)}
              </Chip>
            </div>

            {/* Poster info */}
            <div className="flex items-center gap-2 mt-3">
              <Avatar
                name={vacancy.creator.name}
                src={resolveAvatarUrl(vacancy.creator.avatar_url)}
                size="sm"
                isBordered
              />
              <div>
                <p className="text-sm text-theme-primary font-medium">
                  {vacancy.organization?.name ?? vacancy.creator.name}
                </p>
                <p className="text-xs text-theme-subtle">
                  {t('posted_by')} {vacancy.creator.name} &middot; {new Date(vacancy.created_at).toLocaleDateString()}
                </p>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-2 flex-wrap">
            {/* J1: Save button */}
            {isAuthenticated && !isOwner && (
              <Tooltip content={isSaved ? t('saved.unsave') : t('saved.save')}>
                <Button
                  size="sm"
                  variant="flat"
                  isIconOnly
                  className={isSaved ? 'text-warning bg-warning/10' : 'bg-theme-elevated text-theme-muted'}
                  onPress={handleToggleSave}
                  isLoading={isSaving}
                  aria-label={isSaved ? t('saved.unsave') : t('saved.save')}
                >
                  {isSaved ? <BookmarkCheck className="w-4 h-4" aria-hidden="true" /> : <Bookmark className="w-4 h-4" aria-hidden="true" />}
                </Button>
              </Tooltip>
            )}

            {isOwner && (
              <>
                {/* J8: Analytics link */}
                <Link to={tenantPath(`/jobs/${vacancy.id}/analytics`)}>
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('detail.analytics')}
                  </Button>
                </Link>

                {/* J7: Renew button (show when expiring soon or expired) */}
                {(isPastDeadline || vacancy.status === 'closed') && (
                  <Button
                    size="sm"
                    variant="flat"
                    color="warning"
                    startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
                    onPress={renewModal.onOpen}
                  >
                    {t('detail.renew')}
                  </Button>
                )}

                <Link to={tenantPath(`/jobs/${vacancy.id}/edit`)}>
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<Edit3 className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('detail.edit')}
                  </Button>
                </Link>
                <Button
                  size="sm"
                  variant="flat"
                  color="danger"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={deleteModal.onOpen}
                >
                  {t('detail.delete')}
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Stats row */}
        <div className="flex flex-wrap items-center gap-4 mt-4 text-sm text-theme-subtle">
          {vacancy.is_remote ? (
            <span className="flex items-center gap-1">
              <Wifi className="w-4 h-4" aria-hidden="true" />
              {t('remote')}
            </span>
          ) : vacancy.location ? (
            <span className="flex items-center gap-1">
              <MapPin className="w-4 h-4" aria-hidden="true" />
              {vacancy.location}
            </span>
          ) : null}

          <span className="flex items-center gap-1">
            <Eye className="w-4 h-4" aria-hidden="true" />
            {t('detail.views', { count: vacancy.views_count })}
          </span>

          <span className="flex items-center gap-1">
            <FileText className="w-4 h-4" aria-hidden="true" />
            {t('applications', { count: vacancy.applications_count })}
          </span>

          {deadlineDate && (
            <span className={`flex items-center gap-1 ${isPastDeadline ? 'text-danger' : ''}`}>
              <Calendar className="w-4 h-4" aria-hidden="true" />
              {isPastDeadline
                ? t('deadline_passed')
                : `${t('detail.deadline_label')}: ${deadlineDate.toLocaleDateString()}`}
            </span>
          )}

          {/* J9: Salary display */}
          {formatSalary() && (
            <span className="flex items-center gap-1 font-medium text-theme-primary">
              <DollarSign className="w-4 h-4" aria-hidden="true" />
              {formatSalary()}
              {vacancy.salary_negotiable && (
                <Chip size="sm" variant="flat" color="default" className="ml-1 text-xs">
                  {t('salary.negotiable')}
                </Chip>
              )}
            </span>
          )}
        </div>

        {/* J2: Match percentage badge */}
        {matchResult && matchResult.required_skills.length > 0 && (
          <div className="mt-4">
            <MatchBadge match={matchResult} />
          </div>
        )}
      </GlassCard>

      {/* Owner management banner */}
      {isOwner && (
        <GlassCard className="p-4 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/20">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                <Briefcase className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              </div>
              <div>
                <p className="font-semibold text-theme-primary">{t('detail.owner_banner_title', 'You posted this vacancy')}</p>
                <p className="text-sm text-theme-muted">
                  {vacancy.applications_count > 0
                    ? t('detail.owner_has_applicants', '{{count}} applicant(s) — scroll down to review', { count: vacancy.applications_count })
                    : t('detail.owner_no_applicants', 'No applicants yet — share this listing to get more visibility')}
                </p>
              </div>
            </div>
            <div className="flex gap-2 flex-wrap">
              <Link to={tenantPath(`/jobs/${vacancy.id}/edit`)}>
                <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted" startContent={<Edit3 className="w-4 h-4" aria-hidden="true" />}>
                  Edit
                </Button>
              </Link>
              <Link to={tenantPath(`/jobs/${vacancy.id}/analytics`)}>
                <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted" startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}>
                  Analytics
                </Button>
              </Link>
              {vacancy.status === 'open' && (
                <Button size="sm" color="warning" variant="flat" onPress={async () => {
                  try {
                    const res = await api.put(`/v2/jobs/${vacancy.id}`, { status: 'closed' });
                    if (res.success) {
                      toast.success(t('vacancy_closed', 'Vacancy closed'));
                      loadVacancy();
                    } else {
                      toast.error(t('detail.status_update_error'));
                    }
                  } catch (err) {
                    logError('Failed to close vacancy', err);
                    toast.error(t('detail.status_update_error'));
                  }
                }}>
                  {t('detail.close_vacancy', 'Close Vacancy')}
                </Button>
              )}
              {vacancy.status !== 'open' && (
                <Button size="sm" color="success" variant="flat" onPress={async () => {
                  try {
                    const res = await api.put(`/v2/jobs/${vacancy.id}`, { status: 'open' });
                    if (res.success) {
                      toast.success(t('vacancy_reopened', 'Vacancy reopened'));
                      loadVacancy();
                    } else {
                      toast.error(t('detail.status_update_error'));
                    }
                  } catch (err) {
                    logError('Failed to reopen vacancy', err);
                    toast.error(t('detail.status_update_error'));
                  }
                }}>
                  {t('detail.reopen_vacancy', 'Reopen')}
                </Button>
              )}
            </div>
          </div>
        </GlassCard>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Description */}
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('detail.about')}</h2>
            <div className="text-theme-secondary whitespace-pre-wrap">{vacancy.description}</div>
          </GlassCard>

          {/* Skills */}
          {vacancy.skills.length > 0 && (
            <GlassCard className="p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-semibold text-theme-primary">{t('detail.skills_required')}</h2>
                {/* J5: "Am I Qualified?" button */}
                {isAuthenticated && !isOwner && (
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<Target className="w-4 h-4" aria-hidden="true" />}
                    onPress={handleCheckQualification}
                  >
                    {t('detail.check_qualification')}
                  </Button>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                {vacancy.skills.map((skill, idx) => {
                  // J2: Color skills based on match
                  const isMatched = matchResult?.matched?.includes(skill.toLowerCase());
                  const isMissing = matchResult?.missing?.includes(skill.toLowerCase());
                  return (
                    <Chip
                      key={idx}
                      variant="flat"
                      color={isMatched ? 'success' : isMissing ? 'danger' : 'primary'}
                      className={isMatched ? 'bg-success/10 text-success' : isMissing ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary'}
                      startContent={isMatched ? <Check className="w-3 h-3" /> : isMissing ? <X className="w-3 h-3" /> : undefined}
                    >
                      {skill}
                    </Chip>
                  );
                })}
              </div>
            </GlassCard>
          )}

          {/* Apply button (mobile) */}
          <div className="lg:hidden">
            {renderApplySection()}
          </div>

          {/* J3: Applications pipeline (owner view) */}
          {isOwner && (
            <div id="applications">
            <GlassCard className="p-6">
              <Button
                variant="light"
                onPress={() => setShowApplications(!showApplications)}
                className="flex items-center gap-2 w-full text-left justify-start h-auto p-0"
                startContent={<Users className="w-5 h-5 text-theme-subtle" aria-hidden="true" />}
                endContent={<ChevronRight className={`w-4 h-4 ml-auto text-theme-subtle transition-transform ${showApplications ? 'rotate-90' : ''}`} aria-hidden="true" />}
              >
                <h2 className="text-lg font-semibold text-theme-primary">
                  {t('detail.applications_tab')} ({vacancy.applications_count})
                </h2>
              </Button>

              {showApplications && (
                <div className="mt-4 space-y-4">
                  {isLoadingApps ? (
                    <div className="space-y-3">
                      {[1, 2].map((i) => (
                        <div key={i} className="p-4 rounded-lg bg-theme-elevated animate-pulse">
                          <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                          <div className="h-3 bg-theme-hover rounded w-2/3" />
                        </div>
                      ))}
                    </div>
                  ) : applications.length === 0 ? (
                    <div className="text-center py-8 space-y-3">
                      <div className="w-14 h-14 rounded-full bg-theme-elevated flex items-center justify-center mx-auto">
                        <Users className="w-7 h-7 text-theme-subtle" aria-hidden="true" />
                      </div>
                      <p className="font-medium text-theme-primary">{t('detail.no_applications', 'No applications yet')}</p>
                      <p className="text-sm text-theme-muted">{t('detail.no_applications_desc', "Share your vacancy to attract candidates. When someone applies, they'll appear here.")}</p>
                      <div className="flex gap-2 justify-center">
                        <Button
                          size="sm"
                          variant="flat"
                          className="bg-theme-elevated text-theme-muted"
                          startContent={<RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />}
                          onPress={loadApplications}
                        >
                          {t('detail.refresh', 'Refresh')}
                        </Button>
                      </div>
                    </div>
                  ) : (
                    applications.map((app) => (
                      <ApplicationCard
                        key={app.id}
                        application={app}
                        onUpdateStatus={handleUpdateAppStatus}
                      />
                    ))
                  )}
                </div>
              )}
            </GlassCard>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Apply section (desktop) */}
          <div className="hidden lg:block">
            {renderApplySection()}
          </div>

          {/* Details card */}
          <GlassCard className="p-6 space-y-4">
            {vacancy.category && (
              <div className="flex items-center gap-3">
                <Tag className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('detail.category_label')}</p>
                  <p className="text-sm text-theme-primary">{vacancy.category}</p>
                </div>
              </div>
            )}

            {vacancy.hours_per_week !== null && (
              <div className="flex items-center gap-3">
                <Clock className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('detail.hours_label')}</p>
                  <p className="text-sm text-theme-primary">{t('hours_per_week', { count: vacancy.hours_per_week })}</p>
                </div>
              </div>
            )}

            {vacancy.time_credits !== null && (
              <div className="flex items-center gap-3">
                <Timer className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('detail.time_credits_label')}</p>
                  <p className="text-sm text-theme-primary">{t('time_credits_label', { count: vacancy.time_credits })}</p>
                </div>
              </div>
            )}

            {/* J9: Salary display in sidebar */}
            {formatSalary() && (
              <div className="flex items-center gap-3">
                <DollarSign className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('salary.label')}</p>
                  <p className="text-sm text-theme-primary font-medium">{formatSalary()}</p>
                  {vacancy.salary_negotiable && (
                    <p className="text-xs text-success">{t('salary.negotiable')}</p>
                  )}
                </div>
              </div>
            )}

            {(vacancy.contact_email || vacancy.contact_phone) && (
              <>
                <Divider />
                <h3 className="text-sm font-semibold text-theme-primary">{t('detail.contact_label')}</h3>

                {vacancy.contact_email && (
                  <div className="flex items-center gap-3">
                    <Mail className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                    <a href={`mailto:${vacancy.contact_email}`} className="text-sm text-primary hover:underline">
                      {vacancy.contact_email}
                    </a>
                  </div>
                )}

                {vacancy.contact_phone && (
                  <div className="flex items-center gap-3">
                    <Phone className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                    <a href={`tel:${vacancy.contact_phone}`} className="text-sm text-primary hover:underline">
                      {vacancy.contact_phone}
                    </a>
                  </div>
                )}
              </>
            )}
          </GlassCard>
        </div>
      </div>

      {/* Apply Modal */}
      <Modal isOpen={applyModal.isOpen} onOpenChange={applyModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('apply.title')}</ModalHeader>
              <ModalBody>
                <Textarea
                  label={t('apply.message_label')}
                  placeholder={t('apply.message_placeholder')}
                  value={applyMessage}
                  onValueChange={setApplyMessage}
                  minRows={4}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('apply.cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={handleApply}
                  isLoading={isSubmitting}
                >
                  {t('apply.submit')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* J5: Qualification Modal */}
      <Modal isOpen={qualifiedModal.isOpen} onOpenChange={qualifiedModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                <div className="flex items-center gap-2">
                  <Target className="w-5 h-5 text-primary" aria-hidden="true" />
                  {t('qualified.title')}
                </div>
              </ModalHeader>
              <ModalBody>
                {isLoadingQualification ? (
                  <div className="space-y-4 animate-pulse">
                    <div className="h-4 bg-theme-hover rounded w-3/4" />
                    <div className="h-8 bg-theme-hover rounded" />
                    <div className="h-4 bg-theme-hover rounded w-1/2" />
                  </div>
                ) : qualification ? (
                  <div className="space-y-5">
                    {/* Score */}
                    <div className="text-center">
                      <div className="text-4xl font-bold text-theme-primary mb-1">
                        {qualification.percentage}%
                      </div>
                      <p className="text-sm text-theme-muted">
                        {t(`qualified.level_${qualification.level}`)}
                      </p>
                      <p className="text-sm text-theme-subtle mt-1">
                        {t('qualified.matched_count', {
                          matched: qualification.total_matched,
                          total: qualification.total_required,
                        })}
                      </p>
                    </div>

                    <Progress
                      value={qualification.percentage}
                      color={
                        qualification.percentage >= 80 ? 'success' :
                        qualification.percentage >= 60 ? 'primary' :
                        qualification.percentage >= 40 ? 'warning' : 'danger'
                      }
                      className="max-w-full"
                      aria-label="Qualification percentage"
                    />

                    {/* Breakdown */}
                    <div className="space-y-2">
                      {qualification.breakdown.map((item, idx) => (
                        <div
                          key={idx}
                          className={`flex items-center gap-3 p-3 rounded-lg ${
                            item.matched ? 'bg-success/5 border border-success/20' : 'bg-danger/5 border border-danger/20'
                          }`}
                        >
                          {item.matched ? (
                            <CheckCircle className="w-5 h-5 text-success flex-shrink-0" aria-hidden="true" />
                          ) : (
                            <XCircle className="w-5 h-5 text-danger flex-shrink-0" aria-hidden="true" />
                          )}
                          <span className={`text-sm ${item.matched ? 'text-success' : 'text-danger'}`}>
                            {item.skill}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : null}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('apply.cancel')}
                </Button>
                {qualification && qualification.percentage > 0 && !vacancy.has_applied && vacancy.status === 'open' && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={() => {
                      onClose();
                      applyModal.onOpen();
                    }}
                  >
                    {t('apply.button')}
                  </Button>
                )}
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* J7: Renewal Modal */}
      <Modal isOpen={renewModal.isOpen} onOpenChange={renewModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('renew.title')}</ModalHeader>
              <ModalBody>
                <p className="text-theme-muted mb-4">{t('renew.description')}</p>
                <div className="flex gap-2">
                  {[7, 14, 30, 60].map((d) => (
                    <Button
                      key={d}
                      size="sm"
                      variant={renewDays === d ? 'solid' : 'flat'}
                      color={renewDays === d ? 'primary' : 'default'}
                      onPress={() => setRenewDays(d)}
                    >
                      {d} {t('analytics.days')}
                    </Button>
                  ))}
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('apply.cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={handleRenew}
                  isLoading={isRenewing}
                >
                  {t('renew.button')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal isOpen={deleteModal.isOpen} onOpenChange={deleteModal.onOpenChange} size="sm">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('detail.confirm_delete_title')}</ModalHeader>
              <ModalBody>
                <p className="text-theme-muted">{t('detail.confirm_delete')}</p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('apply.cancel')}
                </Button>
                <Button color="danger" onPress={handleDelete}>
                  {t('detail.delete')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );

  function renderApplySection() {
    return (
      <GlassCard className="p-6">
        {!isAuthenticated ? (
          <div className="text-center">
            <p className="text-theme-muted mb-3">{t('apply.login_required')}</p>
            <Link to={tenantPath('/login')}>
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full">
                {t('apply.button')}
              </Button>
            </Link>
          </div>
        ) : isOwner ? (
          <div className="text-center">
            <Chip variant="flat" color="default" className="text-sm">
              {t('apply.own_vacancy')}
            </Chip>
          </div>
        ) : vacancy!.status !== 'open' ? (
          <div className="text-center">
            <Chip variant="flat" color="warning" className="text-sm">
              {t('apply.closed')}
            </Chip>
          </div>
        ) : vacancy!.has_applied ? (
          <div className="text-center space-y-2">
            <Chip
              variant="flat"
              color={STATUS_COLORS[vacancy!.application_stage ?? vacancy!.application_status ?? 'applied'] ?? 'default'}
              className="text-sm"
            >
              {t('apply.applied')} - {t(`application_status.${vacancy!.application_stage ?? vacancy!.application_status ?? 'applied'}`)}
            </Chip>
          </div>
        ) : (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full"
            size="lg"
            onPress={applyModal.onOpen}
          >
            {t('apply.button')}
          </Button>
        )}
      </GlassCard>
    );
  }
}

// J2: Match badge component
function MatchBadge({ match }: { match: MatchResult }) {
  const { t } = useTranslation('jobs');

  if (match.required_skills.length === 0) return null;

  const color = match.percentage >= 80 ? 'success' : match.percentage >= 60 ? 'primary' : match.percentage >= 40 ? 'warning' : 'danger';
  const label = match.percentage >= 80 ? t('match.excellent') : match.percentage >= 60 ? t('match.good') : match.percentage >= 40 ? t('match.moderate') : t('match.low');

  return (
    <div className="flex items-center gap-3">
      <Chip
        variant="flat"
        color={color}
        startContent={<Target className="w-3.5 h-3.5" aria-hidden="true" />}
        className="text-sm"
      >
        {match.percentage}% {t('match.title')}
      </Chip>
      <span className="text-xs text-theme-subtle">{label}</span>
    </div>
  );
}

// J3: Application card with pipeline stage buttons
interface ApplicationCardProps {
  application: Application;
  onUpdateStatus: (applicationId: number, status: string) => void;
}

function ApplicationCard({ application, onUpdateStatus }: ApplicationCardProps) {
  const { t } = useTranslation('jobs');
  const [showHistory, setShowHistory] = useState(false);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(false);

  const currentStage = application.stage ?? application.status;

  // J4: Load history
  const handleToggleHistory = async () => {
    if (!showHistory && history.length === 0) {
      setIsLoadingHistory(true);
      try {
        const response = await api.get<HistoryEntry[]>(`/v2/jobs/applications/${application.id}/history`);
        if (response.success && response.data) {
          setHistory(response.data);
        }
      } catch {
        // Non-critical
      } finally {
        setIsLoadingHistory(false);
      }
    }
    setShowHistory(!showHistory);
  };

  // Determine available next stages based on current
  const getAvailableStages = () => {
    const stages: string[] = [];
    switch (currentStage) {
      case 'applied':
        stages.push('screening', 'interview', 'accepted', 'rejected');
        break;
      case 'screening':
        stages.push('interview', 'accepted', 'rejected');
        break;
      case 'interview':
        stages.push('offer', 'accepted', 'rejected');
        break;
      case 'offer':
        stages.push('accepted', 'rejected');
        break;
      case 'reviewed':
        stages.push('screening', 'interview', 'accepted', 'rejected');
        break;
      default:
        break;
    }
    return stages;
  };

  const availableStages = getAvailableStages();

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="p-4 rounded-lg bg-theme-elevated border border-theme-default"
    >
      <div className="flex items-start gap-3">
        <Avatar
          name={application.applicant.name}
          src={resolveAvatarUrl(application.applicant.avatar_url)}
          size="sm"
          isBordered
        />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <p className="font-medium text-theme-primary">{application.applicant.name}</p>
            <Chip
              size="sm"
              variant="flat"
              color={STATUS_COLORS[currentStage] ?? 'default'}
            >
              {t(`application_status.${currentStage}`)}
            </Chip>
          </div>
          {application.applicant.email && (
            <p className="text-xs text-theme-subtle">{application.applicant.email}</p>
          )}
          <p className="text-xs text-theme-subtle mt-1">
            {new Date(application.created_at).toLocaleDateString()}
          </p>
          {application.message && (
            <p className="text-sm text-theme-secondary mt-2 whitespace-pre-wrap">
              {application.message}
            </p>
          )}
          {application.reviewer_notes && (
            <div className="mt-2 p-2 rounded bg-theme-hover text-sm">
              <span className="font-medium text-theme-subtle">{t('detail.reviewer_notes')}: </span>
              <span className="text-theme-secondary">{application.reviewer_notes}</span>
            </div>
          )}
        </div>
      </div>

      {/* J3: Pipeline stage action buttons */}
      {availableStages.length > 0 && (
        <div className="flex gap-2 mt-3 flex-wrap justify-end">
          {availableStages.map((stage) => (
            <Button
              key={stage}
              size="sm"
              variant="flat"
              color={STATUS_COLORS[stage] ?? 'default'}
              startContent={
                stage === 'accepted' ? <CheckCircle className="w-3.5 h-3.5" aria-hidden="true" /> :
                stage === 'rejected' ? <XCircle className="w-3.5 h-3.5" aria-hidden="true" /> :
                <ChevronRight className="w-3.5 h-3.5" aria-hidden="true" />
              }
              onPress={() => onUpdateStatus(application.id, stage)}
            >
              {t(`application_status.${stage}`)}
            </Button>
          ))}
        </div>
      )}

      {/* J4: History toggle */}
      <div className="mt-2">
        <Button
          variant="light"
          size="sm"
          onPress={handleToggleHistory}
          className="text-xs text-theme-subtle hover:text-theme-primary flex items-center gap-1 transition-colors h-auto p-0 min-w-0"
          startContent={<History className="w-3 h-3" aria-hidden="true" />}
        >
          {t('history.title')}
        </Button>
        {showHistory && (
          <div className="mt-2 pl-4 border-l-2 border-theme-default space-y-2">
            {isLoadingHistory ? (
              <div className="h-4 bg-theme-hover rounded w-3/4 animate-pulse" />
            ) : history.length === 0 ? (
              <p className="text-xs text-theme-subtle">{t('history.empty')}</p>
            ) : (
              history.map((entry) => (
                <div key={entry.id} className="text-xs text-theme-subtle">
                  <span className="text-theme-muted">
                    {entry.from_status
                      ? `${t(`application_status.${entry.from_status}`)} → ${t(`application_status.${entry.to_status}`)}`
                      : t('history.initial')}
                  </span>
                  {entry.changed_by_name && (
                    <span> - {entry.changed_by_name}</span>
                  )}
                  <span className="ml-2">{new Date(entry.changed_at).toLocaleString()}</span>
                  {entry.notes && (
                    <p className="text-theme-subtle mt-0.5 italic">{entry.notes}</p>
                  )}
                </div>
              ))
            )}
          </div>
        )}
      </div>
    </motion.div>
  );
}

export default JobDetailPage;
