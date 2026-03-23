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

import { useState, useEffect, useCallback, useRef } from 'react';
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
  Select,
  SelectItem,
  Input,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
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
  ChevronUp,
  ChevronDown,
  History,
  Check,
  X,
  Upload,
  FileText as FileTextIcon,
  MessageCircle,
  Sparkles,
  Building2,
  Share2,
  TrendingUp,
  Zap,
  EyeOff,
  CalendarClock,
  Globe,
  Copy,
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
  tagline: string | null;
  video_url: string | null;
  benefits: string[] | null;
  company_size: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_type: string | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
  expired_at: string | null;
  renewed_at: string | null;
  renewal_count: number;
  blind_hiring: boolean;
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

interface QualificationData {
  percentage: number;
  level: 'low' | 'moderate' | 'good' | 'excellent';
  ai_summary: string;
  matched_skills: string[];
  missing_skills: string[];
  dimensions: { label: string; score: number; detail: string }[];
}

interface HistoryEntry {
  id: number;
  from_status: string | null;
  to_status: string;
  changed_by_name: string;
  changed_at: string;
  notes: string | null;
}

interface InlineInterview {
  id: number;
  application_id: number;
  scheduled_at: string;
  interview_type: 'video' | 'phone' | 'in_person';
  status: 'proposed' | 'accepted' | 'declined';
  location_notes?: string | null;
  duration_mins?: number;
}

interface InlineOffer {
  id: number;
  application_id: number;
  salary_offered: string | null;
  salary_currency: string;
  salary_type: 'hourly' | 'monthly' | 'annual';
  start_date: string | null;
  message: string | null;
  status: 'pending' | 'accepted' | 'rejected';
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

// ---------------------------------------------------------------------------
// JSON-LD helper
// ---------------------------------------------------------------------------

function buildJobPostingSchema(vacancy: JobVacancy, tenantPath: (p: string) => string): string {
  const base = window.location.origin;
  const schema: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'JobPosting',
    'title': vacancy.title,
    'description': vacancy.description ?? '',
    'datePosted': vacancy.created_at ?? new Date().toISOString(),
    'validThrough': vacancy.deadline ?? undefined,
    'employmentType': vacancy.commitment === 'full_time' ? 'FULL_TIME'
                    : vacancy.commitment === 'part_time' ? 'PART_TIME'
                    : vacancy.commitment === 'one_off' ? 'CONTRACTOR'
                    : 'OTHER',
    'jobLocationType': vacancy.is_remote ? 'TELECOMMUTE' : undefined,
    'url': base + tenantPath(`/jobs/${vacancy.id}`),
  };

  if (vacancy.location && !vacancy.is_remote) {
    schema['jobLocation'] = {
      '@type': 'Place',
      'address': { '@type': 'PostalAddress', 'addressLocality': vacancy.location },
    };
  }

  if (vacancy.salary_min || vacancy.salary_max) {
    schema['baseSalary'] = {
      '@type': 'MonetaryAmount',
      'currency': vacancy.salary_currency ?? 'EUR',
      'value': {
        '@type': 'QuantitativeValue',
        'minValue': vacancy.salary_min ?? undefined,
        'maxValue': vacancy.salary_max ?? undefined,
        'unitText': vacancy.salary_type?.toUpperCase() ?? 'YEAR',
      },
    };
  }

  if (vacancy.skills_required) {
    schema['skills'] = vacancy.skills_required;
  }

  return JSON.stringify(schema);
}

// ---------------------------------------------------------------------------
// Pipeline rule interface
// ---------------------------------------------------------------------------

interface PipelineRule {
  id: number;
  name: string;
  trigger_stage: string;
  condition_days: number;
  action: string;
  action_target: string | null;
  is_active: boolean;
  last_run_at: string | null;
}

function parseArrayResponse<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data;
  if (data && typeof data === 'object' && 'data' in data) return (data as { data: T[] }).data ?? [];
  return [];
}

