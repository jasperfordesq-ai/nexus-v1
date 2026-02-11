/**
 * Create/Edit Group Page
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Switch } from '@heroui/react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Group } from '@/types/api';

interface FormData {
  name: string;
  description: string;
  is_private: boolean;
}

const initialFormData: FormData = {
  name: '',
  description: '',
  is_private: false,
};

export function CreateGroupPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const toast = useToast();
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

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
        });
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
      };

      let response;
      if (isEditing) {
        response = await api.put(`/v2/groups/${id}`, payload);
      } else {
        response = await api.post('/v2/groups', payload);
      }

      if (response.success) {
        toast.success(isEditing ? 'Group updated' : 'Group created');
        navigate('/groups');
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
            <Link to="/groups">
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
      {/* Back Button */}
      <Link
        to="/groups"
        className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
        aria-label="Go back to groups"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        Back to groups
      </Link>

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Users className="w-7 h-7 text-purple-600 dark:text-purple-400" aria-hidden="true" />
          {isEditing ? 'Edit Group' : 'Create New Group'}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-6">
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
              isLoading={isSubmitting}
            >
              {isEditing ? 'Update Group' : 'Create Group'}
            </Button>
            <Button
              type="button"
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => navigate('/groups')}
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
