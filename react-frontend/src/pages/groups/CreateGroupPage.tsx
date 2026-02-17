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
  usePageTitle('Create Group');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const isEditing = !!id;

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
        setLoadError('Group not found');
      }
    } catch (error) {
      logError('Failed to load group', error);
      setLoadError('Failed to load group. Please try again.');
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
      toast.error('Please select a valid image (JPEG, PNG, GIF, or WebP)');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      toast.error('Image must be under 5MB');
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
      toast.warning('Group saved but image upload failed. You can try again from group settings.');
      return false;
    } catch (err) {
      logError('Failed to upload group image', err);
      toast.warning('Group saved but image upload failed');
      return false;
    } finally {
      setIsUploadingImage(false);
    }
  }

  function validateForm(): boolean {
    const newErrors: Partial<Record<keyof FormData, string>> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Group name is required';
    } else if (formData.name.length < 3) {
      newErrors.name = 'Name must be at least 3 characters';
    } else if (formData.name.length > 100) {
      newErrors.name = 'Name must be less than 100 characters';
    }

    if (!formData.description.trim()) {
      newErrors.description = 'Description is required';
    } else if (formData.description.length < 20) {
      newErrors.description = 'Description must be at least 20 characters';
    } else if (formData.description.length > 2000) {
      newErrors.description = 'Description must be less than 2000 characters';
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
        is_private: formData.is_private,
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
        toast.success(isEditing ? 'Group updated' : 'Group created');
        navigate(tenantPath('/groups'));
      } else {
        toast.error(response.error || 'Failed to save group');
      }
    } catch (error) {
      logError('Failed to save group', error);
      toast.error('Something went wrong');
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
    return <LoadingScreen message="Loading group..." />;
  }

  if (loadError) {
    return (
      <div className="max-w-2xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Group</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/groups")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                Back to Groups
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadGroup()}
            >
              Try Again
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
        { label: 'Groups', href: tenantPath('/groups') },
        { label: isEditing ? 'Edit Group' : 'New Group' },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Users className="w-7 h-7 text-purple-600 dark:text-purple-400" aria-hidden="true" />
          {isEditing ? 'Edit Group' : 'Create New Group'}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Group Image Upload */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-2">
              Group Image
            </label>
            <div className="flex items-center gap-4">
              {displayImage ? (
                <div className="relative">
                  <Avatar
                    src={displayImage}
                    className="w-20 h-20 ring-2 ring-white/20"
                    radius="lg"
                    alt="Group image preview"
                  />
                  <Button
                    isIconOnly
                    size="sm"
                    variant="flat"
                    className="absolute -top-2 -right-2 bg-red-500/80 text-white rounded-full min-w-6 w-6 h-6"
                    aria-label="Remove image"
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
                  {displayImage ? 'Change Image' : 'Upload Image'}
                </Button>
                <p className="text-xs text-theme-subtle mt-1">
                  JPEG, PNG, GIF, or WebP. Max 5MB.
                </p>
                {/* Hidden file input */}
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  className="hidden"
                  onChange={handleImageSelect}
                  aria-label="Upload group image"
                />
              </div>
            </div>
          </div>

          {/* Group Name */}
          <div>
            <Input
              label="Group Name"
              placeholder="e.g., Gardening Enthusiasts, Tech Help..."
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
              label="Description"
              placeholder="Describe what your group is about..."
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
              label="Location"
              placeholder="e.g., Dublin, Ireland (optional)"
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
                    {formData.is_private ? 'Private Group' : 'Public Group'}
                  </p>
                  <p className="text-sm text-theme-subtle">
                    {formData.is_private
                      ? 'Only approved members can see posts and join'
                      : 'Anyone can see posts and join this group'}
                  </p>
                </div>
              </div>
              <Switch
                aria-label={formData.is_private ? 'Make group public' : 'Make group private'}
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
              {isUploadingImage ? 'Uploading image...' : isEditing ? 'Update Group' : 'Create Group'}
            </Button>
            <Button
              type="button"
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => navigate(tenantPath('/groups'))}
            >
              Cancel
            </Button>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default CreateGroupPage;
