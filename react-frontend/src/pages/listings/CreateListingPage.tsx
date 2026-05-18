// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Listing Page
 */

import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem, Radio, RadioGroup, Chip } from '@heroui/react';
import Save from 'lucide-react/icons/save';
import Clock from 'lucide-react/icons/clock';
import Tag from 'lucide-react/icons/tag';
import FileText from 'lucide-react/icons/file-text';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import ImagePlus from 'lucide-react/icons/image-plus';
import X from 'lucide-react/icons/x';
import MapPin from 'lucide-react/icons/map-pin';
import Monitor from 'lucide-react/icons/monitor';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import HelpCircle from 'lucide-react/icons/circle-help';
import Sparkles from 'lucide-react/icons/sparkles';
import Info from 'lucide-react/icons/info';
import { SkillTagsInput } from '@/components/listings/SkillTagsInput';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';
import type { Listing, Category } from '@/types/api';

interface FormData {
  title: string;
  description: string;
  type: 'offer' | 'request';
  service_type: 'physical_only' | 'remote_only' | 'hybrid' | 'location_dependent';
  category_id: string;
  hours_estimate: string;
  location: string;
  latitude?: number;
  longitude?: number;
  skill_tags: string[];
  experience_level?: string;
  equipment_provided?: string;
  accessibility_notes?: string;
}

const initialFormData: FormData = {
  title: '',
  description: '',
  type: 'offer',
  service_type: 'hybrid',
  category_id: '',
  hours_estimate: '1',
  location: '',
  skill_tags: [],
};

