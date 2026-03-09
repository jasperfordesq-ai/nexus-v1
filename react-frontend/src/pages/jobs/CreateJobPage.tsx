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

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
  Chip,
} from '@heroui/react';
import {
  Briefcase,
  ArrowLeft,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
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

  const isEditing = Boolean(id);
  usePageTitle(isEditing ? t('form.edit_title') : t('form.create_title'));

  const [form, setForm] = useState<JobFormData>(INITIAL_FORM);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

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
            deadline: v.deadline ? String(v.deadline).split('T')[0] : '',
            salary_min: v.salary_min != null ? String(v.salary_min) : '',
            salary_max: v.salary_max != null ? String(v.salary_max) : '',
            salary_type: str(v.salary_type),
            salary_currency: str(v.salary_currency),
            salary_negotiable: Boolean(v.salary_negotiable),
          });
        } else {
          toast.error(t('detail.not_found'));
          navigate(tenantPath('/jobs'));
        }
      } catch (err) {
        logError('Failed to load vacancy for editing', err);
        toast.error(t('detail.unable_to_load'));
        navigate(tenantPath('/jobs'));
      } finally {
        setIsLoading(false);
      }
    };

    loadVacancy();
  }, [id, isEditing, navigate, tenantPath, toast, t]);

  const updateField = useCallback(<K extends keyof JobFormData>(field: K, value: JobFormData[K]) => {
    setForm((prev) => ({ ...prev, [field]: value }));
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

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

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

      // J9: Salary fields
      if (form.salary_min) payload.salary_min = parseFloat(form.salary_min);
      if (form.salary_max) payload.salary_max = parseFloat(form.salary_max);
      if (form.salary_type) payload.salary_type = form.salary_type;
      if (form.salary_currency.trim()) payload.salary_currency = form.salary_currency.trim();
      payload.salary_negotiable = form.salary_negotiable;

      let response;
      if (isEditing && id) {
        response = await api.put(`/v2/jobs/${id}`, payload);
      } else {
        response = await api.post('/v2/jobs', payload);
      }

      if (response.success) {
        toast.success(isEditing ? t('form.update_success') : t('form.create_success'));
        const newId = isEditing ? id : (response.data as Record<string, unknown>)?.id;
        if (newId) {
          navigate(tenantPath(`/jobs/${newId}`));
        } else {
          navigate(tenantPath('/jobs'));
        }
      } else {
        toast.error(response.error || (isEditing ? t('form.update_error') : t('form.create_error')));
      }
    } catch (err) {
      logError('Failed to save vacancy', err);
      toast.error(isEditing ? t('form.update_error') : t('form.create_error'));
    } finally {
      setIsSubmitting(false);
    }
  };

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
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3 mb-6">
          <Briefcase className="w-7 h-7 text-blue-400" aria-hidden="true" />
          {isEditing ? t('form.edit_title') : t('form.create_title')}
        </h1>

        <div className="space-y-5">
          {/* Title */}
          <Input
            label={t('form.title_label')}
            placeholder={t('form.title_placeholder')}
            value={form.title}
            onChange={(e) => updateField('title', e.target.value)}
            isRequired
            isInvalid={!!errors.title}
            errorMessage={errors.title}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />

          {/* Description */}
          <Textarea
            label={t('form.description_label')}
            placeholder={t('form.description_placeholder')}
            value={form.description}
            onValueChange={(v) => updateField('description', v)}
            isRequired
            isInvalid={!!errors.description}
            errorMessage={errors.description}
            minRows={5}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />

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

          {/* J9: Salary/Compensation */}
          {form.type === 'paid' && (
            <div className="space-y-4 pt-2">
              <h3 className="text-sm font-semibold text-theme-primary">{t('form.salary_section')}</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  type="number"
                  label={t('form.salary_min_label')}
                  value={form.salary_min}
                  onChange={(e) => updateField('salary_min', e.target.value)}
                  min="0"
                  step="100"
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />
                <Input
                  type="number"
                  label={t('form.salary_max_label')}
                  value={form.salary_max}
                  onChange={(e) => updateField('salary_max', e.target.value)}
                  min="0"
                  step="100"
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />
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
              <Switch
                isSelected={form.salary_negotiable}
                onValueChange={(v) => updateField('salary_negotiable', v)}
                classNames={{ label: 'text-theme-primary text-sm' }}
              >
                <p className="text-sm text-theme-primary">{t('form.salary_negotiable_label')}</p>
              </Switch>
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
                    if (form.salary_min) payload.salary_min = parseFloat(form.salary_min);
                    if (form.salary_max) payload.salary_max = parseFloat(form.salary_max);
                    if (form.salary_type) payload.salary_type = form.salary_type;
                    if (form.salary_currency.trim()) payload.salary_currency = form.salary_currency.trim();
                    payload.salary_negotiable = form.salary_negotiable;
                    const response = await api.post('/v2/jobs', payload);
                    if (response.success) {
                      toast.success(t('form.draft_saved', 'Draft saved'));
                      const newId = (response.data as Record<string, unknown>)?.id;
                      if (newId) {
                        navigate(tenantPath(`/jobs/${newId}`));
                      } else {
                        navigate(tenantPath('/jobs'));
                      }
                    } else {
                      toast.error(response.error || t('form.create_error'));
                    }
                  } catch (err) {
                    logError('Failed to save draft', err);
                    toast.error(t('form.create_error'));
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
              isDisabled={!form.title.trim() || !form.description.trim()}
            >
              {isSubmitting
                ? (isEditing ? t('form.updating') : t('form.creating'))
                : (isEditing ? t('form.submit_update') : t('form.submit_create'))}
            </Button>
          </div>
        </div>
      </GlassCard>
    </div>
  );
}

export default CreateJobPage;
