/**
 * Create/Edit Event Page
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Textarea } from '@heroui/react';
import {
  ArrowLeft,
  Save,
  Calendar,
  Clock,
  MapPin,
  FileText,
  CheckCircle,
  Users,
  AlertTriangle,
  RefreshCw,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Event } from '@/types/api';

interface FormData {
  title: string;
  description: string;
  start_date: string;
  start_time: string;
  end_date: string;
  end_time: string;
  location: string;
  max_attendees: string;
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
};

export function CreateEventPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const toast = useToast();
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

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
        });
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

    if (formData.max_attendees) {
      const max = parseInt(formData.max_attendees);
      if (isNaN(max) || max < 1 || max > 10000) {
        newErrors.max_attendees = 'Must be between 1 and 10,000';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
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

      const payload = {
        title: formData.title,
        description: formData.description,
        start_date: startDateTime.toISOString(),
        end_date: endDateTime?.toISOString() || null,
        location: formData.location || null,
        max_attendees: formData.max_attendees ? parseInt(formData.max_attendees) : null,
      };

      let response;
      if (isEditing) {
        response = await api.put(`/v2/events/${id}`, payload);
      } else {
        response = await api.post('/v2/events', payload);
      }

      if (response.success) {
        toast.success(isEditing ? 'Event updated' : 'Event created');
        navigate('/events');
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
            <Link to="/events">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
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

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Back Button */}
      <Link
        to="/events"
        className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
        aria-label="Go back to events"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        Back to events
      </Link>

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Calendar className="w-7 h-7 text-amber-600 dark:text-amber-400" aria-hidden="true" />
          {isEditing ? 'Edit Event' : 'Create New Event'}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
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
              startContent={isEditing ? <CheckCircle className="w-4 h-4" aria-hidden="true" /> : <Save className="w-4 h-4" aria-hidden="true" />}
              isLoading={isSubmitting}
            >
              {isEditing ? 'Update Event' : 'Create Event'}
            </Button>
            <Link to="/events">
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