export function CreateListingPage() {
  const { t } = useTranslation('listings');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath, listingConfig } = useTenant();
  const { user } = useAuth();
  const toast = useToast();
  const isEditing = !!id;
  const pageTitle = isEditing ? t('page_meta.edit.title') : t('page_meta.create.title');
  usePageTitle(pageTitle);

  const [formData, setFormData] = useState<FormData>(() => ({
    ...initialFormData,
    location: user?.location ?? '',
    latitude: user?.latitude ?? undefined,
    longitude: user?.longitude ?? undefined,
  }));
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const editAbortRef = useRef<AbortController | null>(null);
  // Stable ref so async handlers always use the latest t() without re-creation
  const tRef = useRef(t);
  tRef.current = t;
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null);
  const [removeExistingImage, setRemoveExistingImage] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const selectedCategoryName = categories.find((c) => c.id.toString() === formData.category_id)?.name;

  // Cleanup object URL on unmount or when preview changes
  useEffect(() => {
    return () => {
      if (imagePreview) URL.revokeObjectURL(imagePreview);
    };
  }, [imagePreview]);

  useEffect(() => {
    async function loadCategories() {
      try {
        const response = await api.get<Category[]>('/v2/categories?type=listing');
        if (response.success && response.data) {
          setCategories(response.data);
        }
      } catch (error) {
        logError('Failed to load categories', error);
      }
    }

    async function loadListing() {
      if (!id) return;

      editAbortRef.current?.abort();
      const controller = new AbortController();
      editAbortRef.current = controller;

      try {
        setIsLoading(true);
        const response = await api.get<Listing>(`/v2/listings/${id}`);
        if (controller.signal.aborted) return;
        if (response.success && response.data) {
          const listing = response.data;
          setFormData((prev) => ({
            ...prev,
            title: listing.title,
            description: listing.description,
            type: listing.type,
            service_type: listing.service_type || 'hybrid',
            category_id: listing.category_id?.toString() || '',
            hours_estimate: (listing.hours_estimate ?? listing.estimated_hours ?? 1).toString(),
            skill_tags: Array.isArray(listing.skill_tags) ? listing.skill_tags : [],
            // location/latitude/longitude are always sourced from the user's profile
            // (set during state initialisation above); do not overwrite with stored
            // listing values here.
          }));
          if (listing.image_url) {
            setExistingImageUrl(listing.image_url);
          }
        }
      } catch (error) {
        if (controller.signal.aborted) return;
        logError('Failed to load listing', error);
        toast.error(t('form.load_error'));
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadCategories();
    if (isEditing) {
      loadListing();
    }
    return () => { editAbortRef.current?.abort(); };
  }, [id, isEditing, t, toast]);

  // True when the title is just the selected category name, or a generic
  // "I can help with X" / "Help with X" template. Drives a non-blocking
  // nudge under the title input.
  const isGenericTitle = (() => {
    const title = formData.title.trim().toLowerCase();
    if (!title || title.length < 3) return false;
    const stripped = title
      .replace(/^(i can help with|help with|looking for help with|need help with)\s+/i, '')
      .trim();
    const categoryName = selectedCategoryName?.toLowerCase().trim() || '';
    if (categoryName && (title === categoryName || stripped === categoryName)) return true;
    // Bare category-style titles with no qualifier beyond a couple of words.
    return stripped.length > 0 && stripped !== title && stripped.split(/\s+/).length <= 2;
  })();

  function validateForm(): boolean {
    const newErrors: Partial<Record<keyof FormData, string>> = {};
    const minTitle = listingConfig['listing.min_title_length'] || 5;
    const minDesc = listingConfig['listing.min_description_length'] || 20;

    if (!formData.title.trim()) {
      newErrors.title = t('form.title_required');
    } else if (formData.title.length < minTitle) {
      newErrors.title = t('form.title_min_length');
    }

    if (!formData.description.trim()) {
      newErrors.description = t('form.description_required');
    } else if (formData.description.length < minDesc) {
      newErrors.description = t('form.description_min_length');
    }

    if (listingConfig['listing.require_category'] && !formData.category_id) {
      newErrors.category_id = t('form.category_required');
    }

    const hours = parseFloat(formData.hours_estimate);
    if (listingConfig['listing.require_hours_estimate'] && (isNaN(hours) || hours < 0.5 || hours > 100)) {
      newErrors.hours_estimate = t('form.hours_range');
    } else if (formData.hours_estimate && (isNaN(hours) || hours < 0.5 || hours > 100)) {
      newErrors.hours_estimate = t('form.hours_range');
    }

    if (formData.accessibility_notes && formData.accessibility_notes.length > 200) {
      newErrors.accessibility_notes = t('form.accessibility_max_length');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      setIsSubmitting(true);

      // Build enriched description with optional structured details
      let enrichedDescription = formData.description;
      const details: string[] = [];
      const tFn = tRef.current ?? t;
      if (formData.experience_level) {
        const experienceLabels: Record<string, string> = {
          beginner_friendly: tFn('form.experience_beginner'),
          some_experience: tFn('form.experience_some'),
          experienced: tFn('form.experience_experienced'),
          professional: tFn('form.experience_professional'),
        };
        details.push(`${tFn('form.experience_label')}: ${experienceLabels[formData.experience_level] || formData.experience_level}`);
      }
      if (formData.equipment_provided) {
        const equipmentLabels: Record<string, string> = {
          provided: tFn('form.equipment_provided_option'),
          partial: tFn('form.equipment_partial'),
          bring_own: tFn('form.equipment_bring_own'),
          not_applicable: tFn('form.equipment_na'),
        };
        details.push(`${tFn('form.equipment_label')}: ${equipmentLabels[formData.equipment_provided] || formData.equipment_provided}`);
      }
      if (formData.accessibility_notes) {
        details.push(`${tFn('form.accessibility_label')}: ${formData.accessibility_notes}`);
      }
      if (details.length > 0) {
        enrichedDescription += '\n\n---\n' + details.join('\n');
      }

      const payload = {
        title: formData.title,
        description: enrichedDescription,
        type: formData.type,
        location: formData.location,
        latitude: formData.latitude,
        longitude: formData.longitude,
        category_id: formData.category_id ? parseInt(formData.category_id, 10) : undefined,
        hours_estimate: parseFloat(formData.hours_estimate),
        service_type: formData.service_type,
      };

      let listingId = id;
      if (isEditing) {
        await api.put(`/v2/listings/${id}`, payload);
      } else {
        const response = await api.post<{ id: number }>('/v2/listings', payload);
        if (response.success && response.data) {
          listingId = String(response.data.id);
        }
      }

      if (listingId) {
        try {
          await api.put(`/v2/listings/${listingId}/tags`, { tags: formData.skill_tags });
        } catch (tagErr) {
          logError('Failed to save listing skill tags', tagErr);
          toast.warning(t('form.tags_save_failed'));
        }
      }

      // Upload image if selected
      if (imageFile && listingId) {
        try {
          await api.upload(`/v2/listings/${listingId}/image`, imageFile, 'image');
        } catch (imgErr) {
          logError('Failed to upload listing image', imgErr);
          toast.warning(t('form.image_upload_failed'));
        }
      } else if (removeExistingImage && listingId) {
        try {
          await api.delete(`/v2/listings/${listingId}/image`);
        } catch (imgErr) {
          logError('Failed to remove listing image', imgErr);
        }
      }

      toast.success(isEditing ? t('form.update_success') : t('form.create_success'));
      navigate(tenantPath(listingId ? `/listings/${listingId}` : '/listings'));
    } catch (error) {
      logError('Failed to save listing', error);
      toast.error(t('form.save_error_title'), t('form.save_error_subtitle'));
    } finally {
      setIsSubmitting(false);
    }
  }

  function updateField<K extends keyof FormData>(field: K, value: FormData[K]) {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  }

  async function handleGenerateDescription() {
    if (!formData.title.trim()) return;
    setIsGenerating(true);
    try {
      const response = await api.post<{ description: string }>('/v2/listings/generate-description', {
        title: formData.title,
        category: selectedCategoryName || '',
        type: formData.type,
        notes: formData.description, // Use existing description as context
      });
      if (response.success && response.data) {
        const desc = ((response.data as Record<string, unknown>)?.description ?? '') as string;
        if (desc) {
          updateField('description', desc);
          toast.success(t('form.ai_generated'));
        }
      }
    } catch (error) {
      logError('Failed to generate description', error);
      toast.error(t('form.ai_generate_failed'));
    } finally {
      setIsGenerating(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-5xl space-y-6"
    >
      <PageMeta title={pageTitle} noIndex />
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/listings') },
        { label: isEditing ? t('form.edit_title') : t('form.new_title') },
      ]} />

      <header className="overflow-hidden rounded-2xl border border-theme-default bg-theme-elevated">
        <div className="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
          <div className="max-w-2xl space-y-3">
            <Chip size="sm" variant="flat" color={formData.type === 'offer' ? 'success' : 'warning'} className="font-medium">
              {formData.type === 'offer' ? t('form.offer_badge') : t('form.request_badge')}
            </Chip>
            <div>
              <h1 className="text-3xl font-bold tracking-normal text-theme-primary sm:text-4xl">
                {isEditing ? t('form.edit_title') : t('form.create_title')}
              </h1>
              <p className="mt-2 text-sm leading-6 text-theme-muted sm:text-base">
                {t('form.create_intro')}
              </p>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3 text-sm sm:min-w-64">
            <div className="rounded-xl border border-theme-default bg-theme-surface px-4 py-3">
              <span className="block text-xs font-medium uppercase tracking-wide text-theme-subtle">{t('form.summary_type')}</span>
              <span className="mt-1 block font-semibold text-theme-primary">
                {formData.type === 'offer' ? t('form.offer_title') : t('form.request_title')}
              </span>
            </div>
            <div className="rounded-xl border border-theme-default bg-theme-surface px-4 py-3">
              <span className="block text-xs font-medium uppercase tracking-wide text-theme-subtle">{t('form.summary_category')}</span>
              <span className="mt-1 block truncate font-semibold text-theme-primary">
                {selectedCategoryName || t('form.summary_not_set')}
              </span>
            </div>
          </div>
        </div>
      </header>

      {/* Form */}
      <GlassCard className="p-5 sm:p-8">

        <form onSubmit={handleSubmit} className="space-y-8" noValidate>
          {/* Type Selection */}
          <section className="space-y-4">
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">{t('form.intent_section')}</h2>
              <p className="mt-1 text-sm text-theme-muted">{t('form.intent_section_hint')}</p>
            </div>
            <RadioGroup
              aria-label={t('form.type_question')}
              value={formData.type}
              onValueChange={(value) => updateField('type', value as 'offer' | 'request')}
              classNames={{ wrapper: 'gap-3 sm:flex-row' }}
            >
              <Radio
                value="offer"
                classNames={{
                  base: 'm-0 max-w-none rounded-xl border border-theme-default bg-theme-elevated p-4 data-[selected=true]:border-emerald-500 data-[selected=true]:bg-emerald-500/10 sm:flex-1',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-3">
                  <CheckCircle className="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" aria-hidden="true" />
                  <div>
                    <div className="font-semibold">{t('form.offer_title')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.offer_subtitle')}</div>
                  </div>
                </div>
              </Radio>
              <Radio
                value="request"
                classNames={{
                  base: 'm-0 max-w-none rounded-xl border border-theme-default bg-theme-elevated p-4 data-[selected=true]:border-amber-500 data-[selected=true]:bg-amber-500/10 sm:flex-1',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-3">
                  <HelpCircle className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
                  <div>
                    <div className="font-semibold">{t('form.request_title')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.request_subtitle')}</div>
                  </div>
                </div>
              </Radio>
            </RadioGroup>
          </section>

          <section className="space-y-4 border-t border-theme-default pt-8">
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">{t('form.essentials_section')}</h2>
              <p className="mt-1 text-sm text-theme-muted">{t('form.essentials_section_hint')}</p>
            </div>

          {/* Title */}
          <div>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={formData.title}
              onChange={(e) => updateField('title', e.target.value)}
              isRequired
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              maxLength={255}
              description={t('form.character_count', { count: formData.title.length, max: 255 })}
              startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
                description: 'text-theme-subtle',
              }}
            />
            {/* Soft nudge when the title is just the category name or a generic
                "I can help with X" template. Not blocking — listing still
                submits. Aimed at the SERP/discovery quality regression where
                titles like "I can help with Events" indexed as thin content. */}
            {isGenericTitle && (
              <p className="mt-2 flex items-start gap-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs leading-5 text-amber-700 dark:text-amber-300">
                <Info className="w-3.5 h-3.5 shrink-0 mt-0.5" aria-hidden="true" />
                <span>{t('form.title_too_generic_hint')}</span>
              </p>
            )}
          </div>

          {/* Description */}
          <div>
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
              minRows={6}
              isRequired
              isInvalid={!!errors.description}
              errorMessage={errors.description}
              maxLength={10000}
              description={t('form.character_count', { count: formData.description.length, max: 10000 })}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
                description: 'text-theme-subtle',
              }}
            />
            <div className="flex flex-col items-stretch gap-2 mt-2 sm:flex-row sm:items-center">
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<Sparkles className="w-3.5 h-3.5" aria-hidden="true" />}
                onPress={handleGenerateDescription}
                isLoading={isGenerating}
                isDisabled={!formData.title.trim() || isGenerating}
              >
                {isGenerating
                  ? t('form.ai_generating')
                  : t('form.ai_help_write')}
              </Button>
              {!formData.title.trim() && (
                <span className="text-xs text-theme-subtle">
                  {t('form.ai_enter_title_first')}
                </span>
              )}
            </div>
          </div>
          </section>

          {/* Optional Service Details */}
          <section className="space-y-4 border-t border-theme-default pt-8">
          <details className="group rounded-xl border border-theme-default bg-theme-surface p-4 open:bg-theme-elevated">
            <summary className="flex cursor-pointer select-none items-center justify-between gap-3 text-sm font-semibold text-theme-primary">
              <span className="flex items-center gap-2">
                <Info className="w-4 h-4" aria-hidden="true" />
                {t('form.service_details_toggle')}
              </span>
              <span className="text-xs font-medium text-theme-subtle group-open:hidden">{t('form.optional_label')}</span>
            </summary>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              {/* Experience Level */}
              <Select
                label={t('form.experience_label')}
                placeholder={t('form.experience_placeholder')}
                selectedKeys={formData.experience_level ? [formData.experience_level] : []}
                onChange={(e) => updateField('experience_level', e.target.value)}
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default',
                  value: 'text-theme-primary',
                  label: 'text-theme-muted',
                }}
              >
                <SelectItem key="beginner_friendly">
                  {t('form.experience_beginner')}
                </SelectItem>
                <SelectItem key="some_experience">
                  {t('form.experience_some')}
                </SelectItem>
                <SelectItem key="experienced">
                  {t('form.experience_experienced')}
                </SelectItem>
                <SelectItem key="professional">
                  {t('form.experience_professional')}
                </SelectItem>
              </Select>

              {/* Equipment/Tools */}
              <Select
                label={t('form.equipment_label')}
                placeholder={t('form.equipment_placeholder')}
                selectedKeys={formData.equipment_provided ? [formData.equipment_provided] : []}
                onChange={(e) => updateField('equipment_provided', e.target.value)}
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default',
                  value: 'text-theme-primary',
                  label: 'text-theme-muted',
                }}
              >
                <SelectItem key="provided">
                  {t('form.equipment_provided_option')}
                </SelectItem>
                <SelectItem key="partial">
                  {t('form.equipment_partial')}
                </SelectItem>
                <SelectItem key="bring_own">
                  {t('form.equipment_bring_own')}
                </SelectItem>
                <SelectItem key="not_applicable">
                  {t('form.equipment_na')}
                </SelectItem>
              </Select>

              {/* Accessibility Notes */}
              <Input
                label={t('form.accessibility_label')}
                placeholder={t('form.accessibility_placeholder')}
                value={formData.accessibility_notes || ''}
                onChange={(e) => updateField('accessibility_notes', e.target.value)}
                isInvalid={!!errors.accessibility_notes}
                errorMessage={errors.accessibility_notes}
                maxLength={200}
                description={t('form.character_count', { count: formData.accessibility_notes?.length || 0, max: 200 })}
                className="md:col-span-2"
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  description: 'text-theme-subtle',
                }}
              />
            </div>
          </details>
          </section>

          {/* Service Delivery Mode */}
          <section className="space-y-4 border-t border-theme-default pt-8">
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">{t('form.logistics_section')}</h2>
              <p className="mt-1 text-sm text-theme-muted">{t('form.logistics_section_hint')}</p>
            </div>
            <RadioGroup
              label={t('form.service_type_label')}
              value={formData.service_type}
              onValueChange={(value) => updateField('service_type', value as FormData['service_type'])}
              classNames={{
                label: 'text-sm font-medium text-theme-muted',
                wrapper: 'grid grid-cols-1 sm:grid-cols-2 gap-3',
              }}
            >
              <Radio
                value="physical_only"
                classNames={{
                  base: 'm-0 max-w-none p-3 border border-theme-default rounded-xl bg-theme-elevated data-[selected=true]:border-emerald-500 data-[selected=true]:bg-emerald-500/10',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-2">
                  <MapPin className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <div className="font-semibold text-sm">{t('form.service_type_physical')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.service_type_physical_hint')}</div>
                  </div>
                </div>
              </Radio>
              <Radio
                value="remote_only"
                classNames={{
                  base: 'm-0 max-w-none p-3 border border-theme-default rounded-xl bg-theme-elevated data-[selected=true]:border-blue-500 data-[selected=true]:bg-blue-500/10',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-2">
                  <Monitor className="w-4 h-4 text-[var(--color-info)] shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <div className="font-semibold text-sm">{t('form.service_type_remote')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.service_type_remote_hint')}</div>
                  </div>
                </div>
              </Radio>
              <Radio
                value="hybrid"
                classNames={{
                  base: 'm-0 max-w-none p-3 border border-theme-default rounded-xl bg-theme-elevated data-[selected=true]:border-teal-500 data-[selected=true]:bg-teal-500/10',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-2">
                  <ArrowRightLeft className="w-4 h-4 text-teal-500 shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <div className="font-semibold text-sm">{t('form.service_type_hybrid')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.service_type_hybrid_hint')}</div>
                  </div>
                </div>
              </Radio>
              <Radio
                value="location_dependent"
                classNames={{
                  base: 'm-0 max-w-none p-3 border border-theme-default rounded-xl bg-theme-elevated data-[selected=true]:border-slate-500 data-[selected=true]:bg-slate-500/10',
                  label: 'text-theme-primary',
                }}
              >
                <div className="flex items-start gap-2">
                  <HelpCircle className="w-4 h-4 text-slate-500 shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <div className="font-semibold text-sm">{t('form.service_type_depends')}</div>
                    <div className="text-xs leading-5 text-theme-subtle">{t('form.service_type_depends_hint')}</div>
                  </div>
                </div>
              </Radio>
            </RadioGroup>
          </section>

          <section className="space-y-4 border-t border-theme-default pt-8">
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">{t('form.organise_section')}</h2>
              <p className="mt-1 text-sm text-theme-muted">{t('form.organise_section_hint')}</p>
            </div>
            <div className="grid gap-5 md:grid-cols-2">
          {/* Category */}
          <div>
            <Select
              label={t('form.category_label')}
              placeholder={t('form.category_placeholder')}
              selectedKeys={formData.category_id ? [formData.category_id] : []}
              onChange={(e) => updateField('category_id', e.target.value)}
              isRequired
              isInvalid={!!errors.category_id}
              errorMessage={errors.category_id}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              popoverProps={{
                placement: 'bottom',
                shouldFlip: false,
                shouldBlockScroll: true,
                offset: 4,
                containerPadding: 8,
              }}
              listboxProps={{
                className: 'max-h-60 overflow-y-auto',
              }}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
                label: 'text-theme-muted',
                popoverContent: 'bg-theme-elevated border border-theme-default shadow-lg',
              }}
            >
              {categories.map((cat) => (
                <SelectItem key={cat.id.toString()}>{cat.name}</SelectItem>
              ))}
            </Select>
          </div>

          {/* Skill Tags */}
          <SkillTagsInput
            tags={formData.skill_tags}
            onChange={(tags) => setFormData((prev) => ({ ...prev, skill_tags: tags }))}
          />
            </div>
          </section>

          {/* Hours & Location */}
          <section className="space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <Input
                type="number"
                label={t('form.hours_estimated_label')}
                placeholder={t('form.hours_placeholder')}
                value={formData.hours_estimate}
                onChange={(e) => updateField('hours_estimate', e.target.value)}
                min={0.5}
                max={100}
                step={0.5}
                isInvalid={!!errors.hours_estimate}
                errorMessage={errors.hours_estimate}
                startContent={<Clock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <Input
                label={t('form.location_optional_label')}
                value={formData.location}
                isDisabled
                description={t('form.location_from_profile')}
                startContent={<MapPin className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  description: 'text-theme-subtle',
                }}
              />
            </div>
          </div>
          </section>

          {/* Image Upload */}
          <section className="space-y-4 border-t border-theme-default pt-8">
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">{t('form.media_section')}</h2>
              <p className="mt-1 text-sm text-theme-muted">{t('form.media_section_hint')}</p>
            </div>
            {(imagePreview || existingImageUrl) ? (
              <div className="relative inline-block">
                <img
                  src={imagePreview || resolveAssetUrl(existingImageUrl) || ''}
                  alt={t('form.image_preview_alt')}
                  className="h-56 w-full max-w-md rounded-xl border border-theme-default object-cover"
                />
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={() => { setImageFile(null); setImagePreview(null); if (existingImageUrl) setRemoveExistingImage(true); setExistingImageUrl(null); }}
                  className="absolute right-2 top-2 h-auto w-auto min-w-0 rounded-full bg-black/60 p-1.5 text-white transition-colors hover:bg-black/80"
                  aria-label={t('form.aria_remove_image')}
                >
                  <X className="w-4 h-4" aria-hidden="true" />
                </Button>
              </div>
            ) : (
              <label className="flex h-44 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-theme-default bg-theme-elevated transition-colors hover:border-teal-500/60 hover:bg-theme-hover focus-within:border-teal-500 focus-within:ring-2 focus-within:ring-teal-500/30">
                <ImagePlus className="mb-2 h-8 w-8 text-theme-subtle" aria-hidden="true" />
                <span className="text-sm text-theme-muted">{t('form.image_upload_hint')}</span>
                <span className="text-xs text-theme-subtle mt-1">{t('form.image_formats')}</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  aria-label={t('form.image_label')}
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) {
                      if (file.size > 8 * 1024 * 1024) {
                        toast.error(t('form.image_too_large'));
                        return;
                      }
                      setImageFile(file);
                      setImagePreview(URL.createObjectURL(file));
                    }
                  }}
                />
              </label>
            )}
          </section>

          {/* Submit */}
          <div className="flex flex-col-reverse gap-3 border-t border-theme-default pt-6 sm:flex-row sm:items-center sm:justify-end">
            <Link to={tenantPath("/listings")} className="sm:mr-auto">
              <Button
                type="button"
                variant="flat"
                className="w-full bg-theme-elevated text-theme-primary sm:w-auto sm:min-w-24"
              >
                {t('form.cancel')}
              </Button>
            </Link>
            <Button
              type="submit"
              color="primary"
              className="w-full font-semibold text-white sm:w-auto sm:min-w-44"
              startContent={isEditing ? <CheckCircle className="w-4 h-4" aria-hidden="true" /> : <Save className="w-4 h-4" aria-hidden="true" />}
              isLoading={isSubmitting}
            >
              {isEditing ? t('form.update') : t('create')}
            </Button>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default CreateListingPage;
