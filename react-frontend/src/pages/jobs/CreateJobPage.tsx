// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create / Edit Job Vacancy Page
 *
 * Handles both creation and editing via optional :id route param.
 * Uses HeroUI form components with validation.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tooltip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Avatar,
} from '@heroui/react';
import {
  Briefcase,
  ArrowLeft,
  Info,
  ChevronDown,
  ChevronUp,
  Users,
  TrendingUp,
  FileText,
  Sparkles,
  X,
  Eye,
  EyeOff,
  Trash2,
  Plus,
  Search,
  MapPin,
  Calendar,
  DollarSign,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';

interface JobFormData {
  title: string;
  description: string;
  type: string;
  commitment: string;
  category: string;
  location: string;
  is_remote: boolean;
  skills_required: string;
  hours_per_week: string;
  time_credits: string;
  contact_email: string;
  contact_phone: string;
  deadline: string;
  // J9: Salary fields
  salary_min: string;
  salary_max: string;
  salary_type: string;
  salary_currency: string;
  salary_negotiable: boolean;
  organization_id?: number;
}

const COMPANY_SIZE_OPTIONS = ['1-10', '11-50', '51-200', '201-500', '500+'] as const;

interface SalaryBenchmark {
  role_keyword: string;
  salary_min: number;
  salary_max: number;
  salary_median: number;
  salary_type: string;
  currency: string;
}

interface JobTemplate {
  id: number;
  name: string;
  type: string;
  commitment: string;
  skills_required?: string;
  is_remote?: boolean;
  salary_min?: string;
  salary_max?: string;
  salary_type?: string;
  salary_currency?: string;
  hours_per_week?: string;
  time_credits?: string;
  benefits?: string[];
  tagline?: string;
  description?: string;
}

interface TeamMember {
  id: number;
  name: string;
  avatar_url: string | null;
  role: string;
}

const INITIAL_FORM: JobFormData = {
  title: '',
  description: '',
  type: 'paid',
  commitment: 'flexible',
  category: '',
  location: '',
  is_remote: false,
  skills_required: '',
  hours_per_week: '',
  time_credits: '',
  contact_email: '',
  contact_phone: '',
  deadline: '',
  salary_min: '',
  salary_max: '',
  salary_type: '',
  salary_currency: '',
  salary_negotiable: false,
};

const JOB_TYPES = ['paid', 'volunteer', 'timebank'] as const;
const COMMITMENT_TYPES = ['full_time', 'part_time', 'flexible', 'one_off'] as const;

export function CreateJobPage() {
  const { t } = useTranslation('jobs');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { isAuthenticated, user } = useAuth();

  const isEditing = Boolean(id);
  usePageTitle(isEditing ? t('form.edit_title') : t('form.create_title'));

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const [form, setForm] = useState<JobFormData>(INITIAL_FORM);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Employer Branding section state
  const [brandingOpen, setBrandingOpen] = useState(false);
  const [tagline, setTagline] = useState('');
  const [videoUrl, setVideoUrl] = useState('');
  const [companySize, setCompanySize] = useState('');
  const [benefits, setBenefits] = useState<string[]>([]);
  const [benefitInput, setBenefitInput] = useState('');

  // Hiring Team section state
  const [teamOpen, setTeamOpen] = useState(false);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [teamSearchInput, setTeamSearchInput] = useState('');
  const [isAddingTeamMember, setIsAddingTeamMember] = useState(false);
  const [createdJobId, setCreatedJobId] = useState<string | null>(null);
  const [addMemberModalOpen, setAddMemberModalOpen] = useState(false);
  const [newMemberRole, setNewMemberRole] = useState<string>('reviewer');
  const [memberSearchResults, setMemberSearchResults] = useState<Array<{ id: number; name: string; avatar_url: string | null }>>([]);
  const [isMemberSearching, setIsMemberSearching] = useState(false);
  const memberSearchDebounce = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Salary Benchmark (Feature 2)
  const [benchmark, setBenchmark] = useState<SalaryBenchmark | null>(null);
  const benchmarkDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Job Templates (Feature 3)
  const [templates, setTemplates] = useState<JobTemplate[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState('');
  const [saveTemplateOpen, setSaveTemplateOpen] = useState(false);
  const [templateName, setTemplateName] = useState('');
  const [templateIsPublic, setTemplateIsPublic] = useState(false);
  const [isSavingTemplate, setIsSavingTemplate] = useState(false);

  // AI Generate state (Agent A)
  const [isGenerating, setIsGenerating] = useState(false);

  // Duplicate detection state (Agent A)
  const [duplicates, setDuplicates] = useState<Array<{ id: number; title: string; status: string; similarity: number }>>([]);
  const [duplicatesDismissed, setDuplicatesDismissed] = useState(false);

  // Blind hiring state (Agent C)
  const [blindHiring, setBlindHiring] = useState(false);

  // Preview modal state
  const [previewOpen, setPreviewOpen] = useState(false);

  // Unsaved changes tracking
  const formDirtyRef = useRef(false);

  // Parse skills for display
  const skillsArray = form.skills_required
    ? form.skills_required
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
    : [];

  // Load existing vacancy for editing
  useEffect(() => {
    if (!isEditing || !id) return;

    const loadVacancy = async () => {
      setIsLoading(true);
      try {
        const response = await api.get<Record<string, unknown>>(`/v2/jobs/${id}`);
        if (response.success && response.data) {
          const v = response.data;
          const str = (val: unknown): string => (typeof val === 'string' ? val : '');
          setForm({
            title: str(v.title),
            description: str(v.description),
            type: str(v.type) || 'paid',
            commitment: str(v.commitment) || 'flexible',
            category: str(v.category),
            location: str(v.location),
            is_remote: Boolean(v.is_remote),
            skills_required: str(v.skills_required),
            hours_per_week: v.hours_per_week != null ? String(v.hours_per_week) : '',
            time_credits: v.time_credits != null ? String(v.time_credits) : '',
            contact_email: str(v.contact_email),
            contact_phone: str(v.contact_phone),
            deadline: v.deadline ? (String(v.deadline).split('T')[0] ?? '') : '',
            salary_min: v.salary_min != null ? String(v.salary_min) : '',
            salary_max: v.salary_max != null ? String(v.salary_max) : '',
            salary_type: str(v.salary_type),
            salary_currency: str(v.salary_currency),
            salary_negotiable: Boolean(v.salary_negotiable),
          });
          // Load branding fields
          if (str(v.tagline)) setTagline(str(v.tagline));
          if (str(v.video_url)) setVideoUrl(str(v.video_url));
          if (str(v.company_size)) setCompanySize(str(v.company_size));
          if (Array.isArray(v.benefits)) setBenefits(v.benefits as string[]);
        } else {
          toastRef.current.error(tRef.current('detail.not_found'));
          navigate(tenantPath('/jobs'));
        }
      } catch (err) {
        logError('Failed to load vacancy for editing', err);
        toastRef.current.error(tRef.current('detail.unable_to_load'));
        navigate(tenantPath('/jobs'));
      } finally {
        setIsLoading(false);
      }
    };

    loadVacancy();
  }, [id, isEditing, navigate, tenantPath]);

  // Load team members when editing an existing job
  useEffect(() => {
    const jobId = isEditing ? id : createdJobId;
    if (!jobId) return;
    const controller = new AbortController();
    api.get<TeamMember[]>(`/v2/jobs/${jobId}/team`)
      .then((res) => {
        if (!controller.signal.aborted && res.success && res.data) {
          setTeamMembers(res.data);
        }
      })
      .catch(() => { /* non-critical */ });
    return () => { controller.abort(); };
  }, [id, isEditing, createdJobId]);

  // Feature 3: Load job templates on mount
  useEffect(() => {
    if (!isAuthenticated) return;
    const controller = new AbortController();
    api.get<JobTemplate[]>('/v2/jobs/templates')
      .then((res) => {
        if (!controller.signal.aborted && res.success && res.data) {
          setTemplates(res.data);
        }
      })
      .catch(() => { /* non-critical */ });
    return () => { controller.abort(); };
  }, [isAuthenticated]);

  // Feature 2: Salary benchmark — debounce on title change
  useEffect(() => {
    if (benchmarkDebounceRef.current) clearTimeout(benchmarkDebounceRef.current);
    const title = form.title.trim();
    if (!title) {
      setBenchmark(null);
      return;
    }
    benchmarkDebounceRef.current = setTimeout(async () => {
      try {
        const response = await api.get<SalaryBenchmark>(`/v2/jobs/salary-benchmark?title=${encodeURIComponent(title)}`);
        if (response.success && response.data) {
          setBenchmark(response.data);
        } else {
          setBenchmark(null);
        }
      } catch {
        setBenchmark(null);
      }
    }, 600);
    return () => {
      if (benchmarkDebounceRef.current) clearTimeout(benchmarkDebounceRef.current);
    };
  }, [form.title]);

  // Unsaved changes warning — beforeunload
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (formDirtyRef.current) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, []);

  const updateField = useCallback(<K extends keyof JobFormData>(field: K, value: JobFormData[K]) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    formDirtyRef.current = true;
    // Clear field error on change
    if (errors[field]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  }, [errors]);

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!form.title.trim()) {
      newErrors.title = t('form.validation.title_required');
    }
    if (!form.description.trim()) {
      newErrors.description = t('form.validation.description_required');
    }
    if (form.deadline) {
      const deadlineDate = new Date(form.deadline);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      if (deadlineDate < today) {
        newErrors.deadline = t('form.validation.deadline_past', 'Deadline must be a future date');
      }
    }

    // Salary min cannot exceed max
    if (form.salary_min && form.salary_max && Number(form.salary_min) > Number(form.salary_max)) {
      newErrors.salary_range = t('form.validation.salary_range_invalid', 'Minimum salary cannot exceed maximum salary');
    }

    // EU Pay Transparency: salary range required for paid jobs unless negotiable
    if (form.type === 'paid' && !form.salary_negotiable) {
      if (!form.salary_min && !form.salary_max) {
        newErrors.salary_range = t('form.validation.salary_required', "Salary range required. You may check 'Salary negotiable' to omit.");
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // AI description generation (Agent A)
  const handleAiGenerate = useCallback(async () => {
    if (!form.title.trim()) {
      toastRef.current.error(tRef.current('form.validation.title_required'));
      return;
    }

    setIsGenerating(true);
    try {
      const payload: Record<string, unknown> = {
        title: form.title.trim(),
        skills: skillsArray,
        type: form.type,
        commitment: form.commitment,
      };

      const response = await api.post<{ description: string }>('/v2/jobs/generate-description', payload);

      if (response.success && response.data) {
        const desc = ((response.data as Record<string, unknown>)?.description ?? '') as string;
        if (desc) {
          setForm((prev) => ({ ...prev, description: desc }));
          toastRef.current.success(tRef.current('ai_generate.success'));
        }
      } else {
        toastRef.current.error(response.error || tRef.current('ai_generate.error'));
      }
    } catch (err) {
      logError('AI description generation failed', err);
      toastRef.current.error(tRef.current('ai_generate.error'));
    } finally {
      setIsGenerating(false);
    }
  }, [form.title, form.type, form.commitment, skillsArray]);

  // Duplicate detection on title change (Agent A)
  useEffect(() => {
    const title = form.title.trim();
    if (!title || title.length < 5 || isEditing) {
      setDuplicates([]);
      return;
    }
    setDuplicatesDismissed(false);
    const timer = setTimeout(async () => {
      try {
        const response = await api.post<{ duplicates: typeof duplicates }>('/v2/jobs/check-duplicate', {
          title,
          organization_id: form.organization_id || undefined,
        });
        if (response.success && response.data) {
          setDuplicates((response.data as Record<string, unknown>).duplicates as typeof duplicates || []);
        }
      } catch {
        // Non-critical, silently fail
      }
    }, 800);
    return () => clearTimeout(timer);
  }, [form.title, form.organization_id, isEditing]); // eslint-disable-line react-hooks/exhaustive-deps -- debounced duplicate check on title/org change

  const handleSubmit = async () => {
    if (!validate()) return;

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        title: form.title.trim(),
        description: form.description.trim(),
        type: form.type,
        commitment: form.commitment,
        is_remote: form.is_remote,
      };

      // Optional fields
      if (form.category.trim()) payload.category = form.category.trim();
      if (form.location.trim()) payload.location = form.location.trim();
      if (form.skills_required.trim()) payload.skills_required = form.skills_required.trim();
      if (form.hours_per_week) payload.hours_per_week = parseFloat(form.hours_per_week);
      if (form.time_credits) payload.time_credits = parseFloat(form.time_credits);
      if (form.contact_email.trim()) payload.contact_email = form.contact_email.trim();
      if (form.contact_phone.trim()) payload.contact_phone = form.contact_phone.trim();
      if (form.deadline) payload.deadline = form.deadline;

      // J9: Salary fields — strip commas/spaces before parsing to handle locale formats like "50,000"
      if (form.salary_min) payload.salary_min = parseFloat(form.salary_min.replace(/[,\s]/g, ''));
      if (form.salary_max) payload.salary_max = parseFloat(form.salary_max.replace(/[,\s]/g, ''));
      if (form.salary_type) payload.salary_type = form.salary_type;
      if (form.salary_currency.trim()) payload.salary_currency = form.salary_currency.trim();
      payload.salary_negotiable = form.salary_negotiable;
      payload.blind_hiring = blindHiring;

      // Employer Branding fields
      if (tagline.trim()) payload.tagline = tagline.trim();
      if (videoUrl.trim()) payload.video_url = videoUrl.trim();
      if (companySize) payload.company_size = companySize;
      if (benefits.length > 0) payload.benefits = JSON.stringify(benefits);

      let response;
      if (isEditing && id) {
        response = await api.put(`/v2/jobs/${id}`, payload);
      } else {
        response = await api.post('/v2/jobs', payload);
      }

      if (response.success) {
        formDirtyRef.current = false;
        toastRef.current.success(isEditing ? tRef.current('form.update_success') : tRef.current('form.create_success'));
        const newId = isEditing ? id : (response.data as Record<string, unknown>)?.id;
        if (newId) {
          if (!isEditing) {
            setCreatedJobId(String(newId));
          }
          navigate(tenantPath(`/jobs/${newId}`));
        } else {
          navigate(tenantPath('/jobs'));
        }
      } else {
        toastRef.current.error(response.error || (isEditing ? tRef.current('form.update_error') : tRef.current('form.create_error')));
      }
    } catch (err) {
      logError('Failed to save vacancy', err);
      toastRef.current.error(isEditing ? tRef.current('form.update_error') : tRef.current('form.create_error'));
    } finally {
      setIsSubmitting(false);
    }
  };

  // Feature 3: Apply a template to the form
  const applyTemplate = useCallback((templateId: string) => {
    const tpl = templates.find((t) => String(t.id) === templateId);
    if (!tpl) return;
    setForm((prev) => ({
      ...prev,
      type: tpl.type || prev.type,
      commitment: tpl.commitment || prev.commitment,
      skills_required: tpl.skills_required ?? prev.skills_required,
      is_remote: tpl.is_remote ?? prev.is_remote,
      salary_min: tpl.salary_min ?? prev.salary_min,
      salary_max: tpl.salary_max ?? prev.salary_max,
      salary_type: tpl.salary_type ?? prev.salary_type,
      salary_currency: tpl.salary_currency ?? prev.salary_currency,
      hours_per_week: tpl.hours_per_week ?? prev.hours_per_week,
      time_credits: tpl.time_credits ?? prev.time_credits,
      description: tpl.description ?? prev.description,
    }));
    if (tpl.tagline) setTagline(tpl.tagline);
    if (tpl.benefits) setBenefits(tpl.benefits);
    setSelectedTemplate(templateId);
  }, [templates]);

  // Feature 3: Save current form as a template
  const handleSaveTemplate = async () => {
    if (!templateName.trim()) return;
    setIsSavingTemplate(true);
    try {
      const payload = {
        name: templateName.trim(),
        is_public: templateIsPublic,
        type: form.type,
        commitment: form.commitment,
        skills_required: form.skills_required,
        is_remote: form.is_remote,
        salary_min: form.salary_min,
        salary_max: form.salary_max,
        salary_type: form.salary_type,
        salary_currency: form.salary_currency,
        hours_per_week: form.hours_per_week,
        time_credits: form.time_credits,
        benefits: JSON.stringify(benefits),
        tagline,
        description: form.description,
      };
      const response = await api.post<JobTemplate>('/v2/jobs/templates', payload);
      if (response.success && response.data) {
        setTemplates((prev) => [...prev, response.data!]);
        toast.success(t('templates.saved', 'Template saved'));
      } else {
        toast.error(t('template.save_error', 'Failed to save template'));
      }
    } catch (err) {
      logError('Failed to save template', err);
      toast.error(t('template.save_error', 'Failed to save template'));
    } finally {
      setIsSavingTemplate(false);
      setSaveTemplateOpen(false);
      setTemplateName('');
      setTemplateIsPublic(false);
    }
  };

  // Delete a saved template
  const handleDeleteTemplate = useCallback(async (templateId: number) => {
    try {
      const res = await api.delete(`/v2/jobs/templates/${templateId}`);
      if (res.success) {
        setTemplates((prev) => prev.filter((t) => t.id !== templateId));
        if (selectedTemplate === String(templateId)) setSelectedTemplate('');
        toastRef.current.success(tRef.current('templates.deleted', 'Template deleted'));
      }
    } catch (err) {
      logError('Failed to delete template', err);
    }
  }, [selectedTemplate]);

  // Member search for hiring team
  const handleMemberSearch = useCallback((query: string) => {
    setTeamSearchInput(query);
    if (memberSearchDebounce.current) clearTimeout(memberSearchDebounce.current);
    if (!query.trim()) {
      setMemberSearchResults([]);
      return;
    }
    memberSearchDebounce.current = setTimeout(async () => {
      setIsMemberSearching(true);
      try {
        const res = await api.get<Array<{ id: number; name: string; avatar_url: string | null }>>(
          `/v2/members/search?q=${encodeURIComponent(query.trim())}&limit=5`
        );
        if (res.success && res.data) {
          // Filter out members already on the team
          const existingIds = new Set(teamMembers.map((m) => m.id));
          setMemberSearchResults((res.data as Array<{ id: number; name: string; avatar_url: string | null }>).filter((m) => !existingIds.has(m.id)));
        }
      } catch {
        /* non-critical */
      } finally {
        setIsMemberSearching(false);
      }
    }, 400);
  }, [teamMembers]);

  // Add a member to the hiring team
  const handleAddTeamMember = useCallback(async (userId: number) => {
    const jobId = isEditing ? id : createdJobId;
    if (!jobId) return;
    setIsAddingTeamMember(true);
    try {
      const res = await api.post(`/v2/jobs/${jobId}/team`, { user_id: userId, role: newMemberRole });
      if (res.success && res.data) {
        setTeamMembers((prev) => [...prev, res.data as TeamMember]);
        toastRef.current.success(tRef.current('team.added', 'Team member added'));
        setTeamSearchInput('');
        setMemberSearchResults([]);
        setAddMemberModalOpen(false);
        setNewMemberRole('reviewer');
      }
    } catch (err) {
      logError('Failed to add team member', err);
    } finally {
      setIsAddingTeamMember(false);
    }
  }, [isEditing, id, createdJobId, newMemberRole]);

  // Remove a member from the hiring team
  const handleRemoveTeamMember = useCallback(async (memberId: number) => {
    const jobId = isEditing ? id : createdJobId;
    if (!jobId) return;
    try {
      const res = await api.delete(`/v2/jobs/${jobId}/team/${memberId}`);
      if (res.success) {
        setTeamMembers((prev) => prev.filter((m) => m.id !== memberId));
        toastRef.current.success(tRef.current('team.removed', 'Team member removed'));
      }
    } catch (err) {
      logError('Failed to remove team member', err);
    }
  }, [isEditing, id, createdJobId]);

  if (isLoading) {
    return (
      <div className="space-y-6 max-w-2xl mx-auto">
        <GlassCard className="p-6 animate-pulse">
          <div className="h-8 bg-theme-hover rounded w-1/2 mb-6" />
          <div className="space-y-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-12 bg-theme-hover rounded" />
            ))}
          </div>
        </GlassCard>
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-2xl mx-auto">
      <PageMeta title="Post a Job" noIndex />
      {/* Back nav */}
      <Link
        to={isEditing ? tenantPath(`/jobs/${id}`) : tenantPath('/jobs')}
        className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {isEditing ? t('detail.browse_vacancies') : t('title')}
      </Link>

      {/* Form */}
      <GlassCard className="p-6">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Briefcase className="w-7 h-7 text-blue-400" aria-hidden="true" />
            {isEditing ? t('form.edit_title') : t('form.create_title')}
          </h1>

          {/* Templates dropdown */}
          {isAuthenticated && (
            <Dropdown>
              <DropdownTrigger>
                <Button
                  variant="flat"
                  size="sm"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<FileText className="w-4 h-4" aria-hidden="true" />}
                  endContent={<ChevronDown className="w-3.5 h-3.5" aria-hidden="true" />}
                >
                  {t('templates.title', 'Templates')}
                </Button>
              </DropdownTrigger>
              <DropdownMenu
                aria-label={t('templates.title', 'Templates')}
                onAction={(key) => {
                  const keyStr = String(key);
                  if (keyStr === 'empty') return;
                  applyTemplate(keyStr);
                  toastRef.current.success(tRef.current('templates.loaded', 'Template loaded'));
                }}
              >
                {templates.length === 0 ? (
                  <DropdownItem key="empty" isReadOnly className="text-theme-subtle italic">
                    {t('templates.empty', 'No saved templates')}
                  </DropdownItem>
                ) : (
                  templates.map((tpl) => (
                    <DropdownItem
                      key={String(tpl.id)}
                      endContent={
                        <Button
                          isIconOnly
                          size="sm"
                          variant="light"
                          color="danger"
                          className="min-w-6 w-6 h-6"
                          onPress={() => {
                            void handleDeleteTemplate(tpl.id);
                          }}
                          aria-label={t('templates.delete_confirm', 'Delete this template?')}
                        >
                          <Trash2 className="w-3.5 h-3.5" aria-hidden="true" />
                        </Button>
                      }
                    >
                      {tpl.name}
                    </DropdownItem>
                  ))
                )}
              </DropdownMenu>
            </Dropdown>
          )}
        </div>

        <div className="space-y-5">

          {/* Title */}
          <div>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={form.title}
              onChange={(e) => updateField('title', e.target.value.slice(0, 100))}
              maxLength={100}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            <p className={`text-xs text-right mt-1 ${form.title.length >= 100 ? 'text-danger' : form.title.length >= 90 ? 'text-warning' : 'text-theme-subtle'}`}>
              {t('char_count', { count: form.title.length, max: 100 })}
            </p>
          </div>

          {/* Description with AI Generate Button (Agent A) */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-theme-primary">{t('form.description_label')}</span>
              <Tooltip content={t('ai_generate.tooltip')}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-gradient-to-r from-violet-500/10 to-fuchsia-500/10 text-violet-600 dark:text-violet-400"
                  startContent={<Sparkles className="w-3.5 h-3.5" aria-hidden="true" />}
                  onPress={handleAiGenerate}
                  isLoading={isGenerating}
                  isDisabled={!form.title.trim()}
                >
                  {isGenerating ? t('ai_generate.button_loading') : t('ai_generate.button')}
                </Button>
              </Tooltip>
            </div>
            <Textarea
              placeholder={t('form.description_placeholder')}
              value={form.description}
              onValueChange={(v) => updateField('description', v.slice(0, 5000))}
              maxLength={5000}
              isRequired
              isInvalid={!!errors.description}
              errorMessage={errors.description}
              minRows={5}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            <p className={`text-xs text-right mt-1 ${form.description.length >= 5000 ? 'text-danger' : form.description.length >= 4500 ? 'text-warning' : 'text-theme-subtle'}`}>
              {t('char_count', { count: form.description.length, max: 5000 })}
            </p>
          </div>

          {/* Duplicate Detection Warning (Agent A) */}
          {duplicates.length > 0 && !duplicatesDismissed && (
            <div className="rounded-lg border border-warning/30 bg-warning/10 p-3">
              <div className="flex items-start justify-between gap-2">
                <div className="space-y-1">
                  {duplicates.map((dup) => (
                    <p key={dup.id} className="text-sm text-warning-600 dark:text-warning-400">
                      {t('duplicate.warning', { title: dup.title, status: dup.status })}
                    </p>
                  ))}
                </div>
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  onPress={() => setDuplicatesDismissed(true)}
                  aria-label={t('duplicate.dismiss')}
                >
                  <X className="w-4 h-4" />
                </Button>
              </div>
            </div>
          )}

          {/* Type & Commitment */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Select
              label={t('form.type_label')}
              selectedKeys={[form.type]}
              onChange={(e) => updateField('type', e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
            >
              {JOB_TYPES.map((type) => (
                <SelectItem key={type}>{t(`type.${type}`)}</SelectItem>
              ))}
            </Select>

            <Select
              label={t('form.commitment_label')}
              selectedKeys={[form.commitment]}
              onChange={(e) => updateField('commitment', e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
            >
              {COMMITMENT_TYPES.map((type) => (
                <SelectItem key={type}>{t(`commitment.${type}`)}</SelectItem>
              ))}
            </Select>
          </div>

          {/* Category */}
          <Input
            label={t('form.category_label')}
            placeholder={t('form.category_placeholder')}
            value={form.category}
            onChange={(e) => updateField('category', e.target.value)}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />

          {/* Location & Remote */}
          <div className="space-y-3">
            <Input
              label={t('form.location_label')}
              placeholder={t('form.location_placeholder')}
              value={form.location}
              onChange={(e) => updateField('location', e.target.value)}
              isDisabled={form.is_remote}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            <Switch
              isSelected={form.is_remote}
              onValueChange={(v) => updateField('is_remote', v)}
              classNames={{
                label: 'text-theme-primary text-sm',
              }}
            >
              <div>
                <p className="text-sm text-theme-primary">{t('form.is_remote_label')}</p>
                <p className="text-xs text-theme-subtle">{t('form.is_remote_description')}</p>
              </div>
            </Switch>
          </div>

          {/* Skills */}
          <div>
            <Input
              label={t('form.skills_label')}
              placeholder={t('form.skills_placeholder')}
              description={t('form.skills_hint')}
              value={form.skills_required}
              onChange={(e) => updateField('skills_required', e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            {skillsArray.length > 0 && (
              <div className="flex flex-wrap gap-1.5 mt-2">
                {skillsArray.map((skill, idx) => (
                  <Chip key={idx} size="sm" variant="flat" color="primary" className="bg-primary/10 text-primary">
                    {skill}
                  </Chip>
                ))}
              </div>
            )}
          </div>

          {/* Hours per Week */}
          <Input
            type="number"
            label={t('form.hours_label')}
            placeholder={t('form.hours_placeholder')}
            value={form.hours_per_week}
            onChange={(e) => updateField('hours_per_week', e.target.value)}
            min="0"
            step="0.5"
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />

          {/* Time Credits (only for timebank type) */}
          {form.type === 'timebank' && (
            <Input
              type="number"
              label={t('form.time_credits_label')}
              placeholder={t('form.time_credits_placeholder')}
              value={form.time_credits}
              onChange={(e) => updateField('time_credits', e.target.value)}
              min="0"
              step="0.5"
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          )}

          {/* Contact info */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              type="email"
              label={t('form.contact_email_label')}
              placeholder={t('form.contact_email_placeholder')}
              value={form.contact_email}
              onChange={(e) => updateField('contact_email', e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            <Input
              type="tel"
              label={t('form.contact_phone_label')}
              placeholder={t('form.contact_phone_placeholder')}
              value={form.contact_phone}
              onChange={(e) => updateField('contact_phone', e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          {/* Deadline */}
          <Input
            type="date"
            label={t('form.deadline_label')}
            value={form.deadline}
            onChange={(e) => updateField('deadline', e.target.value)}
            isInvalid={!!errors.deadline}
            errorMessage={errors.deadline}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />

          {/* J9: Salary/Compensation + EU Pay Transparency */}
          {form.type === 'paid' && (
            <div className="space-y-4 pt-2">
              <h3 className="text-sm font-semibold text-theme-primary">{t('form.salary_section')}</h3>

              {/* Negotiable toggle first so it gates the required fields */}
              <Switch
                isSelected={form.salary_negotiable}
                onValueChange={(v) => {
                  updateField('salary_negotiable', v);
                  // Clear salary range error when negotiable is checked
                  if (v && errors.salary_range) {
                    setErrors((prev) => {
                      const next = { ...prev };
                      delete next.salary_range;
                      return next;
                    });
                  }
                }}
                classNames={{ label: 'text-theme-primary text-sm' }}
              >
                <p className="text-sm text-theme-primary">{t('form.salary_negotiable_label')}</p>
              </Switch>

              {/* Salary range fields — required when NOT negotiable */}
              <div className="space-y-2">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Input
                    type="number"
                    label={`${t('form.salary_min_label')}${!form.salary_negotiable ? ' *' : ''}`}
                    value={form.salary_min}
                    onChange={(e) => updateField('salary_min', e.target.value)}
                    min="0"
                    step="100"
                    isInvalid={!!errors.salary_range && !form.salary_negotiable}
                    isDisabled={form.salary_negotiable}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                  <Input
                    type="number"
                    label={`${t('form.salary_max_label')}${!form.salary_negotiable ? ' *' : ''}`}
                    value={form.salary_max}
                    onChange={(e) => updateField('salary_max', e.target.value)}
                    min="0"
                    step="100"
                    isInvalid={!!errors.salary_range && !form.salary_negotiable}
                    isDisabled={form.salary_negotiable}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                </div>
                {errors.salary_range && (
                  <p className="text-xs text-danger">{errors.salary_range}</p>
                )}
                {/* EU Pay Transparency info chip */}
                <Chip
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<Info size={12} aria-hidden="true" />}
                  className="text-xs"
                >
                  {t('form.salary_transparency_hint', 'Required under EU Pay Transparency Directive (June 2026)')}
                </Chip>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Select
                  label={t('form.salary_type_label')}
                  selectedKeys={form.salary_type ? [form.salary_type] : []}
                  onChange={(e) => updateField('salary_type', e.target.value)}
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    value: 'text-theme-primary',
                  }}
                >
                  <SelectItem key="hourly">{t('salary.hourly')}</SelectItem>
                  <SelectItem key="annual">{t('salary.annual')}</SelectItem>
                  <SelectItem key="time_credits">{t('salary.time_credits')}</SelectItem>
                </Select>
                <Input
                  label={t('form.salary_currency_label')}
                  placeholder={t('form.salary_currency_placeholder')}
                  value={form.salary_currency}
                  onChange={(e) => updateField('salary_currency', e.target.value)}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />
              </div>
            </div>
          )}

          {/* Feature 2: Salary Benchmark widget */}
          {benchmark && (
            <div className="flex items-center gap-3 bg-primary/5 rounded-lg p-3">
              <TrendingUp className="w-4 h-4 text-primary shrink-0" aria-hidden="true" />
              <p className="text-xs text-theme-primary flex-1">
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
              <Button
                size="sm"
                variant="flat"
                color="primary"
                className="shrink-0 text-xs"
                onPress={() => {
                  updateField('salary_min', String(benchmark.salary_min));
                  updateField('salary_max', String(benchmark.salary_max));
                  if (benchmark.currency) updateField('salary_currency', benchmark.currency);
                  if (benchmark.salary_type) updateField('salary_type', benchmark.salary_type);
                  toast.success(t('salary_benchmark.applied'));
                }}
              >
                {t('salary_benchmark.apply')}
              </Button>
            </div>
          )}

          {/* Blind Hiring Toggle (Agent C) */}
          <div className="flex items-start gap-3 p-4 rounded-xl bg-theme-elevated border border-theme-default">
            <div className="w-10 h-10 rounded-lg bg-violet-500/20 flex items-center justify-center flex-shrink-0">
              <EyeOff className="w-5 h-5 text-violet-400" aria-hidden="true" />
            </div>
            <div className="flex-1">
              <Switch
                isSelected={blindHiring}
                onValueChange={setBlindHiring}
                classNames={{ label: 'text-theme-primary text-sm' }}
              >
                <p className="text-sm font-medium text-theme-primary">{t('blind_hiring.label')}</p>
              </Switch>
              <p className="text-xs text-theme-muted mt-1">{t('blind_hiring.description')}</p>
            </div>
          </div>

          {/* Employer Branding section */}
          <div className="border border-theme-default rounded-xl overflow-hidden">
            <button
              type="button"
              className="w-full flex items-center justify-between p-4 text-left bg-theme-elevated hover:bg-theme-hover transition-colors"
              onClick={() => setBrandingOpen((o) => !o)}
              aria-expanded={brandingOpen}
            >
              <span className="font-semibold text-theme-primary text-sm">{t('branding.section')}</span>
              {brandingOpen
                ? <ChevronUp size={16} className="text-theme-subtle" aria-hidden="true" />
                : <ChevronDown size={16} className="text-theme-subtle" aria-hidden="true" />
              }
            </button>
            {brandingOpen && (
              <div className="p-4 space-y-4 border-t border-theme-default">
                {/* Tagline */}
                <Input
                  label={t('branding.tagline_label')}
                  placeholder={t('branding.tagline_placeholder')}
                  value={tagline}
                  onChange={(e) => setTagline(e.target.value.slice(0, 160))}
                  maxLength={160}
                  description={`${tagline.length}/160`}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />

                {/* Culture Video URL */}
                <Input
                  type="url"
                  label={t('branding.video_label')}
                  placeholder="https://www.youtube.com/watch?v=..."
                  value={videoUrl}
                  onChange={(e) => setVideoUrl(e.target.value)}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />

                {/* Company Size */}
                <Select
                  label={t('branding.size_label')}
                  selectedKeys={companySize ? [companySize] : []}
                  onChange={(e) => setCompanySize(e.target.value)}
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    value: 'text-theme-primary',
                  }}
                >
                  {COMPANY_SIZE_OPTIONS.map((s) => (
                    <SelectItem key={s}>{s}</SelectItem>
                  ))}
                </Select>

                {/* Benefits tag input */}
                <div>
                  <Input
                    label={t('branding.benefits_label')}
                    placeholder={t('branding.benefits_placeholder')}
                    value={benefitInput}
                    onChange={(e) => setBenefitInput(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        const val = benefitInput.trim();
                        if (val && !benefits.includes(val)) {
                          setBenefits((prev) => [...prev, val]);
                        }
                        setBenefitInput('');
                      }
                    }}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                  {benefits.length > 0 && (
                    <div className="flex flex-wrap gap-1.5 mt-2">
                      {benefits.map((benefit, idx) => (
                        <Chip
                          key={idx}
                          size="sm"
                          variant="flat"
                          color="success"
                          onClose={() => setBenefits((prev) => prev.filter((_, i) => i !== idx))}
                        >
                          {benefit}
                        </Chip>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Hiring Team section (only shown when editing or after creation) */}
          {(isEditing || createdJobId) ? (
            <div className="border border-theme-default rounded-xl overflow-hidden">
              <button
                type="button"
                className="w-full flex items-center justify-between p-4 text-left bg-theme-elevated hover:bg-theme-hover transition-colors"
                onClick={() => setTeamOpen((o) => !o)}
                aria-expanded={teamOpen}
              >
                <span className="font-semibold text-theme-primary text-sm flex items-center gap-2">
                  <Users size={15} aria-hidden="true" />
                  {t('team.title', 'Hiring Team')}
                  {teamMembers.length > 0 && (
                    <Chip size="sm" variant="flat" color="primary" className="ml-1">{teamMembers.length}</Chip>
                  )}
                </span>
                {teamOpen
                  ? <ChevronUp size={16} className="text-theme-subtle" aria-hidden="true" />
                  : <ChevronDown size={16} className="text-theme-subtle" aria-hidden="true" />
                }
              </button>
              {teamOpen && (
                <div className="p-4 space-y-4 border-t border-theme-default">
                  {/* Current team members as avatar + name chips */}
                  {teamMembers.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {teamMembers.map((member) => (
                        <Chip
                          key={member.id}
                          variant="flat"
                          color="default"
                          size="lg"
                          avatar={
                            <Avatar
                              src={member.avatar_url || undefined}
                              name={member.name}
                              size="sm"
                            />
                          }
                          onClose={() => void handleRemoveTeamMember(member.id)}
                          classNames={{
                            base: 'bg-theme-elevated border border-theme-default',
                            content: 'text-theme-primary',
                          }}
                        >
                          <span className="flex flex-col leading-tight">
                            <span className="text-sm font-medium">{member.name}</span>
                            <span className="text-xs text-theme-subtle">{t(`team.role_${member.role}`, member.role)}</span>
                          </span>
                        </Chip>
                      ))}
                    </div>
                  )}

                  {/* Add Member button */}
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => setAddMemberModalOpen(true)}
                  >
                    {t('team.add_member', 'Add Member')}
                  </Button>
                </div>
              )}
            </div>
          ) : (
            /* Hint for new jobs that team is available after save */
            <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated border border-theme-default">
              <Users size={16} className="text-theme-subtle shrink-0" aria-hidden="true" />
              <p className="text-sm text-theme-subtle">
                {t('team.title', 'Hiring Team')} — {t('team.edit_only', 'Available after saving the job')}
              </p>
            </div>
          )}

          {/* Feature 3: Save as Template button */}
          {isAuthenticated && (
            <div className="flex justify-start pt-2">
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                startContent={<FileText className="w-3.5 h-3.5" aria-hidden="true" />}
                onPress={() => setSaveTemplateOpen(true)}
              >
                {t('templates.save', 'Save as Template')}
              </Button>
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center justify-end gap-3 pt-4">
            <Button
              variant="flat"
              className="text-theme-muted"
              onPress={() => navigate(isEditing ? tenantPath(`/jobs/${id}`) : tenantPath('/jobs'))}
            >
              {t('form.cancel')}
            </Button>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              startContent={<Eye className="w-4 h-4" aria-hidden="true" />}
              onPress={() => setPreviewOpen(true)}
              isDisabled={!form.title.trim()}
            >
              {t('preview.button')}
            </Button>
            {!isEditing && (
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                onPress={async () => {
                  if (!validate()) return;
                  setIsSubmitting(true);
                  try {
                    const payload: Record<string, unknown> = {
                      title: form.title.trim(),
                      description: form.description.trim(),
                      type: form.type,
                      commitment: form.commitment,
                      is_remote: form.is_remote,
                      status: 'draft',
                    };
                    if (form.category.trim()) payload.category = form.category.trim();
                    if (form.location.trim()) payload.location = form.location.trim();
                    if (form.skills_required.trim()) payload.skills_required = form.skills_required.trim();
                    if (form.hours_per_week) payload.hours_per_week = parseFloat(form.hours_per_week);
                    if (form.time_credits) payload.time_credits = parseFloat(form.time_credits);
                    if (form.contact_email.trim()) payload.contact_email = form.contact_email.trim();
                    if (form.contact_phone.trim()) payload.contact_phone = form.contact_phone.trim();
                    if (form.deadline) payload.deadline = form.deadline;
                    if (form.salary_min) payload.salary_min = parseFloat(form.salary_min.replace(/[,\s]/g, ''));
                    if (form.salary_max) payload.salary_max = parseFloat(form.salary_max.replace(/[,\s]/g, ''));
                    if (form.salary_type) payload.salary_type = form.salary_type;
                    if (form.salary_currency.trim()) payload.salary_currency = form.salary_currency.trim();
                    payload.salary_negotiable = form.salary_negotiable;
                    if (tagline.trim()) payload.tagline = tagline.trim();
                    if (videoUrl.trim()) payload.video_url = videoUrl.trim();
                    if (companySize) payload.company_size = companySize;
                    if (benefits.length > 0) payload.benefits = JSON.stringify(benefits);
                    const response = await api.post('/v2/jobs', payload);
                    if (response.success) {
                      formDirtyRef.current = false;
                      toastRef.current.success(tRef.current('form.draft_saved', 'Draft saved'));
                      const newId = (response.data as Record<string, unknown>)?.id;
                      if (newId) {
                        setCreatedJobId(String(newId));
                        navigate(tenantPath(`/jobs/${newId}`));
                      } else {
                        navigate(tenantPath('/jobs'));
                      }
                    } else {
                      toastRef.current.error(response.error || tRef.current('form.create_error'));
                    }
                  } catch (err) {
                    logError('Failed to save draft', err);
                    toastRef.current.error(tRef.current('form.create_error'));
                  } finally {
                    setIsSubmitting(false);
                  }
                }}
                isLoading={isSubmitting}
                isDisabled={!form.title.trim() || !form.description.trim()}
              >
                {t('form.save_draft', 'Save as Draft')}
              </Button>
            )}
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleSubmit}
              isLoading={isSubmitting}
              isDisabled={isSubmitting || !form.title.trim() || !form.description.trim()}
            >
              {isSubmitting
                ? (isEditing ? t('form.updating') : t('form.creating'))
                : (isEditing ? t('form.submit_update') : t('form.submit_create'))}
            </Button>
          </div>
        </div>
      </GlassCard>

      {/* Save as Template Modal */}
      <Modal
        isOpen={saveTemplateOpen}
        onClose={() => setSaveTemplateOpen(false)}
        placement="center"
        size="sm"
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('templates.save_title', 'Save Template')}
          </ModalHeader>
          <ModalBody className="space-y-4">
            <Input
              label={t('templates.name_label', 'Template Name')}
              placeholder={t('templates.name_placeholder', 'Enter a name for this template')}
              value={templateName}
              onChange={(e) => setTemplateName(e.target.value)}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            <Switch
              isSelected={templateIsPublic}
              onValueChange={setTemplateIsPublic}
              classNames={{ label: 'text-theme-primary text-sm' }}
            >
              {t('template.share_with_team', 'Share with team (public template)')}
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="text-theme-muted"
              onPress={() => setSaveTemplateOpen(false)}
            >
              {t('form.cancel')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={() => void handleSaveTemplate()}
              isLoading={isSavingTemplate}
              isDisabled={!templateName.trim()}
            >
              {isSavingTemplate
                ? t('template.saving', 'Saving...')
                : t('templates.save_button', 'Save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Job Preview Modal */}
      <Modal
        isOpen={previewOpen}
        onClose={() => setPreviewOpen(false)}
        placement="center"
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('preview.title')}
          </ModalHeader>
          <ModalBody className="space-y-5 pb-6">
            {/* Title */}
            <h2 className="text-2xl font-bold text-theme-primary">
              {form.title || t('form.title_placeholder')}
            </h2>

            {/* Type + Commitment chips */}
            <div className="flex flex-wrap gap-2">
              <Chip size="sm" variant="flat" color="primary">{t(`type.${form.type}`)}</Chip>
              <Chip size="sm" variant="flat" color="secondary">{t(`commitment.${form.commitment}`)}</Chip>
              {form.is_remote && (
                <Chip size="sm" variant="flat" color="success" startContent={<MapPin className="w-3 h-3" />}>
                  {t('remote')}
                </Chip>
              )}
            </div>

            {/* Location */}
            {form.location && !form.is_remote && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <MapPin className="w-4 h-4" aria-hidden="true" />
                {form.location}
              </div>
            )}

            {/* Salary */}
            {(form.salary_min || form.salary_max || form.salary_negotiable) && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <DollarSign className="w-4 h-4" aria-hidden="true" />
                {form.salary_negotiable
                  ? t('salary.negotiable')
                  : `${form.salary_currency || ''} ${form.salary_min ? Number(form.salary_min).toLocaleString() : '?'} - ${form.salary_max ? Number(form.salary_max).toLocaleString() : '?'}${form.salary_type ? ` / ${t(`salary.${form.salary_type}`)}` : ''}`
                }
              </div>
            )}

            {/* Description */}
            {form.description && (
              <div className="space-y-2">
                <h3 className="text-sm font-semibold text-theme-primary">{t('detail.about')}</h3>
                <div className="text-sm text-theme-muted whitespace-pre-wrap leading-relaxed">
                  {form.description}
                </div>
              </div>
            )}

            {/* Skills */}
            {skillsArray.length > 0 && (
              <div className="space-y-2">
                <h3 className="text-sm font-semibold text-theme-primary">{t('detail.skills_required')}</h3>
                <div className="flex flex-wrap gap-1.5">
                  {skillsArray.map((skill, idx) => (
                    <Chip key={idx} size="sm" variant="flat" color="primary" className="bg-primary/10 text-primary">
                      {skill}
                    </Chip>
                  ))}
                </div>
              </div>
            )}

            {/* Deadline */}
            {form.deadline && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <Calendar className="w-4 h-4" aria-hidden="true" />
                {t('deadline_label')}: {form.deadline ? new Date(form.deadline).toLocaleDateString() : ''}
              </div>
            )}

            {/* Posted by */}
            {user?.name && (
              <p className="text-sm text-theme-subtle">
                {t('preview.posted_by')}: {user.name}
              </p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="text-theme-muted" onPress={() => setPreviewOpen(false)}>
              {t('form.cancel')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Add Team Member Modal */}
      <Modal
        isOpen={addMemberModalOpen}
        onClose={() => {
          setAddMemberModalOpen(false);
          setTeamSearchInput('');
          setMemberSearchResults([]);
          setNewMemberRole('reviewer');
        }}
        placement="center"
        size="md"
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('team.add_member', 'Add Member')}
          </ModalHeader>
          <ModalBody className="space-y-4">
            {/* Search input */}
            <Input
              label={t('team.search_placeholder', 'Search members...')}
              placeholder={t('team.search_placeholder', 'Search members...')}
              value={teamSearchInput}
              onChange={(e) => handleMemberSearch(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />

            {/* Role selector */}
            <Select
              label={t('team.role', 'Role')}
              selectedKeys={[newMemberRole]}
              onChange={(e) => setNewMemberRole(e.target.value)}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
            >
              <SelectItem key="reviewer">{t('team.role_reviewer', 'Reviewer')}</SelectItem>
              <SelectItem key="interviewer">{t('team.role_interviewer', 'Interviewer')}</SelectItem>
              <SelectItem key="hiring_manager">{t('team.role_hiring_manager', 'Hiring Manager')}</SelectItem>
            </Select>

            {/* Search results */}
            {isMemberSearching && (
              <p className="text-sm text-theme-subtle">{t('detail.loading', 'Loading...')}</p>
            )}
            {memberSearchResults.length > 0 && (
              <div className="space-y-2 max-h-48 overflow-y-auto">
                {memberSearchResults.map((member) => (
                  <div
                    key={member.id}
                    className={`flex items-center gap-3 p-2 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors ${isAddingTeamMember ? 'opacity-50 pointer-events-none' : 'cursor-pointer'}`}
                    onClick={() => void handleAddTeamMember(member.id)}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); void handleAddTeamMember(member.id); } }}
                  >
                    <Avatar
                      src={member.avatar_url || undefined}
                      name={member.name}
                      size="sm"
                    />
                    <span className="text-sm font-medium text-theme-primary flex-1">{member.name}</span>
                    <Plus className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                  </div>
                ))}
              </div>
            )}
            {teamSearchInput.trim() && !isMemberSearching && memberSearchResults.length === 0 && (
              <p className="text-sm text-theme-subtle italic">{t('empty_search', 'No members match your search')}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="text-theme-muted"
              onPress={() => {
                setAddMemberModalOpen(false);
                setTeamSearchInput('');
                setMemberSearchResults([]);
                setNewMemberRole('reviewer');
              }}
            >
              {t('form.cancel')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CreateJobPage;
