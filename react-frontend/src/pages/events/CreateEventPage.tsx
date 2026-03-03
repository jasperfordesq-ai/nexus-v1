// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Event Page with image upload, category selection,
 * and HeroUI DatePicker + TimeInput components.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem, DatePicker, TimeInput, Switch, CheckboxGroup, Checkbox } from '@heroui/react';
import type { DateInputValue, TimeInputValue } from '@heroui/react';
import { parseDate, parseTime, today, getLocalTimeZone } from '@internationalized/date';
import {
  Save,
  Calendar,
  FileText,
  CheckCircle,
  Users,
  AlertTriangle,
  RefreshCw,
  ImagePlus,
  X,
  Tag,
  Repeat,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { PlaceAutocompleteInput } from '@/components/location';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import type { Event } from '@/types/api';

/** Event category IDs matching EventsPage — names resolved via t() inside the component */
const EVENT_CATEGORY_IDS = ['workshop', 'social', 'outdoor', 'online', 'meeting', 'training', 'other'] as const;

const MAX_IMAGE_SIZE_MB = 5;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

type RecurrenceFrequency = 'daily' | 'weekly' | 'biweekly' | 'monthly';
type RecurrenceEndType = 'after_count' | 'on_date';

interface FormData {
  title: string;
  description: string;
  startDate: DateInputValue | null;
  startTime: TimeInputValue | null;
  endDate: DateInputValue | null;
  endTime: TimeInputValue | null;
  location: string;
  latitude?: number;
  longitude?: number;
  max_attendees: string;
  category: string;
  // Recurrence
  isRecurring: boolean;
  recurrenceFrequency: RecurrenceFrequency;
  recurrenceDays: string[];
  recurrenceEndType: RecurrenceEndType;
  recurrenceCount: string;
  recurrenceEndDate: DateInputValue | null;
}

const WEEKDAY_KEYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'] as const;
const WEEKDAY_LABEL_KEYS = ['weekday_mon', 'weekday_tue', 'weekday_wed', 'weekday_thu', 'weekday_fri', 'weekday_sat', 'weekday_sun'] as const;

const initialFormData: FormData = {
  title: '',
  description: '',
  startDate: null,
  startTime: null,
  endDate: null,
  endTime: null,
  location: '',
  max_attendees: '',
  category: '',
  isRecurring: false,
  recurrenceFrequency: 'weekly',
  recurrenceDays: [],
  recurrenceEndType: 'after_count',
  recurrenceCount: '10',
  recurrenceEndDate: null,
};

/** Convert a DateInputValue + TimeInputValue into a JS Date */
function toJSDate(date: DateInputValue, time: TimeInputValue | null): Date {
  const dateStr = date.toString(); // "2026-02-17"
  if (time) {
    const h = String(time.hour).padStart(2, '0');
    const m = String(time.minute).padStart(2, '0');
    return new Date(`${dateStr}T${h}:${m}:00`);
  }
  return new Date(`${dateStr}T00:00:00`);
}

/** Build an RRULE string from recurrence form data */
function buildRecurrenceRule(data: FormData): string | null {
  if (!data.isRecurring) return null;

  const parts: string[] = [];

  // Frequency
  if (data.recurrenceFrequency === 'biweekly') {
    parts.push('FREQ=WEEKLY');
    parts.push('INTERVAL=2');
  } else {
    parts.push(`FREQ=${data.recurrenceFrequency.toUpperCase()}`);
  }

  // Days of week (for weekly/biweekly)
  if (
    (data.recurrenceFrequency === 'weekly' || data.recurrenceFrequency === 'biweekly') &&
    data.recurrenceDays.length > 0
  ) {
    parts.push(`BYDAY=${data.recurrenceDays.join(',')}`);
  }

  // End condition
  if (data.recurrenceEndType === 'after_count' && data.recurrenceCount) {
    const count = parseInt(data.recurrenceCount);
    if (!isNaN(count) && count > 0) {
      parts.push(`COUNT=${count}`);
    }
  } else if (data.recurrenceEndType === 'on_date' && data.recurrenceEndDate) {
    // Format as YYYYMMDD for RRULE UNTIL
    const dateStr = data.recurrenceEndDate.toString().replace(/-/g, '');
    parts.push(`UNTIL=${dateStr}T235959Z`);
  }

  return `RRULE:${parts.join(';')}`;
}

export function CreateEventPage() {
  const { t } = useTranslation('events');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const isEditing = !!id;
  usePageTitle(isEditing ? t('form.edit_title') : t('form.create_title'));

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Image upload state
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [existingImage, setExistingImage] = useState<string | null>(null);
  const [isUploadingImage, setIsUploadingImage] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const loadEvent = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setLoadError(null);
      const response = await api.get<Event>(`/v2/events/${id}`);
      if (response.success && response.data) {
        const event = response.data;
        const startDate = new Date(event.start_date);
        const endDate = event.end_date ? new Date(event.end_date) : null;

        setFormData({
          title: event.title,
          description: event.description || '',
          startDate: parseDate(startDate.toISOString().split('T')[0]),
          startTime: parseTime(startDate.toTimeString().slice(0, 5)),
          endDate: endDate ? parseDate(endDate.toISOString().split('T')[0]) : null,
          endTime: endDate ? parseTime(endDate.toTimeString().slice(0, 5)) : null,
          location: event.location || '',
          latitude: event.coordinates?.lat,
          longitude: event.coordinates?.lng,
          max_attendees: event.max_attendees?.toString() || '',
          category: event.category_name || '',
          // Recurrence fields default (editing a recurring event is not supported yet)
          isRecurring: false,
          recurrenceFrequency: 'weekly',
          recurrenceDays: [],
          recurrenceEndType: 'after_count',
          recurrenceCount: '10',
          recurrenceEndDate: null,
        });

        if (event.cover_image) {
          setExistingImage(resolveAssetUrl(event.cover_image));
        }
      } else {
        setLoadError('Event not found');
      }
    } catch (error) {
      logError('Failed to load event', error);
      setLoadError('Failed to load event. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (isEditing) {
      loadEvent();
    }
  }, [isEditing, loadEvent]);

  function handleImageSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      toast.error(t('form.toast.image_type'));
      return;
    }

    if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
      toast.error(t('form.toast.image_size', { size: MAX_IMAGE_SIZE_MB }));
      return;
    }

    setImageFile(file);

    const reader = new FileReader();
    reader.onload = (ev) => {
      setImagePreview(ev.target?.result as string);
    };
    reader.readAsDataURL(file);
    setExistingImage(null);
  }

  function removeImage() {
    setImageFile(null);
    setImagePreview(null);
    setExistingImage(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (!file) return;

    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      toast.error(t('form.toast.drop_type'));
      return;
    }

    if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
      toast.error(t('form.toast.image_size', { size: MAX_IMAGE_SIZE_MB }));
      return;
    }

    setImageFile(file);
    const reader = new FileReader();
    reader.onload = (ev) => {
      setImagePreview(ev.target?.result as string);
    };
    reader.readAsDataURL(file);
    setExistingImage(null);
  }

  function handleDragOver(e: React.DragEvent) {
    e.preventDefault();
  }

  function validateForm(): boolean {
    const newErrors: Record<string, string> = {};

    if (!formData.title.trim()) {
      newErrors.title = t('form.validation.title_required');
    } else if (formData.title.length < 5) {
      newErrors.title = t('form.validation.title_min');
    }

    if (!formData.description.trim()) {
      newErrors.description = t('form.validation.description_required');
    } else if (formData.description.length < 20) {
      newErrors.description = t('form.validation.description_min');
    }

    if (!formData.startDate) {
      newErrors.startDate = t('form.validation.start_date_required');
    }

    if (!formData.startTime) {
      newErrors.startTime = t('form.validation.start_time_required');
    }

    if (formData.startDate && formData.endDate) {
      if (formData.endDate.toString() < formData.startDate.toString()) {
        newErrors.endDate = t('form.validation.end_date_before_start');
      }
    }

    if (formData.max_attendees) {
      const max = parseInt(formData.max_attendees);
      if (isNaN(max) || max < 1 || max > 10000) {
        newErrors.max_attendees = t('form.validation.max_attendees_range');
      }
    }

    // Recurrence validation
    if (formData.isRecurring) {
      if (
        (formData.recurrenceFrequency === 'weekly' || formData.recurrenceFrequency === 'biweekly') &&
        formData.recurrenceDays.length === 0
      ) {
        newErrors.recurrenceDays = t('form.validation.select_day');
      }

      if (formData.recurrenceEndType === 'after_count') {
        const count = parseInt(formData.recurrenceCount);
        if (isNaN(count) || count < 2 || count > 52) {
          newErrors.recurrenceCount = t('form.validation.occurrences_range');
        }
      }

      if (formData.recurrenceEndType === 'on_date' && !formData.recurrenceEndDate) {
        newErrors.recurrenceEndDate = t('form.validation.select_end_date');
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function uploadImage(eventId: number): Promise<void> {
    if (!imageFile) return;

    try {
      setIsUploadingImage(true);
      const response = await api.upload(`/v2/events/${eventId}/image`, imageFile, 'image');
      if (!response.success) {
        toast.error(t('form.toast.image_failed'));
      }
    } catch (err) {
      logError('Failed to upload event image', err);
      toast.error(t('form.toast.image_failed'));
    } finally {
      setIsUploadingImage(false);
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      setIsSubmitting(true);

      const startDateTime = formData.startDate
        ? toJSDate(formData.startDate, formData.startTime)
        : new Date();

      const endDateTime = formData.endDate
        ? toJSDate(formData.endDate, formData.endTime)
        : null;

      const payload: Record<string, unknown> = {
        title: formData.title,
        description: formData.description,
        start_time: startDateTime.toISOString(),
        end_time: endDateTime?.toISOString() || null,
        location: formData.location || null,
        latitude: formData.latitude,
        longitude: formData.longitude,
        max_attendees: formData.max_attendees ? parseInt(formData.max_attendees) : null,
      };

      if (formData.category) {
        const categoryInt = parseInt(formData.category);
        if (!isNaN(categoryInt)) {
          payload.category_id = categoryInt;
        }
      }

      // Recurrence
      const recurrenceRule = buildRecurrenceRule(formData);
      if (recurrenceRule) {
        payload.recurrence_rule = recurrenceRule;
        // Also send structured fields for backend flexibility
        payload.recurrence_frequency = formData.recurrenceFrequency === 'biweekly' ? 'weekly' : formData.recurrenceFrequency;
        if (formData.recurrenceFrequency === 'biweekly') {
          payload.recurrence_interval = 2;
        }
        if (formData.recurrenceDays.length > 0) {
          payload.recurrence_days = formData.recurrenceDays.join(',');
        }
        payload.recurrence_ends_type = formData.recurrenceEndType;
        if (formData.recurrenceEndType === 'after_count') {
          payload.recurrence_ends_after_count = parseInt(formData.recurrenceCount) || 10;
        } else if (formData.recurrenceEndType === 'on_date' && formData.recurrenceEndDate) {
          payload.recurrence_ends_on_date = formData.recurrenceEndDate.toString();
        }
      }

      let response;
      if (isEditing) {
        response = await api.put(`/v2/events/${id}`, payload);
      } else {
        response = await api.post('/v2/events', payload);
      }

      if (response.success) {
        const eventId = isEditing
          ? Number(id)
          : (response.data as { id?: number })?.id;

        if (imageFile && eventId) {
          await uploadImage(eventId);
        }

        toast.success(isEditing ? t('form.toast.updated') : t('form.toast.created'));
        navigate(tenantPath('/events'));
      } else {
        toast.error(response.error || t('form.toast.error'));
      }
    } catch (error) {
      logError('Failed to save event', error);
      toast.error(t('form.toast.error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message={t('form.loading')} />;
  }

  if (loadError) {
    return (
      <div className="max-w-2xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('form.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/events")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                {t('form.back_to_events')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadEvent()}
            >
              {t('form.try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const hasImage = imagePreview || existingImage;

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/events') },
        { label: isEditing ? t('form.nav_edit') : t('form.nav_new') },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Calendar className="w-7 h-7 text-amber-600 dark:text-amber-400" aria-hidden="true" />
          {isEditing ? t('form.edit_title') : t('form.create_title')}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Cover Image Upload */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-2">
              {t('form.cover_label')}
            </label>
            {hasImage ? (
              <div className="relative rounded-xl overflow-hidden border border-theme-default">
                <img
                  src={imagePreview || existingImage || ''}
                  alt={t('form.cover_preview_alt')}
                  className="w-full h-48 object-cover"
                />
                <div className="absolute top-2 right-2 flex gap-2">
                  <Button
                    isIconOnly
                    size="sm"
                    className="bg-black/50 text-white backdrop-blur-sm"
                    onPress={removeImage}
                    aria-label={t('form.remove_image_aria')}
                  >
                    <X className="w-4 h-4" />
                  </Button>
                </div>
                <div className="absolute bottom-2 left-2">
                  <Button
                    size="sm"
                    className="bg-black/50 text-white backdrop-blur-sm"
                    startContent={<ImagePlus className="w-3.5 h-3.5" aria-hidden="true" />}
                    onPress={() => fileInputRef.current?.click()}
                  >
                    {t('form.change_image')}
                  </Button>
                </div>
              </div>
            ) : (
              <div
                className="border-2 border-dashed border-theme-default rounded-xl p-8 text-center cursor-pointer hover:border-indigo-500/50 hover:bg-indigo-500/5 transition-colors"
                onClick={() => fileInputRef.current?.click()}
                onDrop={handleDrop}
                onDragOver={handleDragOver}
                role="button"
                tabIndex={0}
                aria-label={t('form.upload_aria')}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInputRef.current?.click();
                  }
                }}
              >
                <ImagePlus className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                <p className="text-theme-muted font-medium mb-1">
                  {t('form.upload_label')}
                </p>
                <p className="text-theme-subtle text-sm">
                  {t('form.upload_hint', { size: MAX_IMAGE_SIZE_MB })}
                </p>
              </div>
            )}
            <input
              ref={fileInputRef}
              type="file"
              accept={ACCEPTED_IMAGE_TYPES.join(',')}
              onChange={handleImageSelect}
              className="hidden"
              aria-hidden="true"
            />
          </div>

          {/* Title */}
          <div>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={formData.title}
              onChange={(e) => {
                setFormData((prev) => ({ ...prev, title: e.target.value }));
                if (errors.title) setErrors((prev) => ({ ...prev, title: '' }));
              }}
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
              }}
            />
          </div>

          {/* Category */}
          <div>
            <Select
              label={t('form.category_label')}
              placeholder={t('form.category_placeholder')}
              aria-label={t('form.category_aria')}
              selectedKeys={formData.category ? [formData.category] : []}
              onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
                label: 'text-theme-muted',
              }}
            >
              {EVENT_CATEGORY_IDS.map((catId) => (
                <SelectItem key={catId}>{t(`category.${catId}`)}</SelectItem>
              ))}
            </Select>
          </div>

          {/* Description */}
          <div>
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={formData.description}
              onChange={(e) => {
                setFormData((prev) => ({ ...prev, description: e.target.value }));
                if (errors.description) setErrors((prev) => ({ ...prev, description: '' }));
              }}
              minRows={4}
              isInvalid={!!errors.description}
              errorMessage={errors.description}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
              }}
            />
          </div>

          {/* Start Date & Time */}
          <fieldset className="grid sm:grid-cols-2 gap-4">
            <legend className="sr-only">{t('form.legend_start_datetime', 'Start date and time')}</legend>
            <div>
              <DatePicker
                label={t('form.start_date_label')}
                value={formData.startDate}
                onChange={(val) => {
                  setFormData((prev) => ({ ...prev, startDate: val }));
                  if (errors.startDate) setErrors((prev) => ({ ...prev, startDate: '' }));
                }}
                minValue={today(getLocalTimeZone())}
                isInvalid={!!errors.startDate}
                errorMessage={errors.startDate}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <TimeInput
                label={t('form.start_time_label')}
                value={formData.startTime}
                onChange={(val) => {
                  setFormData((prev) => ({ ...prev, startTime: val }));
                  if (errors.startTime) setErrors((prev) => ({ ...prev, startTime: '' }));
                }}
                isInvalid={!!errors.startTime}
                errorMessage={errors.startTime}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </fieldset>

          {/* End Date & Time (optional) */}
          <fieldset className="grid sm:grid-cols-2 gap-4">
            <legend className="sr-only">{t('form.legend_end_datetime', 'End date and time (optional)')}</legend>
            <div>
              <DatePicker
                label={t('form.end_date_label')}
                value={formData.endDate}
                onChange={(val) => {
                  setFormData((prev) => ({ ...prev, endDate: val }));
                  if (errors.endDate) setErrors((prev) => ({ ...prev, endDate: '' }));
                }}
                minValue={formData.startDate || today(getLocalTimeZone())}
                isInvalid={!!errors.endDate}
                errorMessage={errors.endDate}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <TimeInput
                label={t('form.end_time_label')}
                value={formData.endTime}
                onChange={(val) => setFormData((prev) => ({ ...prev, endTime: val }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </fieldset>

          {/* Recurring Event Toggle */}
          <div className="space-y-4">
            <div className="flex items-center justify-between p-4 rounded-xl bg-theme-elevated border border-theme-default">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-purple-500/20">
                  <Repeat className="w-5 h-5 text-purple-600 dark:text-purple-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-theme-primary">
                    {t('form.recurring_toggle', { defaultValue: 'Make this a recurring event' })}
                  </p>
                  <p className="text-sm text-theme-subtle">
                    {t('form.recurring_desc', { defaultValue: 'Automatically create multiple occurrences' })}
                  </p>
                </div>
              </div>
              <Switch
                aria-label="Toggle recurring event"
                isSelected={formData.isRecurring}
                onValueChange={(checked) => setFormData((prev) => ({ ...prev, isRecurring: checked }))}
                classNames={{
                  wrapper: 'group-data-[selected=true]:bg-purple-500',
                }}
              />
            </div>

            {/* Recurrence Options (shown when toggled on) */}
            {formData.isRecurring && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
                className="space-y-4 p-4 rounded-xl border border-purple-500/30 bg-purple-500/5"
              >
                {/* Frequency */}
                <Select
                  label={t('form.recurrence_frequency', { defaultValue: 'Frequency' })}
                  aria-label="Recurrence frequency"
                  selectedKeys={[formData.recurrenceFrequency]}
                  onChange={(e) => setFormData((prev) => ({ ...prev, recurrenceFrequency: e.target.value as RecurrenceFrequency }))}
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default',
                    value: 'text-theme-primary',
                    label: 'text-theme-muted',
                  }}
                >
                  <SelectItem key="daily">{t('form.freq_daily', { defaultValue: 'Daily' })}</SelectItem>
                  <SelectItem key="weekly">{t('form.freq_weekly', { defaultValue: 'Weekly' })}</SelectItem>
                  <SelectItem key="biweekly">{t('form.freq_biweekly', { defaultValue: 'Biweekly (every 2 weeks)' })}</SelectItem>
                  <SelectItem key="monthly">{t('form.freq_monthly', { defaultValue: 'Monthly' })}</SelectItem>
                </Select>

                {/* Days of Week (for weekly/biweekly) */}
                {(formData.recurrenceFrequency === 'weekly' || formData.recurrenceFrequency === 'biweekly') && (
                  <div>
                    <label className="block text-sm font-medium text-theme-muted mb-2">
                      {t('form.recurrence_days', { defaultValue: 'Repeat on' })}
                    </label>
                    <CheckboxGroup
                      orientation="horizontal"
                      value={formData.recurrenceDays}
                      onChange={(val) => {
                        setFormData((prev) => ({ ...prev, recurrenceDays: val as string[] }));
                        if (errors.recurrenceDays) setErrors((prev) => ({ ...prev, recurrenceDays: '' }));
                      }}
                      classNames={{
                        wrapper: 'gap-2 flex-wrap',
                      }}
                    >
                      {WEEKDAY_KEYS.map((key, idx) => (
                        <Checkbox
                          key={key}
                          value={key}
                          classNames={{
                            base: 'px-3 py-1.5 rounded-lg border border-theme-default bg-theme-elevated data-[selected=true]:bg-purple-500/20 data-[selected=true]:border-purple-500/50 cursor-pointer',
                            label: 'text-sm text-theme-primary',
                          }}
                        >
                          {t(`form.${WEEKDAY_LABEL_KEYS[idx]}`)}
                        </Checkbox>
                      ))}
                    </CheckboxGroup>
                    {errors.recurrenceDays && (
                      <p className="text-tiny text-danger mt-1">{errors.recurrenceDays}</p>
                    )}
                  </div>
                )}

                {/* End Condition */}
                <div className="grid sm:grid-cols-2 gap-4">
                  <Select
                    label={t('form.recurrence_end_type', { defaultValue: 'Ends' })}
                    aria-label="How the series ends"
                    selectedKeys={[formData.recurrenceEndType]}
                    onChange={(e) => setFormData((prev) => ({ ...prev, recurrenceEndType: e.target.value as RecurrenceEndType }))}
                    classNames={{
                      trigger: 'bg-theme-elevated border-theme-default',
                      value: 'text-theme-primary',
                      label: 'text-theme-muted',
                    }}
                  >
                    <SelectItem key="after_count">{t('form.end_after_count', { defaultValue: 'After X occurrences' })}</SelectItem>
                    <SelectItem key="on_date">{t('form.end_on_date', { defaultValue: 'On a specific date' })}</SelectItem>
                  </Select>

                  {formData.recurrenceEndType === 'after_count' ? (
                    <Input
                      type="number"
                      label={t('form.recurrence_count', { defaultValue: 'Number of occurrences' })}
                      placeholder="10"
                      value={formData.recurrenceCount}
                      onChange={(e) => {
                        setFormData((prev) => ({ ...prev, recurrenceCount: e.target.value }));
                        if (errors.recurrenceCount) setErrors((prev) => ({ ...prev, recurrenceCount: '' }));
                      }}
                      min={2}
                      max={52}
                      isInvalid={!!errors.recurrenceCount}
                      errorMessage={errors.recurrenceCount}
                      classNames={{
                        input: 'bg-transparent text-theme-primary',
                        inputWrapper: 'bg-theme-elevated border-theme-default',
                        label: 'text-theme-muted',
                      }}
                    />
                  ) : (
                    <DatePicker
                      label={t('form.recurrence_end_date', { defaultValue: 'Series end date' })}
                      value={formData.recurrenceEndDate}
                      onChange={(val) => {
                        setFormData((prev) => ({ ...prev, recurrenceEndDate: val }));
                        if (errors.recurrenceEndDate) setErrors((prev) => ({ ...prev, recurrenceEndDate: '' }));
                      }}
                      minValue={formData.startDate || today(getLocalTimeZone())}
                      isInvalid={!!errors.recurrenceEndDate}
                      errorMessage={errors.recurrenceEndDate}
                      classNames={{
                        inputWrapper: 'bg-theme-elevated border-theme-default',
                        label: 'text-theme-muted',
                      }}
                    />
                  )}
                </div>

                {/* Preview of generated RRULE (for transparency) */}
                {formData.isRecurring && (
                  <div className="text-xs text-theme-subtle p-2 rounded-lg bg-theme-elevated font-mono break-all">
                    {buildRecurrenceRule(formData) || 'RRULE:...'}
                  </div>
                )}
              </motion.div>
            )}
          </div>

          {/* Location & Max Attendees */}
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <PlaceAutocompleteInput
                label={t('form.location_label')}
                placeholder={t('form.location_placeholder')}
                value={formData.location}
                onChange={(val) => setFormData((prev) => ({ ...prev, location: val }))}
                onPlaceSelect={(place) => {
                  setFormData((prev) => ({
                    ...prev,
                    location: place.formattedAddress,
                    latitude: place.lat,
                    longitude: place.lng,
                  }));
                }}
                onClear={() => {
                  setFormData((prev) => ({
                    ...prev,
                    location: '',
                    latitude: undefined,
                    longitude: undefined,
                  }));
                }}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />
            </div>

            <div>
              <Input
                type="number"
                label={t('form.max_attendees_label')}
                placeholder={t('form.max_attendees_placeholder')}
                value={formData.max_attendees}
                onChange={(e) => {
                  setFormData((prev) => ({ ...prev, max_attendees: e.target.value }));
                  if (errors.max_attendees) setErrors((prev) => ({ ...prev, max_attendees: '' }));
                }}
                min={1}
                max={10000}
                isInvalid={!!errors.max_attendees}
                errorMessage={errors.max_attendees}
                startContent={<Users className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </div>

          {/* Submit */}
          <div className="flex gap-3 pt-4">
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={
                isEditing
                  ? <CheckCircle className="w-4 h-4" aria-hidden="true" />
                  : <Save className="w-4 h-4" aria-hidden="true" />
              }
              isLoading={isSubmitting || isUploadingImage}
            >
              {isSubmitting && imageFile
                ? t('form.submit_saving')
                : isEditing
                  ? t('form.submit_update')
                  : t('form.submit_create')
              }
            </Button>
            <Link to={tenantPath("/events")}>
              <Button
                type="button"
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                {t('form.cancel')}
              </Button>
            </Link>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default CreateEventPage;
