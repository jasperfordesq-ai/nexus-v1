// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Job Detail Page - Full vacancy detail with apply functionality
 *
 * Features:
 * - Full description, skills, contact info
 * - Apply modal with message textarea
 * - Application status tracking
 * - Owner: applications list with accept/reject
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
}

interface Application {
  id: number;
  vacancy_id: number;
  user_id: number;
  message: string | null;
  status: 'pending' | 'reviewed' | 'accepted' | 'rejected' | 'withdrawn';
  reviewer_notes: string | null;
  created_at: string;
  applicant: {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string | null;
  };
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

const STATUS_COLORS: Record<string, 'warning' | 'primary' | 'success' | 'danger' | 'default'> = {
  pending: 'warning',
  reviewed: 'primary',
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

  const [vacancy, setVacancy] = useState<JobVacancy | null>(null);
  const [applications, setApplications] = useState<Application[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [applyMessage, setApplyMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showApplications, setShowApplications] = useState(false);
  const [isLoadingApps, setIsLoadingApps] = useState(false);

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
    if (!id || !confirm(t('detail.confirm_delete'))) return;
    try {
      const response = await api.delete(`/v2/jobs/${id}`);
      if (response.success) {
        toast.success(t('detail.deleted'));
        navigate(tenantPath('/jobs'));
      } else {
        toast.error(t('detail.delete_error'));
      }
    } catch (err) {
      logError('Failed to delete vacancy', err);
      toast.error(t('detail.delete_error'));
    }
  };

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
          {isOwner && (
            <div className="flex gap-2">
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
                onPress={handleDelete}
              >
                {t('detail.delete')}
              </Button>
            </div>
          )}
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
        </div>
      </GlassCard>

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
              <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('detail.skills_required')}</h2>
              <div className="flex flex-wrap gap-2">
                {vacancy.skills.map((skill, idx) => (
                  <Chip
                    key={idx}
                    variant="flat"
                    color="primary"
                    className="bg-primary/10 text-primary"
                  >
                    {skill}
                  </Chip>
                ))}
              </div>
            </GlassCard>
          )}

          {/* Apply button (mobile) */}
          <div className="lg:hidden">
            {renderApplySection()}
          </div>

          {/* Applications (owner view) */}
          {isOwner && (
            <GlassCard className="p-6">
              <button
                onClick={() => setShowApplications(!showApplications)}
                className="flex items-center gap-2 w-full text-left"
              >
                <Users className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
                <h2 className="text-lg font-semibold text-theme-primary">
                  {t('detail.applications_tab')} ({vacancy.applications_count})
                </h2>
              </button>

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
                    <div className="text-center py-8">
                      <FileText className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                      <p className="text-theme-muted">{t('detail.no_applications')}</p>
                      <p className="text-sm text-theme-subtle mt-1">{t('detail.no_applications_desc')}</p>
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
                {t('apply.login_required')}
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
              color={STATUS_COLORS[vacancy!.application_status ?? 'pending'] ?? 'default'}
              className="text-sm"
            >
              {t('apply.applied')} - {t(`application_status.${vacancy!.application_status ?? 'pending'}`)}
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

interface ApplicationCardProps {
  application: Application;
  onUpdateStatus: (applicationId: number, status: string) => void;
}

function ApplicationCard({ application, onUpdateStatus }: ApplicationCardProps) {
  const { t } = useTranslation('jobs');

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
              color={STATUS_COLORS[application.status] ?? 'default'}
            >
              {t(`application_status.${application.status}`)}
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

      {application.status === 'pending' && (
        <div className="flex gap-2 mt-3 justify-end">
          <Button
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => onUpdateStatus(application.id, 'reviewed')}
          >
            {t('detail.review')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="success"
            startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={() => onUpdateStatus(application.id, 'accepted')}
          >
            {t('detail.accept')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={() => onUpdateStatus(application.id, 'rejected')}
          >
            {t('detail.reject')}
          </Button>
        </div>
      )}

      {application.status === 'reviewed' && (
        <div className="flex gap-2 mt-3 justify-end">
          <Button
            size="sm"
            variant="flat"
            color="success"
            startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={() => onUpdateStatus(application.id, 'accepted')}
          >
            {t('detail.accept')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={() => onUpdateStatus(application.id, 'rejected')}
          >
            {t('detail.reject')}
          </Button>
        </div>
      )}
    </motion.div>
  );
}

export default JobDetailPage;
