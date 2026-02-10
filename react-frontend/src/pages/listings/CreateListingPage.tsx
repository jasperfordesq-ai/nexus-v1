/**
 * Create/Edit Listing Page
 */

import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem, Radio, RadioGroup } from '@heroui/react';
import {
  ArrowLeft,
  Save,
  Clock,
  MapPin,
  Tag,
  FileText,
  CheckCircle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Listing, Category } from '@/types/api';

interface FormData {
  title: string;
  description: string;
  type: 'offer' | 'request';
  category_id: string;
  hours_estimate: string;
  location: string;
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
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    loadCategories();
    if (isEditing) {
      loadListing();
    }
  }, [id]);

  async function loadCategories() {
    try {
      const response = await api.get<Category[]>('/v2/categories');
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
        });
      }
    } catch (error) {
      logError('Failed to load listing', error);
      navigate('/listings');
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

    if (!formData.category_id) {
      newErrors.category_id = 'Please select a category';
    }

    const hours = parseFloat(formData.hours_estimate);
    if (isNaN(hours) || hours < 0.5 || hours > 100) {
      newErrors.hours_estimate = 'Hours must be between 0.5 and 100';
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

      if (isEditing) {
        await api.put(`/v2/listings/${id}`, payload);
      } else {
        await api.post('/v2/listings', payload);
      }

      navigate('/listings');
    } catch (error) {
      logError('Failed to save listing', error);
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
    return <LoadingScreen message="Loading listing..." />;
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Back Button */}
      <Link
        to="/listings"
        className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        Back to listings
      </Link>

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6">
          {isEditing ? 'Edit Listing' : 'Create New Listing'}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Type Selection */}
          <div>
            <label className="block text-sm font-medium text-theme-muted mb-3">
              What would you like to do?
            </label>
            <RadioGroup
              value={formData.type}
              onValueChange={(value) => updateField('type', value as 'offer' | 'request')}
              orientation="horizontal"
            >
              <Radio
                value="offer"
                classNames={{
                  base: 'p-4 border border-theme-default rounded-lg data-[selected=true]:border-emerald-500',
                  label: 'text-theme-primary',
                }}
              >
                <div>
                  <div className="font-medium">Offer a Service</div>
                  <div className="text-xs text-theme-subtle">I want to help others</div>
                </div>
              </Radio>
              <Radio
                value="request"
                classNames={{
                  base: 'p-4 border border-theme-default rounded-lg data-[selected=true]:border-amber-500',
                  label: 'text-theme-primary',
                }}
              >
                <div>
                  <div className="font-medium">Request Help</div>
                  <div className="text-xs text-theme-subtle">I need assistance</div>
                </div>
              </Radio>
            </RadioGroup>
          </div>

          {/* Title */}
          <div>
            <Input
              label="Title"
              placeholder="e.g., Help with gardening, Computer tutoring..."
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
              label="Description"
              placeholder="Describe what you're offering or requesting in detail..."
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
              label="Category"
              placeholder="Select a category"
              selectedKeys={formData.category_id ? [formData.category_id] : []}
              onChange={(e) => updateField('category_id', e.target.value)}
              isInvalid={!!errors.category_id}
              errorMessage={errors.category_id}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
                label: 'text-theme-muted',
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
                label="Estimated Hours"
                placeholder="1"
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
              <Input
                label="Location (optional)"
                placeholder="e.g., Online, Dublin, Cork..."
                value={formData.location}
                onChange={(e) => updateField('location', e.target.value)}
                startContent={<MapPin className="w-4 h-4 text-theme-subtle" />}
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
              startContent={isEditing ? <CheckCircle className="w-4 h-4" /> : <Save className="w-4 h-4" />}
              isLoading={isSubmitting}
            >
              {isEditing ? 'Update Listing' : 'Create Listing'}
            </Button>
            <Link to="/listings">
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

export default CreateListingPage;
