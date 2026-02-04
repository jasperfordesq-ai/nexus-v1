/**
 * Create/Edit Event Page
 */

import { useState, useEffect } from 'react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
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
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    if (isEditing) {
      loadEvent();
    }
  }, [id]);

  async function loadEvent() {
    if (!id) return;

    try {
      setIsLoading(true);
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
      }
    } catch (error) {
      logError('Failed to load event', error);
      navigate('/events');
    } finally {
      setIsLoading(false);
    }
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

      if (isEditing) {
        await api.put(`/v2/events/${id}`, payload);
      } else {
        await api.post('/v2/events', payload);
      }

      navigate('/events');
    } catch (error) {
      logError('Failed to save event', error);
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

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Back Button */}
      <Link
        to="/events"
        className="flex items-center gap-2 text-white/60 hover:text-white transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        Back to events
      </Link>

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-white mb-6 flex items-center gap-3">
          <Calendar className="w-7 h-7 text-amber-400" />
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
              startContent={<FileText className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white',
                inputWrapper: 'bg-white/5 border-white/10',
                label: 'text-white/80',
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
                input: 'bg-transparent text-white',
                inputWrapper: 'bg-white/5 border-white/10',
                label: 'text-white/80',
              }}
            />
          </div>

          {/* Start Date & Time */}
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <Input
                type="date"
                label="Start Date"
                value={formData.start_date}
                onChange={(e) => updateField('start_date', e.target.value)}
                isInvalid={!!errors.start_date}
                errorMessage={errors.start_date}
                startContent={<Calendar className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
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
                startContent={<Clock className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />
            </div>
          </div>

          {/* End Date & Time (optional) */}
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <Input
                type="date"
                label="End Date (optional)"
                value={formData.end_date}
                onChange={(e) => updateField('end_date', e.target.value)}
                startContent={<Calendar className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />
            </div>

            <div>
              <Input
                type="time"
                label="End Time (optional)"
                value={formData.end_time}
                onChange={(e) => updateField('end_time', e.target.value)}
                startContent={<Clock className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />
            </div>
          </div>

          {/* Location & Max Attendees */}
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <Input
                label="Location (optional)"
                placeholder="e.g., Online, Community Center..."
                value={formData.location}
                onChange={(e) => updateField('location', e.target.value)}
                startContent={<MapPin className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
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
                startContent={<Users className="w-4 h-4 text-white/40" />}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />
            </div>
          </div>

          {/* Submit */}
          <div className="flex gap-3 pt-4">
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={isEditing ? <CheckCircle className="w-4 h-4" /> : <Save className="w-4 h-4" />}
              isLoading={isSubmitting}
            >
              {isEditing ? 'Update Event' : 'Create Event'}
            </Button>
            <Link to="/events">
              <Button
                type="button"
                variant="flat"
                className="bg-white/5 text-white"
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
