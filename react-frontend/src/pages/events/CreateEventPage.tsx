/**
 * Create/Edit Event Page with image upload and category selection
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem } from '@heroui/react';
import {
  Save,
  Calendar,
  Clock,
  MapPin,
  FileText,
  CheckCircle,
  Users,
  AlertTriangle,
  RefreshCw,
  ImagePlus,
  X,
  Tag,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';
import type { Event } from '@/types/api';

/** Event categories matching EventsPage */
const EVENT_CATEGORIES = [
  { id: 'workshop', name: 'Workshop' },
  { id: 'social', name: 'Social' },
  { id: 'outdoor', name: 'Outdoor' },
  { id: 'online', name: 'Online' },
  { id: 'meeting', name: 'Meeting' },
  { id: 'training', name: 'Training' },
  { id: 'other', name: 'Other' },
];

const MAX_IMAGE_SIZE_MB = 5;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

interface FormData {
  title: string;
  description: string;
  start_date: string;
  start_time: string;
  end_date: string;
  end_time: string;
  location: string;
  max_attendees: string;
  category: string;
}

const initialFormData: FormData = {
  title: '',
  description: '',
  start_date: '',
  start_time: '',
  end_date: '',
  end_time: '',
  location: '',
  max_attendees: '',
  category: '',
};

