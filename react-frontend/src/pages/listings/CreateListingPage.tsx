// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Listing Page
 */

import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem, Radio, RadioGroup } from '@heroui/react';
import {
  Save,
  Clock,
  Tag,
  FileText,
  CheckCircle,
  ImagePlus,
  X,
} from 'lucide-react';
import { PlaceAutocompleteInput } from '@/components/location';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { Listing, Category } from '@/types/api';

interface FormData {
  title: string;
  description: string;
  type: 'offer' | 'request';
  category_id: string;
  hours_estimate: string;
  location: string;
  latitude?: number;
  longitude?: number;
}

const initialFormData: FormData = {
  title: '',
  description: '',
  type: 'offer',
  category_id: '',
  hours_estimate: '1',
  location: '',
};

export function CreateListingPage() {
  const { t } = useTranslation('listings');
  usePageTitle(t('create'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [existingImageUrl, setExistingImageUrl] = useState<string | null>(null);
  const [removeExistingImage, setRemoveExistingImage] = useState(false);

  // Cleanup object URL on unmount or when preview changes
  useEffect(() => {
    return () => {
      if (imagePreview) URL.revokeObjectURL(imagePreview);
    };
  }, [imagePreview]);

  useEffect(() => {
    loadCategories();
    if (isEditing) {
      loadListing();
    }
  }, [id]);

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

    try {
      setIsLoading(true);
      const response = await api.get<Listing>(`/v2/listings/${id}`);
      if (response.success && response.data) {
        const listing = response.data;
        setFormData({
          title: listing.title,
          description: listing.description,
          type: listing.type,
          category_id: listing.category_id?.toString() || '',
          hours_estimate: (listing.hours_estimate ?? listing.estimated_hours ?? 1).toString(),
          location: listing.location || '',
          latitude: listing.latitude ?? undefined,
          longitude: listing.longitude ?? undefined,
        });
        if (listing.image_url) {
          setExistingImageUrl(listing.image_url);
        }
      }
    } catch (error) {
      logError('Failed to load listing', error);
      toast.error(t('form.load_error', 'Failed to load listing'));
    } finally {
      setIsLoading(false);
    }
  }

  function validateForm(): boolean {
    const newErrors: Partial<Record<keyof FormData, string>> = {};

    if (!formData.title.trim()) {
      newErrors.title = t('form.title_required');
    } else if (formData.title.length < 5) {
      newErrors.title = t('form.title_min_length');
    }

    if (!formData.description.trim()) {
      newErrors.description = t('form.description_required');
    } else if (formData.description.length < 20) {
      newErrors.description = t('form.description_min_length');
    }

    if (!formData.category_id) {
      newErrors.category_id = t('form.category_required');
    }

    const hours = parseFloat(formData.hours_estimate);
    if (isNaN(hours) || hours < 0.5 || hours > 100) {
      newErrors.hours_estimate = t('form.hours_range');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      setIsSubmitting(true);

      const payload = {
        ...formData,
        category_id: parseInt(formData.category_id),
        hours_estimate: parseFloat(formData.hours_estimate),
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

      // Upload image if selected
      if (imageFile && listingId) {
        try {
          await api.upload(`/v2/listings/${listingId}/image`, imageFile, 'image');
        } catch (imgErr) {
          logError('Failed to upload listing image', imgErr);
        }
      } else if (removeExistingImage && listingId) {
        try {
          await api.delete(`/v2/listings/${listingId}/image`);
        } catch (imgErr) {
          logError('Failed to remove listing image', imgErr);
        }
      }

      toast.success(isEditing ? t('form.update_success', 'Listing updated') : t('form.create_success', 'Listing created'));
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

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/listings') },
        { label: isEditing ? t('form.edit_title') : t('form.new_title') },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6">
          {isEditing ? t('form.edit_title') : t('form.create_title')}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Type Selection */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-3">
              {t('form.type_question')}
            </label>
            <RadioGroup
              value={formData.type}
              onValueChange={(value) => updateField('type', value as 'offer' | 'request')}
              classNames={{ wrapper: 'sm:flex-row gap-3' }}
            >
              <Radio
                value="offer"
                classNames={{
                  base: 'p-4 border border-theme-default rounded-lg data-[selected=true]:border-emerald-500 sm:flex-1',
                  label: 'text-theme-primary',
                }}
              >
                <div>
                  <div className="font-medium">{t('form.offer_title')}</div>
                  <div className="text-xs text-theme-subtle">{t('form.offer_subtitle')}</div>
                </div>
              </Radio>
              <Radio
                value="request"
                classNames={{
                  base: 'p-4 border border-theme-default rounded-lg data-[selected=true]:border-amber-500 sm:flex-1',
                  label: 'text-theme-primary',
                }}
              >
                <div>
                  <div className="font-medium">{t('form.request_title')}</div>
                  <div className="text-xs text-theme-subtle">{t('form.request_subtitle')}</div>
                </div>
              </Radio>
            </RadioGroup>
          </div>

          {/* Title */}
          <div>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={formData.title}
              onChange={(e) => updateField('title', e.target.value)}
              isInvalid={!!errors.title}
              errorMessage={errors.title}
              startContent={<FileText className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
              }}
            />
          </div>

          {/* Description */}
          <div>
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
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

          {/* Category */}
          <div>
            <Select
              label={t('form.category_label')}
              placeholder={t('form.category_placeholder')}
              selectedKeys={formData.category_id ? [formData.category_id] : []}
              onChange={(e) => updateField('category_id', e.target.value)}
              isInvalid={!!errors.category_id}
              errorMessage={errors.category_id}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" />}
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

          {/* Hours & Location */}
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
                startContent={<Clock className="w-4 h-4 text-theme-subtle" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <PlaceAutocompleteInput
                label={t('form.location_optional_label')}
                placeholder={t('form.location_placeholder')}
                value={formData.location}
                onChange={(val) => updateField('location', val)}
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
          </div>

          {/* Image Upload */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-2">
              {t('form.image_label', 'Photo (optional)')}
            </label>
            {(imagePreview || existingImageUrl) ? (
              <div className="relative inline-block">
                <img
                  src={imagePreview || resolveAssetUrl(existingImageUrl) || ''}
                  alt="Listing preview"
                  className="w-full max-w-sm h-48 object-cover rounded-xl border border-theme-default"
                />
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={() => { setImageFile(null); setImagePreview(null); if (existingImageUrl) setRemoveExistingImage(true); setExistingImageUrl(null); }}
                  className="absolute top-2 right-2 p-1.5 rounded-full bg-black/60 text-white hover:bg-black/80 transition-colors min-w-0 w-auto h-auto"
                  aria-label="Remove image"
                >
                  <X className="w-4 h-4" />
                </Button>
              </div>
            ) : (
              <label className="flex flex-col items-center justify-center w-full h-40 rounded-xl border-2 border-dashed border-theme-default hover:border-indigo-500/50 bg-theme-elevated hover:bg-theme-hover transition-colors cursor-pointer">
                <ImagePlus className="w-8 h-8 text-theme-subtle mb-2" />
                <span className="text-sm text-theme-muted">{t('form.image_upload_hint', 'Click to add a photo')}</span>
                <span className="text-xs text-theme-subtle mt-1">{t('form.image_formats', 'JPG, PNG, WebP — max 8MB')}</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) {
                      if (file.size > 8 * 1024 * 1024) {
                        toast.error(t('form.image_too_large', 'Image must be under 8MB'));
                        return;
                      }
                      setImageFile(file);
                      setImagePreview(URL.createObjectURL(file));
                    }
                  }}
                />
              </label>
            )}
          </div>

          {/* Submit */}
          <div className="flex gap-3 pt-4">
            <Button
              type="submit"
              className="flex-1 bg-linear-to-r from-indigo-500 to-purple-600 text-white"
              startContent={isEditing ? <CheckCircle className="w-4 h-4" /> : <Save className="w-4 h-4" />}
              isLoading={isSubmitting}
            >
              {isEditing ? t('form.update') : t('create')}
            </Button>
            <Link to={tenantPath("/listings")}>
              <Button
                type="button"
                variant="flat"
                className="bg-theme-elevated text-theme-primary min-w-[80px]"
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

export default CreateListingPage;