export function JobDetailPage() {
  const { t } = useTranslation('jobs');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const applyModal = useDisclosure({
    onOpen: async () => {
      // Feature 5: Fetch saved profile on modal open
      try {
        const res = await api.get<{cv_filename?: string; cover_text?: string}>('/v2/jobs/saved-profile');
        if (res.success && res.data) {
          setSavedProfile(res.data);
        } else {
          setSavedProfile(null);
        }
      } catch {
        setSavedProfile(null);
      }
      setUsingSavedProfile(false);
    },
  });
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

  // CV Upload state
  const [cvFile, setCvFile] = useState<File | null>(null);
  const cvInputRef = useRef<HTMLInputElement>(null);

  // Feature 5: CV parsing state
  const [cvParsed, setCvParsed] = useState<{ skills: string[]; summary?: string } | null>(null);

  // J1: Saved state
  const [isSaved, setIsSaved] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // J2: Match percentage
  const [matchResult, setMatchResult] = useState<MatchResult | null>(null);

  // J5: Qualification assessment
  const [qualification, setQualification] = useState<QualificationResult | null>(null);
  const [isLoadingQualification, setIsLoadingQualification] = useState(false);

  // Match explanation card (inline, auto-loaded)
  const [qualificationData, setQualificationData] = useState<QualificationData | null>(null);
  const [qualOpen, setQualOpen] = useState(false);

  // J7: Renewal
  const [renewDays, setRenewDays] = useState(30);
  const [isRenewing, setIsRenewing] = useState(false);


  // Feature 4: Pipeline rules
  const [pipelineRules, setPipelineRules] = useState<PipelineRule[]>([]);
  const [pipelineOpen, setPipelineOpen] = useState(false);
  const [newRule, setNewRule] = useState({ name: '', trigger_stage: 'applied', condition_days: 7, action: 'move_stage', action_target: 'screening' });
  const [isAddingRule, setIsAddingRule] = useState(false);

  // Feature 5: Saved profile (one-click apply)
  const [savedProfile, setSavedProfile] = useState<{cv_filename?: string; cover_text?: string} | null>(null);
  const [usingSavedProfile, setUsingSavedProfile] = useState(false);

  // Feature 2: Salary benchmark for owners
  const [benchmark, setBenchmark] = useState<{
    role_keyword: string;
    salary_min: number;
    salary_max: number;
    salary_median: number;
    salary_type: string;
    currency: string;
  } | null>(null);

  // Similar jobs
  const [similarJobs, setSimilarJobs] = useState<JobVacancy[]>([]);

  // Inline interview/offer response (GAP 1)
  const [pendingInterview, setPendingInterview] = useState<InlineInterview | null>(null);
  const [pendingOffer, setPendingOffer] = useState<InlineOffer | null>(null);
  const [isRespondingInterview, setIsRespondingInterview] = useState(false);
  const [isRespondingOffer, setIsRespondingOffer] = useState(false);
  const [showDeclineInterviewModal, setShowDeclineInterviewModal] = useState(false);
  const [showDeclineOfferModal, setShowDeclineOfferModal] = useState(false);
  const [declineNotes, setDeclineNotes] = useState('');

  usePageTitle(vacancy?.title ?? t('detail.loading'));

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const isOwner = vacancy && user && vacancy.user_id === user.id;

  const loadVacancy = useCallback(async () => {
    if (!id) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<JobVacancy>(`/v2/jobs/${id}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setVacancy(response.data);
        setIsSaved(response.data.is_saved ?? false);
      } else {
        setError(tRef.current('detail.not_found'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load job vacancy', err);
      setError(tRef.current('detail.unable_to_load'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

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

  // Load qualification data for the inline "Why You Match" card
  useEffect(() => {
    if (!id || !isAuthenticated || !vacancy || isOwner) return;
    const loadQualData = async () => {
      try {
        const qualRes = await api.get<QualificationData>(`/v2/jobs/${id}/qualified`);
        if (qualRes.success && qualRes.data) setQualificationData(qualRes.data as QualificationData);
      } catch {
        // Non-critical
      }
    };
    loadQualData();
  }, [id, isAuthenticated, vacancy, isOwner]);

  // Feature 2: Load salary benchmark for owners
  useEffect(() => {
    if (!vacancy || !isOwner) return;
    const controller = new AbortController();
    api.get<{ role_keyword: string; salary_min: number; salary_max: number; salary_median: number; salary_type: string; currency: string }>(`/v2/jobs/salary-benchmark?title=${encodeURIComponent(vacancy.title)}`)
      .then((res) => {
        if (!controller.signal.aborted && res.success && res.data) {
          setBenchmark(res.data);
        }
      })
      .catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); });
    return () => { controller.abort(); };
  }, [vacancy, isOwner]);

  // Load similar jobs by category
  useEffect(() => {
    if (!vacancy?.category || !vacancy?.id) return;
    const controller = new AbortController();
    api.get<{ data: JobVacancy[] } | JobVacancy[]>(`/v2/jobs?category=${encodeURIComponent(vacancy.category)}&limit=4&exclude=${vacancy.id}`)
      .then((res) => {
        if (controller.signal.aborted || !res.success || !res.data) return;
        const items = parseArrayResponse<JobVacancy>(res.data);
        setSimilarJobs(items.filter((j) => j.id !== vacancy.id).slice(0, 4));
      })
      .catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); });
    return () => { controller.abort(); };
  }, [vacancy?.category, vacancy?.id]);

  // GAP 1: Load pending interview/offer for current user's application
  useEffect(() => {
    if (!vacancy || !vacancy.has_applied || isOwner || !isAuthenticated) return;
    const stage = vacancy.application_stage ?? vacancy.application_status;
    if (stage !== 'interview' && stage !== 'offer') return;

    const controller = new AbortController();
    const load = async () => {
      try {
        const [interviewsRes, offersRes] = await Promise.all([
          api.get<{ data: InlineInterview[] } | InlineInterview[]>('/v2/jobs/my-interviews'),
          api.get<{ data: InlineOffer[] } | InlineOffer[]>('/v2/jobs/my-offers'),
        ]);
        if (controller.signal.aborted) return;

        // Parse interviews
        const interviews: InlineInterview[] = interviewsRes.success && interviewsRes.data
          ? parseArrayResponse<InlineInterview>(interviewsRes.data)
          : [];

        // Parse offers
        const offers: InlineOffer[] = offersRes.success && offersRes.data
          ? parseArrayResponse<InlineOffer>(offersRes.data)
          : [];

        // Find pending interview/offer for THIS job's application
        const myInterview = interviews.find(
          (iv) => iv.status === 'proposed'
        );
        const myOffer = offers.find(
          (of_) => of_.status === 'pending'
        );

        if (myInterview) setPendingInterview(myInterview);
        if (myOffer) setPendingOffer(myOffer);
      } catch {
        // Non-critical — silent failure
      }
    };
    load();
    return () => { controller.abort(); };
  }, [vacancy, isOwner, isAuthenticated]);

  // GAP 1: Interview accept/decline handlers
  const handleAcceptInterview = async () => {
    if (!pendingInterview) return;
    setIsRespondingInterview(true);
    try {
      await api.put(`/v2/jobs/interviews/${pendingInterview.id}/accept`, {});
      toastRef.current.success(tRef.current('inline_response.interview_accepted', 'Interview accepted'));
      setPendingInterview({ ...pendingInterview, status: 'accepted' });
      loadVacancy();
    } catch (err) {
      logError('Failed to accept interview', err);
      toastRef.current.error(tRef.current('something_wrong'));
    } finally {
      setIsRespondingInterview(false);
    }
  };

  const handleDeclineInterview = async () => {
    if (!pendingInterview) return;
    setIsRespondingInterview(true);
    try {
      await api.put(`/v2/jobs/interviews/${pendingInterview.id}/decline`, { notes: declineNotes || undefined });
      toastRef.current.success(tRef.current('inline_response.interview_declined', 'Interview declined'));
      setPendingInterview({ ...pendingInterview, status: 'declined' });
      setShowDeclineInterviewModal(false);
      setDeclineNotes('');
      loadVacancy();
    } catch (err) {
      logError('Failed to decline interview', err);
      toastRef.current.error(tRef.current('something_wrong'));
    } finally {
      setIsRespondingInterview(false);
    }
  };

  // GAP 1: Offer accept/reject handlers
  const handleAcceptOffer = async () => {
    if (!pendingOffer) return;
    setIsRespondingOffer(true);
    try {
      await api.put(`/v2/jobs/offers/${pendingOffer.id}/accept`, {});
      toastRef.current.success(tRef.current('inline_response.offer_accepted', 'Offer accepted'));
      setPendingOffer({ ...pendingOffer, status: 'accepted' });
      loadVacancy();
    } catch (err) {
      logError('Failed to accept offer', err);
      toastRef.current.error(tRef.current('something_wrong'));
    } finally {
      setIsRespondingOffer(false);
    }
  };

  const handleDeclineOffer = async () => {
    if (!pendingOffer) return;
    setIsRespondingOffer(true);
    try {
      await api.put(`/v2/jobs/offers/${pendingOffer.id}/reject`, { reason: declineNotes || undefined });
      toastRef.current.success(tRef.current('inline_response.offer_declined', 'Offer declined'));
      setPendingOffer({ ...pendingOffer, status: 'rejected' });
      setShowDeclineOfferModal(false);
      setDeclineNotes('');
      loadVacancy();
    } catch (err) {
      logError('Failed to decline offer', err);
      toastRef.current.error(tRef.current('something_wrong'));
    } finally {
      setIsRespondingOffer(false);
    }
  };

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
      let response;
      if (cvFile) {
        // Use FormData for multipart upload
        const formData = new FormData();
        formData.append('message', applyMessage || '');
        formData.append('cv', cvFile);
        // Fetch directly for multipart — api client uses JSON by default
        const token = localStorage.getItem('nexus_access_token');
        const tenantId = localStorage.getItem('nexus_tenant_id');
        const apiBase = import.meta.env.VITE_API_BASE || '/api';
        const fetchResponse = await fetch(`${apiBase}/v2/jobs/${id}/apply`, {
          method: 'POST',
          headers: {
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(tenantId ? { 'X-Tenant-ID': tenantId } : {}),
          },
          body: formData,
        });
        if (!fetchResponse.ok) {
          throw new Error(fetchResponse.statusText || 'Application failed');
        }
        response = await fetchResponse.json() as { success: boolean; error?: string };
      } else {
        response = await api.post(`/v2/jobs/${id}/apply`, {
          message: applyMessage || null,
        });
      }
      if (response.success) {
        const appId = (response as { data?: { id?: number } }).data?.id;
        // Feature 5: Auto-parse CV if uploaded and we have an application ID
        if (cvFile && appId) {
          try {
            const parseRes = await api.get<{ skills?: string[]; summary?: string }>(`/v2/jobs/applications/${appId}/parse-cv`);
            if (parseRes.success && parseRes.data) {
              const parsed = parseRes.data;
              setCvParsed({ skills: parsed.skills ?? [], summary: parsed.summary });
              const count = parsed.skills?.length ?? 0;
              if (count > 0) {
                toastRef.current.success(
                  tRef.current('cv.parsed_toast', 'Application submitted! {{count}} skills detected from your CV.', { count })
                );
              } else {
                toastRef.current.success(tRef.current('apply.success'));
              }
            } else {
              toastRef.current.success(tRef.current('apply.success'));
            }
          } catch (err) {
            toastRef.current.error(tRef.current('apply.cv_parse_error', 'Failed to parse CV. You can still submit your application manually.'));
            if (import.meta.env.DEV) console.warn('CV parse failed:', err);
          }
        } else {
          toastRef.current.success(tRef.current('apply.success'));
        }
        applyModal.onClose();
        // Feature 5: Silently update saved cover letter (best-effort, no await blocking)
        if (applyMessage.trim()) {
          api.put('/v2/jobs/saved-profile', { headline: '', cover_text: applyMessage.trim() }).catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); });
        }
        setApplyMessage('');
        setCvFile(null);
        setSavedProfile(null);
        setUsingSavedProfile(false);
        loadVacancy();
      } else {
        toastRef.current.error((response as { error?: string }).error || tRef.current('apply.error'));
      }
    } catch (err) {
      logError('Failed to apply for job', err);
      toastRef.current.error(tRef.current('apply.error'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCvDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file && ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(file.type)) {
      if (file.size <= 5 * 1024 * 1024) {
        setCvFile(file);
      } else {
        toastRef.current.error(tRef.current('apply.cv_too_large', 'CV file must be under 5MB'));
      }
    } else {
      toastRef.current.error(tRef.current('apply.cv_invalid_type', 'Only PDF, DOC, or DOCX files are supported'));
    }
  };

  const handleDelete = async () => {
    if (!id) return;
    try {
      const response = await api.delete(`/v2/jobs/${id}`);
      if (response.success) {
        toastRef.current.success(tRef.current('detail.deleted'));
        deleteModal.onClose();
        navigate(tenantPath('/jobs'));
      } else {
        toastRef.current.error(tRef.current('detail.delete_error'));
      }
    } catch (err) {
      logError('Failed to delete vacancy', err);
      toastRef.current.error(tRef.current('detail.delete_error'));
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
          toastRef.current.success(tRef.current('saved.unsave_success'));
        }
      } else {
        const response = await api.post(`/v2/jobs/${id}/save`, {});
        if (response.success) {
          setIsSaved(true);
          toastRef.current.success(tRef.current('saved.save_success'));
        }
      }
    } catch (err) {
      logError('Failed to toggle save', err);
      toastRef.current.error(tRef.current('saved.save_error'));
    } finally {
      setIsSaving(false);
    }
  };

  // J3: Update application status (now supports pipeline stages)
  const handleUpdateAppStatus = async (applicationId: number, status: string) => {
    try {
      const response = await api.put(`/v2/jobs/applications/${applicationId}`, { status });
      if (response.success) {
        toastRef.current.success(tRef.current('detail.status_updated'));
        loadApplications();
      } else {
        toastRef.current.error(tRef.current('detail.status_update_error'));
      }
    } catch (err) {
      logError('Failed to update application status', err);
      toastRef.current.error(tRef.current('detail.status_update_error'));
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
        toastRef.current.success(tRef.current('renew.success'));
        renewModal.onClose();
        loadVacancy();
      } else {
        toastRef.current.error(tRef.current('renew.error'));
      }
    } catch (err) {
      logError('Failed to renew job', err);
      toastRef.current.error(tRef.current('renew.error'));
    } finally {
      setIsRenewing(false);
    }
  };

  // Feature 4: Load pipeline rules
  const loadPipelineRules = useCallback(async () => {
    if (!id) return;
    try {
      const res = await api.get<{ data: PipelineRule[] }>(`/v2/jobs/${id}/pipeline-rules`);
      if (res.success && res.data) setPipelineRules((res.data as { data: PipelineRule[] }).data ?? []);
    } catch (err) {
      logError('Failed to load pipeline rules', err);
    }
  }, [id]);

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

  // J9: Format salary display (EU Pay Transparency — clear range)
  const formatSalary = () => {
    if (vacancy.salary_negotiable && !vacancy.salary_min && !vacancy.salary_max) return null;
    if (!vacancy.salary_min && !vacancy.salary_max) return null;
    const currency = vacancy.salary_currency || '';
    const typeLabel = vacancy.salary_type ? ` / ${t(`salary.${vacancy.salary_type}`)}` : '';
    if (vacancy.salary_min && vacancy.salary_max) {
      // Use en-dash for range — clear EU Pay Transparency format
      return `${currency}${vacancy.salary_min.toLocaleString()} \u2013 ${currency}${vacancy.salary_max.toLocaleString()}${typeLabel}`;
    }
    if (vacancy.salary_min) return `${t('salary.min_only', { min: `${currency}${vacancy.salary_min.toLocaleString()}` })}${typeLabel}`;
    if (vacancy.salary_max) return `${t('salary.max_only', { max: `${currency}${vacancy.salary_max.toLocaleString()}` })}${typeLabel}`;
    return null;
  };

  return (
    <main className="space-y-6">
      {/* JSON-LD structured data */}
      {vacancy && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: buildJobPostingSchema(vacancy, tenantPath) }}
        />
      )}

      {/* Back nav */}
      <Link to={tenantPath('/jobs')} className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors">
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {t('back_to_jobs')}
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
              {vacancy.blind_hiring && (
                <Chip size="sm" variant="flat" color="secondary" startContent={<EyeOff className="w-3 h-3" />}>
                  {t('blind_hiring.enabled_badge')}
                </Chip>
              )}
            </div>

            {/* Blind Hiring Info Banner (Agent C) */}
            {vacancy.blind_hiring && isOwner && (
              <div className="mt-3 flex items-center gap-2 rounded-lg bg-violet-500/10 border border-violet-500/20 p-3">
                <EyeOff className="w-4 h-4 text-violet-400 flex-shrink-0" aria-hidden="true" />
                <p className="text-sm text-violet-600 dark:text-violet-400">{t('blind_hiring.info_banner')}</p>
              </div>
            )}

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

            {/* Share dropdown */}
            <Dropdown>
              <DropdownTrigger>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<Share2 size={14} aria-hidden="true" />}
                  aria-label={t('share.title')}
                >
                  {t('share.title')}
                </Button>
              </DropdownTrigger>
              <DropdownMenu aria-label={t('share.title')}>
                <DropdownItem
                  key="copy"
                  startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
                  onPress={async () => {
                    const jobUrl = window.location.origin + tenantPath(`/jobs/${vacancy.id}`);
                    await navigator.clipboard.writeText(jobUrl);
                    toastRef.current.success(tRef.current('share.copied'));
                  }}
                >
                  {t('share.copy_link')}
                </DropdownItem>
                <DropdownItem
                  key="email"
                  startContent={<Mail className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => {
                    const jobUrl = window.location.origin + tenantPath(`/jobs/${vacancy.id}`);
                    const subject = encodeURIComponent(vacancy.title);
                    const body = encodeURIComponent(`Check out this job: ${vacancy.title}\n\n${jobUrl}`);
                    window.open(`mailto:?subject=${subject}&body=${body}`, '_self');
                  }}
                >
                  {t('share.email')}
                </DropdownItem>
              </DropdownMenu>
            </Dropdown>

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
              <Globe className="w-4 h-4" aria-hidden="true" />
              {t('remote')}
            </span>
          ) : vacancy.location ? (
            <span className="flex items-center gap-1">
              <MapPin className="w-4 h-4" aria-hidden="true" />
              {vacancy.location}
            </span>
          ) : (
            <span className="flex items-center gap-1 text-theme-muted">
              <MapPin className="w-4 h-4" aria-hidden="true" />
              {t('location_not_specified')}
            </span>
          )}

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

          {/* J9: Salary display — EU Pay Transparency */}
          {formatSalary() ? (
            <span className="flex items-center gap-1 font-medium text-theme-primary">
              <DollarSign className="w-4 h-4" aria-hidden="true" />
              {formatSalary()}
              {vacancy.salary_negotiable && (
                <span className="text-xs text-theme-subtle font-normal">({t('salary.negotiable')})</span>
              )}
            </span>
          ) : vacancy.salary_negotiable ? (
            <span className="flex items-center gap-1 text-theme-subtle">
              <DollarSign className="w-4 h-4" aria-hidden="true" />
              <span className="text-xs">{t('salary.negotiable')}</span>
            </span>
          ) : (
            <span className="flex items-center gap-1 text-theme-muted">
              <DollarSign className="w-4 h-4" aria-hidden="true" />
              <span className="text-xs">{t('salary_not_specified')}</span>
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
              <Link to={tenantPath(`/jobs/${vacancy.id}/kanban`)}>
                <Button size="sm" variant="flat" color="primary" startContent={<Users className="w-4 h-4" aria-hidden="true" />}>
                  {t('detail.kanban_board', 'Kanban Board')}
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                color="secondary"
                startContent={<CalendarClock className="w-4 h-4" aria-hidden="true" />}
                onPress={() => {
                  const el = document.getElementById('interview-slots-section');
                  if (el) el.scrollIntoView({ behavior: 'smooth' });
                }}
              >
                {t('self_scheduling.manage_slots', 'Interview Slots')}
              </Button>
              {vacancy.status === 'open' && (
                <Button size="sm" color="warning" variant="flat" onPress={async () => {
                  try {
                    const res = await api.put(`/v2/jobs/${vacancy.id}`, { status: 'closed' });
                    if (res.success) {
                      toastRef.current.success(tRef.current('vacancy_closed', 'Vacancy closed'));
                      loadVacancy();
                    } else {
                      toastRef.current.error(tRef.current('detail.status_update_error'));
                    }
                  } catch (err) {
                    logError('Failed to close vacancy', err);
                    toastRef.current.error(tRef.current('detail.status_update_error'));
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
                      toastRef.current.success(tRef.current('vacancy_reopened', 'Vacancy reopened'));
                      loadVacancy();
                    } else {
                      toastRef.current.error(tRef.current('detail.status_update_error'));
                    }
                  } catch (err) {
                    logError('Failed to reopen vacancy', err);
                    toastRef.current.error(tRef.current('detail.status_update_error'));
                  }
                }}>
                  {t('detail.reopen_vacancy', 'Reopen')}
                </Button>
              )}
            </div>
          </div>
        </GlassCard>
      )}

      {/* GAP 1: Inline Interview Response Card */}
      {!isOwner && vacancy.has_applied && pendingInterview && pendingInterview.status === 'proposed' && (
        <GlassCard className="p-5 border-l-4 border-l-secondary bg-secondary/5">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-lg bg-secondary/20 flex items-center justify-center flex-shrink-0">
              <Calendar className="w-5 h-5 text-secondary" aria-hidden="true" />
            </div>
            <div className="flex-1 space-y-3">
              <div>
                <h3 className="text-base font-semibold text-theme-primary">
                  {t('inline_response.interview_pending', 'You have an interview scheduled')}
                </h3>
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-theme-secondary">
                  <span className="flex items-center gap-1">
                    <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                    {new Date(pendingInterview.scheduled_at).toLocaleString()}
                  </span>
                  <Chip size="sm" variant="flat" color="secondary">
                    {t(`interview.type_${pendingInterview.interview_type}`, pendingInterview.interview_type)}
                  </Chip>
                  {pendingInterview.duration_mins && (
                    <span className="flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5" aria-hidden="true" />
                      {pendingInterview.duration_mins} min
                    </span>
                  )}
                </div>
                {pendingInterview.location_notes && (
                  <p className="text-sm text-theme-muted mt-1">
                    {pendingInterview.interview_type === 'video' ? (
                      <a
                        href={pendingInterview.location_notes}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-primary hover:underline"
                      >
                        {pendingInterview.location_notes}
                      </a>
                    ) : (
                      <span className="flex items-center gap-1">
                        <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
                        {pendingInterview.location_notes}
                      </span>
                    )}
                  </p>
                )}
              </div>
              <div className="flex gap-2">
                <Button
                  color="success"
                  size="sm"
                  isLoading={isRespondingInterview}
                  onPress={handleAcceptInterview}
                  startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('inline_response.interview_accept', 'Accept Interview')}
                </Button>
                <Button
                  color="danger"
                  variant="flat"
                  size="sm"
                  isDisabled={isRespondingInterview}
                  onPress={() => { setDeclineNotes(''); setShowDeclineInterviewModal(true); }}
                  startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('inline_response.interview_decline', 'Decline Interview')}
                </Button>
              </div>
            </div>
          </div>
        </GlassCard>
      )}

      {/* GAP 1: Inline Offer Response Card */}
      {!isOwner && vacancy.has_applied && pendingOffer && pendingOffer.status === 'pending' && (
        <GlassCard className="p-5 border-l-4 border-l-success bg-success/5">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-lg bg-success/20 flex items-center justify-center flex-shrink-0">
              <DollarSign className="w-5 h-5 text-success" aria-hidden="true" />
            </div>
            <div className="flex-1 space-y-3">
              <div>
                <h3 className="text-base font-semibold text-theme-primary">
                  {t('inline_response.offer_pending', 'You have received an offer!')}
                </h3>
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-theme-secondary">
                  {pendingOffer.salary_offered && (
                    <span className="flex items-center gap-1 font-medium">
                      <DollarSign className="w-3.5 h-3.5" aria-hidden="true" />
                      {t('inline_response.offer_salary', 'Salary Offered')}: {pendingOffer.salary_currency}{pendingOffer.salary_offered}
                      {pendingOffer.salary_type && ` / ${t(`salary.${pendingOffer.salary_type}`)}`}
                    </span>
                  )}
                  {pendingOffer.start_date && (
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                      {t('inline_response.offer_start_date', 'Start Date')}: {new Date(pendingOffer.start_date).toLocaleDateString()}
                    </span>
                  )}
                </div>
                {pendingOffer.message && (
                  <p className="text-sm text-theme-muted mt-2 italic">
                    &ldquo;{pendingOffer.message}&rdquo;
                  </p>
                )}
              </div>
              <div className="flex gap-2">
                <Button
                  color="success"
                  size="sm"
                  isLoading={isRespondingOffer}
                  onPress={handleAcceptOffer}
                  startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('inline_response.offer_accept', 'Accept Offer')}
                </Button>
                <Button
                  color="danger"
                  variant="flat"
                  size="sm"
                  isDisabled={isRespondingOffer}
                  onPress={() => { setDeclineNotes(''); setShowDeclineOfferModal(true); }}
                  startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('inline_response.offer_decline', 'Decline Offer')}
                </Button>
              </div>
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

          {/* Match Explanation Card — "Why You Match" */}
          {qualificationData && !isOwner && (
            <GlassCard className="p-4 mt-4">
              <button
                className="w-full flex items-center justify-between text-left"
                onClick={() => setQualOpen(v => !v)}
                aria-expanded={qualOpen}
              >
                <span className="font-semibold flex items-center gap-2">
                  <Sparkles size={16} className={qualificationData.percentage >= 70 ? 'text-success' : 'text-warning'} aria-hidden="true" />
                  {t('match.why_you_match', 'Why you match')} — {qualificationData.percentage}%
                </span>
                {qualOpen ? <ChevronUp size={16} aria-hidden="true" /> : <ChevronDown size={16} aria-hidden="true" />}
              </button>
              {qualOpen && (
                <div className="mt-3 space-y-3">
                  <p className="text-sm text-theme-secondary italic">{qualificationData.ai_summary}</p>
                  {qualificationData.dimensions.length > 0 && (
                    <div className="grid grid-cols-2 gap-2">
                      {qualificationData.dimensions.map((d, i) => (
                        <div key={i} className="bg-white/5 rounded-lg p-2">
                          <div className="text-xs font-medium text-theme-primary">{d.label}</div>
                          <div className="text-xs text-theme-muted">{d.detail}</div>
                        </div>
                      ))}
                    </div>
                  )}
                  {qualificationData.matched_skills.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-success mb-1">{t('match.you_have', 'You have:')}</p>
                      <div className="flex flex-wrap gap-1">
                        {qualificationData.matched_skills.map((s, i) => (
                          <Chip key={i} size="sm" color="success" variant="flat">{s}</Chip>
                        ))}
                      </div>
                    </div>
                  )}
                  {qualificationData.missing_skills.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-warning mb-1">{t('match.to_develop', 'Skills to develop:')}</p>
                      <div className="flex flex-wrap gap-1">
                        {qualificationData.missing_skills.map((s, i) => (
                          <Chip key={i} size="sm" color="warning" variant="flat">{s}</Chip>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </GlassCard>
          )}

          {/* Feature 2: About the Company / Employer Branding */}
          {(vacancy.tagline || vacancy.video_url || (vacancy.benefits && vacancy.benefits.length > 0)) && (
            <GlassCard className="p-5 mt-4">
              <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                <Building2 size={16} aria-hidden="true" />
                {t('branding.about_company', 'About the Company')}
              </h2>
              {vacancy.tagline && (
                <p className="text-sm text-theme-secondary italic mb-3">&ldquo;{vacancy.tagline}&rdquo;</p>
              )}
              {vacancy.video_url && (
                <div className="aspect-video rounded-lg overflow-hidden mb-3">
                  <iframe
                    src={vacancy.video_url
                      .replace('watch?v=', 'embed/')
                      .replace('youtu.be/', 'youtube.com/embed/')}
                    className="w-full h-full"
                    allowFullScreen
                    title="Company culture video"
                  />
                </div>
              )}
              {vacancy.benefits && vacancy.benefits.length > 0 && (
                <div className="flex flex-wrap gap-2">
                  {vacancy.benefits.map((b: string, i: number) => (
                    <Chip key={i} size="sm" variant="flat" color="success">{b}</Chip>
                  ))}
                </div>
              )}
            </GlassCard>
          )}

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
                        tenantPathFn={tenantPath}
                        navigateFn={navigate}
                      />
                    ))
                  )}
                </div>
              )}
            </GlassCard>
            </div>
          )}

          {/* Feature 4: Pipeline Automation Rules (owner-only) */}
          {isOwner && (
            <GlassCard className="p-4">
              <button
                className="w-full flex items-center justify-between"
                onClick={() => {
                  setPipelineOpen((v) => !v);
                  if (!pipelineOpen) loadPipelineRules();
                }}
                aria-expanded={pipelineOpen}
              >
                <span className="font-semibold flex items-center gap-2">
                  <Zap size={16} aria-hidden="true" />
                  {t('pipeline.title', 'Automation Rules')}
                </span>
                {pipelineOpen ? <ChevronUp size={16} aria-hidden="true" /> : <ChevronDown size={16} aria-hidden="true" />}
              </button>
              {pipelineOpen && (
                <div className="mt-3 space-y-3">
                  {pipelineRules.length === 0 && (
                    <p className="text-sm text-theme-muted">{t('pipeline.no_rules', 'No automation rules yet.')}</p>
                  )}
                  {pipelineRules.map((rule) => (
                    <div key={rule.id} className="flex items-center justify-between text-sm p-2 bg-white/5 rounded-lg">
                      <div>
                        <span className="font-medium">{rule.name}</span>
                        <span className="text-theme-muted ml-2 text-xs">
                          If in &ldquo;{rule.trigger_stage}&rdquo; for {rule.condition_days}d &rarr; {rule.action}{rule.action_target ? ` \u2192 ${rule.action_target}` : ''}
                        </span>
                      </div>
                      <Button
                        size="sm"
                        color="danger"
                        variant="flat"
                        onPress={() =>
                          api.delete(`/v2/jobs/pipeline-rules/${rule.id}`).then(() => loadPipelineRules()).catch((err) => { if (import.meta.env.DEV) console.warn('Non-critical:', err); })
                        }
                      >
                        {t('pipeline.delete', 'Delete')}
                      </Button>
                    </div>
                  ))}
                  <div className="border-t border-divider pt-3">
                    <p className="text-xs font-medium text-theme-muted mb-2">{t('pipeline.add_rule', 'Add rule')}</p>
                    <div className="grid grid-cols-2 gap-2">
                      <Select
                        size="sm"
                        label={t('pipeline.trigger', 'If in stage')}
                        selectedKeys={[newRule.trigger_stage]}
                        onSelectionChange={(keys) =>
                          setNewRule((r) => ({ ...r, trigger_stage: Array.from(keys)[0] as string }))
                        }
                      >
                        {(['applied', 'screening', 'reviewed', 'interview'] as const).map((s) => (
                          <SelectItem key={s}>{s}</SelectItem>
                        ))}
                      </Select>
                      <Input
                        size="sm"
                        type="number"
                        label={t('pipeline.days', 'Days')}
                        value={String(newRule.condition_days)}
                        onChange={(e) =>
                          setNewRule((r) => ({ ...r, condition_days: parseInt(e.target.value) || 7 }))
                        }
                      />
                      <Select
                        size="sm"
                        label={t('pipeline.action', 'Action')}
                        selectedKeys={[newRule.action]}
                        onSelectionChange={(keys) =>
                          setNewRule((r) => ({ ...r, action: Array.from(keys)[0] as string }))
                        }
                      >
                        <SelectItem key="move_stage">Move stage</SelectItem>
                        <SelectItem key="reject">Auto-reject</SelectItem>
                        <SelectItem key="notify_reviewer">Notify me</SelectItem>
                      </Select>
                      {newRule.action === 'move_stage' && (
                        <Select
                          size="sm"
                          label={t('pipeline.target', 'Move to')}
                          selectedKeys={[newRule.action_target]}
                          onSelectionChange={(keys) =>
                            setNewRule((r) => ({ ...r, action_target: Array.from(keys)[0] as string }))
                          }
                        >
                          {(['screening', 'reviewed', 'interview', 'rejected'] as const).map((s) => (
                            <SelectItem key={s}>{s}</SelectItem>
                          ))}
                        </Select>
                      )}
                    </div>
                    <Button
                      size="sm"
                      color="primary"
                      className="mt-2"
                      isLoading={isAddingRule}
                      onPress={async () => {
                        setIsAddingRule(true);
                        try {
                          await api.post(`/v2/jobs/${id}/pipeline-rules`, {
                            ...newRule,
                            name: `${newRule.trigger_stage} \u2192 ${newRule.action_target || newRule.action} after ${newRule.condition_days}d`,
                          });
                          await loadPipelineRules();
                        } catch (err) {
                          logError('Failed to add pipeline rule', err);
                        } finally {
                          setIsAddingRule(false);
                        }
                      }}
                    >
                      {t('pipeline.add', 'Add Rule')}
                    </Button>
                  </div>
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

            {/* J9: Salary display in sidebar — EU Pay Transparency */}
            <div className="flex items-center gap-3">
              <DollarSign className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
              <div>
                <p className="text-xs text-theme-subtle">{t('salary.label')}</p>
                {formatSalary() ? (
                  <p className="text-sm text-theme-primary font-medium">{formatSalary()}</p>
                ) : !vacancy.salary_negotiable ? (
                  <p className="text-sm text-theme-muted">{t('salary_not_specified')}</p>
                ) : null}
                {vacancy.salary_negotiable && (
                  <p className="text-xs text-success">{t('salary.negotiable')}</p>
                )}
              </div>
            </div>

            {/* Feature 2: Salary benchmark widget — owners only */}
            {isOwner && benchmark && (
              <div className="flex items-start gap-2 bg-primary/5 rounded-lg p-2.5">
                <TrendingUp className="w-4 h-4 text-primary shrink-0 mt-0.5" aria-hidden="true" />
                <p className="text-xs text-theme-primary">
                  {t('benchmark.market_rate', {
                    role: benchmark.role_keyword,
                    currency: benchmark.currency,
                    min: benchmark.salary_min.toLocaleString(),
                    max: benchmark.salary_max.toLocaleString(),
                    type: benchmark.salary_type,
                    median: benchmark.salary_median.toLocaleString(),
                    defaultValue: `Market rate for "${benchmark.role_keyword}": ${benchmark.currency}${benchmark.salary_min.toLocaleString()} – ${benchmark.currency}${benchmark.salary_max.toLocaleString()} / ${benchmark.salary_type} (median: ${benchmark.currency}${benchmark.salary_median.toLocaleString()})`,
                  })}
                </p>
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
      <Modal isOpen={applyModal.isOpen} onOpenChange={(isOpen) => {
        if (!isOpen) {
          setApplyMessage('');
          setCvFile(null);
          setCvParsed(null);
          setUsingSavedProfile(false);
        }
        if (isOpen) applyModal.onOpen(); else applyModal.onClose();
      }}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('apply.title')}</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  {/* Feature 5: Saved profile banner */}
                  {savedProfile && !usingSavedProfile && (
                    <div className="flex items-center gap-3 p-3 rounded-lg bg-primary/5 border border-primary/20 text-sm">
                      <div className="flex-1 text-theme-primary">
                        {t('saved_profile.found', 'Saved application profile found')}
                        {savedProfile.cv_filename && (
                          <span className="ml-1 text-xs text-theme-subtle">— CV: {savedProfile.cv_filename}</span>
                        )}
                      </div>
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        onPress={() => {
                          if (savedProfile.cover_text) setApplyMessage(savedProfile.cover_text);
                          setUsingSavedProfile(true);
                        }}
                      >
                        {t('saved_profile.use', 'Use Saved Profile')}
                      </Button>
                      <Button
                        size="sm"
                        variant="flat"
                        className="text-theme-muted"
                        onPress={() => setSavedProfile(null)}
                      >
                        {t('saved_profile.start_fresh', 'Start Fresh')}
                      </Button>
                    </div>
                  )}
                  {usingSavedProfile && savedProfile?.cv_filename && (
                    <Chip size="sm" variant="flat" color="primary">
                      Saved CV: {savedProfile.cv_filename}
                    </Chip>
                  )}

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

                  {/* CV Upload */}
                  <div className="space-y-1">
                    <label className="text-sm font-medium text-foreground">
                      {t('apply.cv_label', 'CV / Resume')}{' '}
                      <span className="text-default-400 text-xs">
                        {t('apply.cv_hint', '(optional — PDF, DOC, DOCX, max 5MB)')}
                      </span>
                    </label>
                    <div
                      className="border-2 border-dashed border-default-200 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition-colors"
                      onClick={() => cvInputRef.current?.click()}
                      onDrop={handleCvDrop}
                      onDragOver={(e) => e.preventDefault()}
                      role="button"
                      tabIndex={0}
                      aria-label={t('apply.cv_dropzone_aria', 'Click or drop file to upload CV')}
                      onKeyDown={(e) => e.key === 'Enter' && cvInputRef.current?.click()}
                    >
                      {cvFile ? (
                        <div className="flex items-center justify-center gap-2 text-sm text-foreground">
                          <FileTextIcon size={16} aria-hidden="true" />
                          <span>{cvFile.name}</span>
                          <span className="text-default-400">({(cvFile.size / 1024).toFixed(0)} KB)</span>
                          <Button
                            size="sm"
                            variant="light"
                            color="danger"
                            isIconOnly
                            onClick={(e) => { e.stopPropagation(); setCvFile(null); }}
                            aria-label={t('apply.cv_remove', 'Remove CV')}
                          >
                            <X size={14} aria-hidden="true" />
                          </Button>
                        </div>
                      ) : (
                        <div className="text-default-400 text-sm">
                          <Upload size={20} className="mx-auto mb-1" aria-hidden="true" />
                          {t('apply.cv_drop_prompt', 'Drop CV here or click to browse')}
                        </div>
                      )}
                    </div>
                    <input
                      ref={cvInputRef}
                      type="file"
                      accept=".pdf,.doc,.docx"
                      className="hidden"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          if (file.size > 5 * 1024 * 1024) {
                            toastRef.current.error(tRef.current('apply.cv_too_large', 'CV file must be under 5MB'));
                          } else {
                            setCvFile(file);
                            // Clear any previous parse result when a new CV is selected
                            setCvParsed(null);
                          }
                        }
                      }}
                    />
                  </div>

                  {/* Feature 5: CV parsed skills display */}
                  {cvFile && !cvParsed && (
                    <p className="text-xs text-default-400 flex items-center gap-1 mt-1">
                      <Sparkles size={12} aria-hidden="true" />
                      {t('cv.parse', 'Skills will be extracted after submission')}
                    </p>
                  )}
                  {cvParsed && cvParsed.skills && cvParsed.skills.length > 0 && (
                    <div className="p-2 rounded-lg bg-secondary-50 border border-secondary-200 text-xs mt-1">
                      <div className="font-medium text-secondary-700 mb-1 flex items-center gap-1">
                        <Sparkles size={12} aria-hidden="true" />
                        {t('cv.detected', 'Skills detected from CV')}
                      </div>
                      <div className="flex flex-wrap gap-1">
                        {cvParsed.skills.map((skill) => (
                          <Chip key={skill} size="sm" variant="flat" color="secondary">{skill}</Chip>
                        ))}
                      </div>
                      {cvParsed.summary && (
                        <p className="text-default-600 mt-1 italic">{cvParsed.summary}</p>
                      )}
                    </div>
                  )}
                </div>
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

      {/* GAP 1: Decline Interview Modal */}
      <Modal isOpen={showDeclineInterviewModal} onClose={() => setShowDeclineInterviewModal(false)} size="sm">
        <ModalContent>
          <ModalHeader>{t('inline_response.interview_decline', 'Decline Interview')}</ModalHeader>
          <ModalBody>
            <Textarea
              label={t('inline_response.decline_notes_label', 'Reason (optional)')}
              placeholder={t('inline_response.decline_notes_placeholder', 'Let the employer know why...')}
              value={declineNotes}
              onValueChange={setDeclineNotes}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setShowDeclineInterviewModal(false)}>
              {t('apply.cancel')}
            </Button>
            <Button color="danger" isLoading={isRespondingInterview} onPress={handleDeclineInterview}>
              {t('inline_response.interview_decline', 'Decline Interview')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* GAP 1: Decline Offer Modal */}
      <Modal isOpen={showDeclineOfferModal} onClose={() => setShowDeclineOfferModal(false)} size="sm">
        <ModalContent>
          <ModalHeader>{t('inline_response.offer_decline', 'Decline Offer')}</ModalHeader>
          <ModalBody>
            <Textarea
              label={t('inline_response.decline_reason_label', 'Reason (optional)')}
              placeholder={t('inline_response.decline_reason_placeholder', 'Share your reason...')}
              value={declineNotes}
              onValueChange={setDeclineNotes}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setShowDeclineOfferModal(false)}>
              {t('apply.cancel')}
            </Button>
            <Button color="danger" isLoading={isRespondingOffer} onPress={handleDeclineOffer}>
              {t('inline_response.offer_decline', 'Decline Offer')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Interview Self-Scheduling Section (Agent E) */}
      {isOwner && (
        <div id="interview-slots-section" className="mt-6">
          <GlassCard className="p-5">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-500/20 to-blue-500/20 flex items-center justify-center">
                <CalendarClock className="w-5 h-5 text-cyan-400" aria-hidden="true" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-theme-primary">{t('self_scheduling.title', 'Interview Slots')}</h3>
                <p className="text-sm text-theme-muted">{t('self_scheduling.employer_no_slots', 'Add interview slots so candidates can self-schedule')}</p>
              </div>
            </div>
            <p className="text-sm text-theme-muted">
              {t('self_scheduling.manage_slots', 'Manage Interview Slots')} &mdash; {t('self_scheduling.candidate_pick', 'Choose a time slot for your interview')}
            </p>
          </GlassCard>
        </div>
      )}

      {/* Candidate self-scheduling view (Agent E) */}
      {!isOwner && vacancy?.has_applied && (
        <div id="interview-slots-candidate-section" className="mt-6">
          <GlassCard className="p-5">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-500/20 to-blue-500/20 flex items-center justify-center">
                <CalendarClock className="w-5 h-5 text-cyan-400" aria-hidden="true" />
              </div>
              <h3 className="text-lg font-semibold text-theme-primary">{t('self_scheduling.title', 'Interview Slots')}</h3>
            </div>
            <p className="text-sm text-theme-muted">{t('self_scheduling.candidate_pick', 'Choose a time slot for your interview')}</p>
          </GlassCard>
        </div>
      )}

      {/* Similar Jobs */}
      {similarJobs.length > 0 && (
        <div className="mt-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('similar_jobs')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {similarJobs.map((sj) => {
              const SjTypeIcon = TYPE_ICONS[sj.type] ?? Briefcase;
              return (
                <Link key={sj.id} to={tenantPath(`/jobs/${sj.id}`)}>
                  <GlassCard className="p-4 hover:scale-[1.02] transition-transform h-full">
                    <div className="flex items-center gap-2 mb-2">
                      <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center flex-shrink-0">
                        <Briefcase className="w-4 h-4 text-blue-400" aria-hidden="true" />
                      </div>
                      <h3 className="font-medium text-theme-primary text-sm line-clamp-2">{sj.title}</h3>
                    </div>
                    <p className="text-xs text-theme-muted mb-2">
                      {sj.organization?.name ?? sj.creator?.name}
                    </p>
                    <div className="flex flex-wrap gap-1">
                      <Chip size="sm" variant="flat" color={TYPE_CHIP_COLORS[sj.type] ?? 'default'} className="text-xs">
                        <span className="flex items-center gap-0.5">
                          <SjTypeIcon className="w-3 h-3" aria-hidden="true" />
                          {t(`type.${sj.type}`)}
                        </span>
                      </Chip>
                      {sj.is_remote ? (
                        <Chip size="sm" variant="flat" color="primary" className="text-xs">
                          <span className="flex items-center gap-0.5">
                            <Globe className="w-3 h-3" aria-hidden="true" />
                            {t('remote')}
                          </span>
                        </Chip>
                      ) : sj.location ? (
                        <Chip size="sm" variant="flat" color="default" className="text-xs">
                          <span className="flex items-center gap-0.5">
                            <MapPin className="w-3 h-3" aria-hidden="true" />
                            {sj.location}
                          </span>
                        </Chip>
                      ) : null}
                    </div>
                  </GlassCard>
                </Link>
              );
            })}
          </div>
        </div>
      )}
    </main>
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
          <div className="space-y-3">
            <div className="text-center space-y-2">
              <Button
                isDisabled
                className="w-full"
                variant="flat"
                color="success"
                startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
              >
                {t('already_applied')}
              </Button>
              <p className="text-xs text-theme-muted">
                {t('application_status_label')}: {t(`application_status.${vacancy!.application_stage ?? vacancy!.application_status ?? 'applied'}`)}
              </p>
            </div>
            {/* Feature 6: Message Employer button for applicants */}
            <Button
              variant="flat"
              startContent={<MessageCircle size={16} aria-hidden="true" />}
              className="w-full bg-theme-elevated text-theme-muted"
              onPress={() => navigate(tenantPath(`/messages?user=${vacancy!.creator.id}&context=job&context_id=${vacancy!.id}`))}
            >
              {t('apply.message_employer', 'Message Employer')}
            </Button>
          </div>
        ) : (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full"
            size="lg"
            onPress={applyModal.onOpen}
            aria-label={t('apply.button_label', 'Apply for {{title}}', { title: vacancy?.title ?? 'this job' })}
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
  tenantPathFn: (path: string) => string;
  navigateFn: (path: string) => void;
}

function ApplicationCard({ application, onUpdateStatus, tenantPathFn, navigateFn }: ApplicationCardProps) {
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

      {/* Feature 6: Message applicant button (owner view) */}
      <div className="mt-2 flex items-center gap-3">
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<MessageCircle size={13} aria-hidden="true" />}
          onPress={() => navigateFn(tenantPathFn(`/messages?user=${application.applicant.id}&context=job&context_id=${application.vacancy_id}`))}
        >
          {t('detail.message_applicant', 'Message')}
        </Button>
      </div>

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