export function CreateEventPage() {
  usePageTitle('Create Event');
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
          start_date: startDate.toISOString().split('T')[0],
          start_time: startDate.toTimeString().slice(0, 5),
          end_date: endDate ? endDate.toISOString().split('T')[0] : '',
          end_time: endDate ? endDate.toTimeString().slice(0, 5) : '',
          location: event.location || '',
          max_attendees: event.max_attendees?.toString() || '',
          category: event.category_name || '',
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

    // Validate type
    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      toast.error('Please select a JPEG, PNG, WebP, or GIF image');
      return;
    }

    // Validate size
    if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
      toast.error(`Image must be smaller than ${MAX_IMAGE_SIZE_MB}MB`);
      return;
    }

    setImageFile(file);

    // Create preview
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
      toast.error('Please drop a JPEG, PNG, WebP, or GIF image');
      return;
    }

    if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
      toast.error(`Image must be smaller than ${MAX_IMAGE_SIZE_MB}MB`);
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
    const newErrors: Partial<Record<keyof FormData, string>> = {};

    if (!formData.title.trim()) {
      newErrors.title = 'Title is required';
    } else if (formData.title.length < 5) {
      newErrors.title = 'Title must be at least 5 characters';
    }

    if (!formData.description.trim()) {
      newErrors.description = 'Description is required';
    } else if (formData.description.length < 20) {
      newErrors.description = 'Description must be at least 20 characters';
    }

    if (!formData.start_date) {
      newErrors.start_date = 'Start date is required';
    }

    if (!formData.start_time) {
      newErrors.start_time = 'Start time is required';
    }

    if (formData.start_date && formData.end_date && formData.end_date < formData.start_date) {
      newErrors.end_date = 'End date must be after start date';
    }

    if (formData.max_attendees) {
      const max = parseInt(formData.max_attendees);
      if (isNaN(max) || max < 1 || max > 10000) {
        newErrors.max_attendees = 'Must be between 1 and 10,000';
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
        toast.error('Event created but image upload failed');
      }
    } catch (err) {
      logError('Failed to upload event image', err);
      toast.error('Event created but image upload failed');
    } finally {
      setIsUploadingImage(false);
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      setIsSubmitting(true);

      const startDateTime = new Date(`${formData.start_date}T${formData.start_time}`);
      const endDateTime = formData.end_date && formData.end_time
        ? new Date(`${formData.end_date}T${formData.end_time}`)
        : null;

      const payload: Record<string, unknown> = {
        title: formData.title,
        description: formData.description,
        start_date: startDateTime.toISOString(),
        end_date: endDateTime?.toISOString() || null,
        location: formData.location || null,
        max_attendees: formData.max_attendees ? parseInt(formData.max_attendees) : null,
      };

      if (formData.category) {
        payload.category = formData.category;
      }

      let response;
      if (isEditing) {
        response = await api.put(`/v2/events/${id}`, payload);
      } else {
        response = await api.post('/v2/events', payload);
      }

      if (response.success) {
        // Upload image if one was selected
        const eventId = isEditing
          ? Number(id)
          : (response.data as { id?: number })?.id;

        if (imageFile && eventId) {
          await uploadImage(eventId);
        }

        toast.success(isEditing ? 'Event updated' : 'Event created');
        navigate(tenantPath('/events'));
      } else {
        toast.error(response.error || 'Failed to save event');
      }
    } catch (error) {
      logError('Failed to save event', error);
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

  if (isLoading) {
    return <LoadingScreen message="Loading event..." />;
  }

  if (loadError) {
    return (
      <div className="max-w-2xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Event</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/events")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                Back to Events
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadEvent()}
            >
              Try Again
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
        { label: 'Events', href: tenantPath('/events') },
        { label: isEditing ? 'Edit Event' : 'New Event' },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Calendar className="w-7 h-7 text-amber-600 dark:text-amber-400" aria-hidden="true" />
          {isEditing ? 'Edit Event' : 'Create New Event'}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Cover Image Upload */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-2">
              Cover Image (optional)
            </label>
            {hasImage ? (
              <div className="relative rounded-xl overflow-hidden border border-theme-default">
                <img
                  src={imagePreview || existingImage || ''}
                  alt="Event cover preview"
                  className="w-full h-48 object-cover"
                />
                <div className="absolute top-2 right-2 flex gap-2">
                  <Button
                    isIconOnly
                    size="sm"
                    className="bg-black/50 text-white backdrop-blur-sm"
                    onPress={removeImage}
                    aria-label="Remove image"
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
                    Change Image
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
                aria-label="Upload cover image"
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInputRef.current?.click();
                  }
                }}
              >
                <ImagePlus className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                <p className="text-theme-muted font-medium mb-1">
                  Click to upload or drag and drop
                </p>
                <p className="text-theme-subtle text-sm">
                  JPEG, PNG, WebP, or GIF up to {MAX_IMAGE_SIZE_MB}MB
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
              label="Event Title"
              placeholder="e.g., Community Garden Day, Skill Share Workshop..."
              value={formData.title}
              onChange={(e) => updateField('title', e.target.value)}
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
              label="Category (optional)"
              placeholder="Select a category"
              aria-label="Event category"
              selectedKeys={formData.category ? [formData.category] : []}
              onChange={(e) => updateField('category', e.target.value)}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
                label: 'text-theme-muted',
              }}
            >
              {EVENT_CATEGORIES.map((cat) => (
                <SelectItem key={cat.id}>{cat.name}</SelectItem>
              ))}
            </Select>
          </div>

          {/* Description */}
          <div>
            <Textarea
              label="Description"
              placeholder="Describe your event in detail..."
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

          {/* Start Date & Time */}
          <fieldset className="grid sm:grid-cols-2 gap-4">
            <legend className="sr-only">Start date and time</legend>
            <div>
              <Input
                type="date"
                label="Start Date"
                value={formData.start_date}
                onChange={(e) => updateField('start_date', e.target.value)}
                isInvalid={!!errors.start_date}
                errorMessage={errors.start_date}
                startContent={<Calendar className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <Input
                type="time"
                label="Start Time"
                value={formData.start_time}
                onChange={(e) => updateField('start_time', e.target.value)}
                isInvalid={!!errors.start_time}
                errorMessage={errors.start_time}
                startContent={<Clock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </fieldset>

          {/* End Date & Time (optional) */}
          <fieldset className="grid sm:grid-cols-2 gap-4">
            <legend className="sr-only">End date and time (optional)</legend>
            <div>
              <Input
                type="date"
                label="End Date (optional)"
                value={formData.end_date}
                onChange={(e) => updateField('end_date', e.target.value)}
                isInvalid={!!errors.end_date}
                errorMessage={errors.end_date}
                startContent={<Calendar className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <Input
                type="time"
                label="End Time (optional)"
                value={formData.end_time}
                onChange={(e) => updateField('end_time', e.target.value)}
                startContent={<Clock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </fieldset>

          {/* Location & Max Attendees */}
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <Input
                label="Location (optional)"
                placeholder="e.g., Online, Community Center..."
                value={formData.location}
                onChange={(e) => updateField('location', e.target.value)}
                startContent={<MapPin className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>

            <div>
              <Input
                type="number"
                label="Max Attendees (optional)"
                placeholder="Leave empty for unlimited"
                value={formData.max_attendees}
                onChange={(e) => updateField('max_attendees', e.target.value)}
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
                ? 'Saving & uploading image...'
                : isEditing
                  ? 'Update Event'
                  : 'Create Event'
              }
            </Button>
            <Link to={tenantPath("/events")}>
              <Button
                type="button"
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                Cancel
              </Button>
            </Link>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default CreateEventPage;
