/**
 * Create/Edit Group Page
 */

import { useState, useEffect } from 'react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
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
  const isEditing = !!id;

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    if (isEditing) {
      loadGroup();
    }
  }, [id]);

  async function loadGroup() {
    if (!id) return;

    try {
      setIsLoading(true);
      const response = await api.get<Group>(`/v2/groups/${id}`);
      if (response.success && response.data) {
        const group = response.data;
        setFormData({
          name: group.name,
          description: group.description || '',
          is_private: group.visibility === 'private' || group.visibility === 'secret',
        });
      }
    } catch (error) {
      logError('Failed to load group', error);
      navigate('/groups');
    } finally {
      setIsLoading(false);
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

      if (isEditing) {
        await api.put(`/v2/groups/${id}`, payload);
      } else {
        await api.post('/v2/groups', payload);
      }

      navigate('/groups');
    } catch (error) {
      logError('Failed to save group', error);
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
      >
        <ArrowLeft className="w-4 h-4" />
        Back to groups
      </Link>

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-theme-primary mb-6 flex items-center gap-3">
          <Users className="w-7 h-7 text-purple-600 dark:text-purple-400" />
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
                  <Lock className="w-5 h-5 text-amber-600 dark:text-amber-400" />
                ) : (
                  <Globe className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
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
              startContent={isEditing ? <CheckCircle className="w-4 h-4" /> : <Save className="w-4 h-4" />}
              isLoading={isSubmitting}
            >
              {isEditing ? 'Update Group' : 'Create Group'}
            </Button>
            <Link to="/groups">
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

export default CreateGroupPage;
