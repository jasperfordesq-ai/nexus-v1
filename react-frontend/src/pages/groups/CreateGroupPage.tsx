// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Group Page
 * Includes image upload, location, and privacy settings
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Switch, Avatar } from '@heroui/react';
import {
  ArrowLeft,
  Save,
  Users,
  FileText,
  Lock,
  Globe,
  CheckCircle,
  AlertTriangle,
  RefreshCw,
  ImagePlus,
  X,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { PlaceAutocompleteInput } from '@/components/location';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import type { Group } from '@/types/api';

interface FormData {
  name: string;
  description: string;
  is_private: boolean;
  location: string;
  latitude?: number;
  longitude?: number;
}

const initialFormData: FormData = {
  name: '',
  description: '',
  is_private: false,
  location: '',
};

export function CreateGroupPage() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('groups');
  const isEditing = !!id;
  usePageTitle(isEditing ? t('form.edit_title') : t('form.create_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  // Image upload state
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [existingImage, setExistingImage] = useState<string | null>(null);
  const [isUploadingImage, setIsUploadingImage] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const loadGroup = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setLoadError(null);
      const response = await api.get<Group>(`/v2/groups/${id}`);
      if (response.success && response.data) {
        const group = response.data;
        setFormData({
          name: group.name,
          description: group.description || '',
          is_private: group.visibility === 'private' || group.visibility === 'secret',
          location: group.location || '',
          latitude: group.latitude ?? undefined,
          longitude: group.longitude ?? undefined,
        });
        // Set existing image for preview
        const imgUrl = group.image_url || group.cover_image_url || group.cover_image;
        if (imgUrl) {
          setExistingImage(resolveAssetUrl(imgUrl));
        }
      } else {
        setLoadError(t('form.error_not_found'));
      }
    } catch (error) {
      logError('Failed to load group', error);
      setLoadError(t('form.error_load_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (isEditing) {
      loadGroup();
    }
  }, [isEditing, loadGroup]);

  // Clean up object URLs on unmount
  useEffect(() => {
    return () => {
      if (imagePreview) {
        URL.revokeObjectURL(imagePreview);
      }
    };
  }, [imagePreview]);

  function handleImageSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      toast.error(t('form.toast.image_type'));
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      toast.error(t('form.toast.image_size'));
      return;
    }

    // Revoke old preview URL
    if (imagePreview) {
      URL.revokeObjectURL(imagePreview);
    }

    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
    setExistingImage(null);
  }

  function clearImage() {
    if (imagePreview) {
      URL.revokeObjectURL(imagePreview);
    }
    setImageFile(null);
    setImagePreview(null);
    setExistingImage(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  }

  async function uploadGroupImage(groupId: number | string): Promise<boolean> {
    if (!imageFile) return true; // No image to upload is not a failure

    try {
      setIsUploadingImage(true);
      const response = await api.upload(`/v2/groups/${groupId}/image`, imageFile, 'image');
      if (response.success) {
        return true;
      }
      toast.warning(t('form.toast.image_failed'));
      return false;
    } catch (err) {
      logError('Failed to upload group image', err);
      toast.warning(t('form.toast.image_failed_short'));
      return false;
    } finally {
      setIsUploadingImage(false);
    }
  }

  function validateForm(): boolean {
    const newErrors: Partial<Record<keyof FormData, string>> = {};

    if (!formData.name.trim()) {
      newErrors.name = t('form.validation.name_required');
    } else if (formData.name.length < 3) {
      newErrors.name = t('form.validation.name_min');
    } else if (formData.name.length > 100) {
      newErrors.name = t('form.validation.name_max');
    }

    if (!formData.description.trim()) {
      newErrors.description = t('form.validation.description_required');
    } else if (formData.description.length < 20) {
      newErrors.description = t('form.validation.description_min');
    } else if (formData.description.length > 2000) {
      newErrors.description = t('form.validation.description_max');
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
        name: formData.name,
        description: formData.description,
        visibility: formData.is_private ? 'private' : 'public',
        location: formData.location || undefined,
        latitude: formData.latitude,
        longitude: formData.longitude,
      };

      let response;
      if (isEditing) {
        response = await api.put(`/v2/groups/${id}`, payload);
      } else {
        response = await api.post<{ id: number }>('/v2/groups', payload);
      }

      if (response.success) {
        // Upload image if one was selected
        const groupId = isEditing ? id! : (response.data as { id: number })?.id;
        if (imageFile && groupId) {
          await uploadGroupImage(groupId);
        }
        toast.success(isEditing ? t('form.toast.updated') : t('form.toast.created'));
        navigate(tenantPath('/groups'));
      } else {
        toast.error(response.error || t('form.toast.save_failed'));
      }
    } catch (error) {
      logError('Failed to save group', error);
      toast.error(t('form.toast.something_wrong'));
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

  const displayImage = imagePreview || existingImage;

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
            <Link to={tenantPath("/groups")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                {t('form.back_to_groups')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadGroup()}
            >
              {t('form.try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/groups') },
        { label: isEditing ? t('form.nav_edit') : t('form.nav_new') },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Users className="w-7 h-7 text-purple-600 dark:text-purple-400" aria-hidden="true" />
          {isEditing ? t('form.edit_title') : t('form.create_title')}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Group Image Upload */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-2">
              {t('form.image_label')}
            </label>
            <div className="flex items-center gap-4">
              {displayImage ? (
                <div className="relative">
                  <Avatar
                    src={displayImage}
                    className="w-20 h-20 ring-2 ring-white/20"
                    radius="lg"
                    alt={t('form.image_preview_alt')}
                  />
                  <Button
                    isIconOnly
                    size="sm"
                    variant="flat"
                    className="absolute -top-2 -right-2 bg-red-500/80 text-white rounded-full min-w-6 w-6 h-6"
                    aria-label={t('form.remove_image_aria')}
                    onPress={clearImage}
                  >
                    <X className="w-3 h-3" />
                  </Button>
                </div>
              ) : (
                <div className="w-20 h-20 rounded-xl bg-theme-elevated border-2 border-dashed border-theme-default flex items-center justify-center">
                  <ImagePlus className="w-8 h-8 text-theme-subtle" aria-hidden="true" />
                </div>
              )}
              <div className="flex-1">
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ImagePlus className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => fileInputRef.current?.click()}
                >
                  {displayImage ? t('form.change_image') : t('form.upload_image')}
                </Button>
                <p className="text-xs text-theme-subtle mt-1">
                  {t('form.image_hint')}
                </p>
                {/* Hidden file input */}
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  className="hidden"
                  onChange={handleImageSelect}
                  aria-label={t('form.upload_image_aria')}
                />
              </div>
            </div>
          </div>

          {/* Group Name */}
          <div>
            <Input
              label={t('form.name_label')}
              placeholder={t('form.name_placeholder')}
              value={formData.name}
              onChange={(e) => updateField('name', e.target.value)}
              isInvalid={!!errors.name}
              errorMessage={errors.name}
              startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
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

          {/* Location */}
          <div>
            <PlaceAutocompleteInput
              label={t('form.location_label')}
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

          {/* Privacy Setting */}
          <div className="p-4 rounded-lg bg-theme-elevated border border-theme-default">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                {formData.is_private ? (
                  <Lock className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                ) : (
                  <Globe className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                )}
                <div>
                  <p className="font-medium text-theme-primary">
                    {formData.is_private ? t('form.private_group') : t('form.public_group')}
                  </p>
                  <p className="text-sm text-theme-subtle">
                    {formData.is_private
                      ? t('form.private_desc')
                      : t('form.public_desc')}
                  </p>
                </div>
              </div>
              <Switch
                aria-label={formData.is_private ? t('form.make_public_aria') : t('form.make_private_aria')}
                isSelected={formData.is_private}
                onValueChange={(checked) => updateField('is_private', checked)}
                classNames={{
                  wrapper: 'group-data-[selected=true]:bg-amber-500',
                }}
              />
            </div>
          </div>

          {/* Submit */}
          <div className="flex gap-3 pt-4">
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={isEditing ? <CheckCircle className="w-4 h-4" aria-hidden="true" /> : <Save className="w-4 h-4" aria-hidden="true" />}
              isLoading={isSubmitting || isUploadingImage}
            >
              {isUploadingImage ? t('form.submit_uploading') : isEditing ? t('form.submit_update') : t('form.submit_create')}
            </Button>
            <Button
              type="button"
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => navigate(tenantPath('/groups'))}
            >
              {t('form.cancel')}
            </Button>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default CreateGroupPage;
