// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Challenge Page - Admin-only form for ideation challenges
 *
 * Features:
 * - Create new challenge or edit existing (via :id param)
 * - All fields: title, description, category, prize, deadlines, max ideas, status
 * - Validation with error display
 * - Redirects non-admin users
 */

import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Spinner,
  Chip,
} from '@heroui/react';
import {
  ArrowLeft,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface ChallengeForm {
  title: string;
  description: string;
  category: string;
  prize_description: string;
  submission_deadline: string;
  voting_deadline: string;
  max_ideas_per_user: string;
  status: string;
  cover_image: string;
  tags: string[];
}

interface ChallengeData {
  id: number;
  title: string;
  description: string;
  category: string | null;
  prize_description: string | null;
  submission_deadline: string | null;
  voting_deadline: string | null;
  max_ideas_per_user: number | null;
  status: string;
  cover_image: string | null;
  tags: string[];
}

const INITIAL_FORM: ChallengeForm = {
  title: '',
  description: '',
  category: '',
  prize_description: '',
  submission_deadline: '',
  voting_deadline: '',
  max_ideas_per_user: '',
  status: 'draft',
  cover_image: '',
  tags: [],
};

/* ───────────────────────── Main Component ───────────────────────── */

export function CreateChallengePage() {
  const { t } = useTranslation('ideation');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const isEdit = Boolean(id);

  usePageTitle(isEdit ? t('edit_page.page_title') : t('create_page.page_title'));

  const [form, setForm] = useState<ChallengeForm>(INITIAL_FORM);
  const [isLoading, setIsLoading] = useState(isEdit);
  const [isSaving, setIsSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [tagInput, setTagInput] = useState('');

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  // Redirect non-admins
  useEffect(() => {
    if (!isAdmin) {
      navigate(tenantPath('/ideation'), { replace: true });
    }
  }, [isAdmin, navigate, tenantPath]);

  // Load existing challenge for editing
  useEffect(() => {
    if (!isEdit || !id) return;

    const fetchChallenge = async () => {
      try {
        setIsLoading(true);
        const response = await api.get<ChallengeData>(`/v2/ideation-challenges/${id}`);

        if (response.success && response.data) {
          const challenge = response.data;
          setForm({
            title: challenge.title ?? '',
            description: challenge.description ?? '',
            category: challenge.category ?? '',
            prize_description: challenge.prize_description ?? '',
            submission_deadline: challenge.submission_deadline
              ? challenge.submission_deadline.slice(0, 16) // Trim to YYYY-MM-DDTHH:MM for input[type=datetime-local]
              : '',
            voting_deadline: challenge.voting_deadline
              ? challenge.voting_deadline.slice(0, 16)
              : '',
            max_ideas_per_user: challenge.max_ideas_per_user != null
              ? String(challenge.max_ideas_per_user)
              : '',
            status: challenge.status ?? 'draft',
            cover_image: challenge.cover_image ?? '',
            tags: challenge.tags ?? [],
          });
        }
      } catch (err) {
        logError('Failed to fetch challenge for editing', err);
        toast.error(t('toast.error_generic'));
        navigate(tenantPath('/ideation'));
      } finally {
        setIsLoading(false);
      }
    };

    fetchChallenge();
  }, [id, isEdit, navigate, tenantPath, toast, t]);

  const updateField = (field: keyof ChallengeForm, value: string) => {
    setForm(prev => ({ ...prev, [field]: value }));
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  };

  const handleTagKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const tag = tagInput.trim();
      if (tag && !form.tags.includes(tag)) {
        setForm(prev => ({ ...prev, tags: [...prev.tags, tag] }));
      }
      setTagInput('');
    }
  };

  const removeTag = (tagToRemove: string) => {
    setForm(prev => ({
      ...prev,
      tags: prev.tags.filter(t => t !== tagToRemove),
    }));
  };

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!form.title.trim()) {
      newErrors.title = t('validation.title_required');
    }
    if (!form.description.trim()) {
      newErrors.description = t('validation.description_required');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) return;

    setIsSaving(true);
    try {
      const payload: Record<string, unknown> = {
        title: form.title.trim(),
        description: form.description.trim(),
        category: form.category.trim() || null,
        prize_description: form.prize_description.trim() || null,
        submission_deadline: form.submission_deadline || null,
        voting_deadline: form.voting_deadline || null,
        max_ideas_per_user: form.max_ideas_per_user ? parseInt(form.max_ideas_per_user, 10) : null,
        cover_image: form.cover_image.trim() || null,
        tags: form.tags,
      };

      if (!isEdit) {
        payload.status = form.status;
      }

      if (isEdit && id) {
        await api.put(`/v2/ideation-challenges/${id}`, payload);
        toast.success(t('toast.challenge_updated'));
        navigate(tenantPath(`/ideation/${id}`));
      } else {
        const response = await api.post<ChallengeData>('/v2/ideation-challenges', payload);
        if (response.success && response.data) {
          toast.success(t('toast.challenge_created'));
          navigate(tenantPath(`/ideation/${response.data.id}`));
        }
      }
    } catch (err: unknown) {
      logError('Failed to save challenge', err);
      // Try to extract field-level errors from API response
      const apiErrors = (err as { response?: { data?: { errors?: Array<{ field?: string; message: string }> } } })
        ?.response?.data?.errors;
      if (apiErrors && Array.isArray(apiErrors)) {
        const fieldErrors: Record<string, string> = {};
        for (const e of apiErrors) {
          if (e.field) {
            fieldErrors[e.field] = e.message;
          }
        }
        if (Object.keys(fieldErrors).length > 0) {
          setErrors(fieldErrors);
        } else {
          toast.error(apiErrors[0]?.message ?? t('toast.error_generic'));
        }
      } else {
        toast.error(t('toast.error_generic'));
      }
    } finally {
      setIsSaving(false);
    }
  };

  if (!isAdmin) {
    return null;
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-6">
      {/* Back link */}
      <Button
        variant="light"
        startContent={<ArrowLeft className="w-4 h-4" />}
        className="mb-4 -ml-2"
        onPress={() => navigate(isEdit
          ? tenantPath(`/ideation/${id}`)
          : tenantPath('/ideation')
        )}
      >
        {isEdit ? t('idea_detail.back_to_challenge') : t('title')}
      </Button>

      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-[var(--color-text)]">
          {isEdit ? t('edit_page.title') : t('create_page.title')}
        </h1>
        {!isEdit && (
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            {t('create_page.subtitle')}
          </p>
        )}
      </div>

      {/* Form */}
      <GlassCard className="p-6">
        <div className="space-y-5">
          {/* Title */}
          <Input
            label={t('form.title_label')}
            placeholder={t('form.title_placeholder')}
            value={form.title}
            onValueChange={(val) => updateField('title', val)}
            variant="bordered"
            isRequired
            isInvalid={Boolean(errors.title)}
            errorMessage={errors.title}
          />

          {/* Description */}
          <Textarea
            label={t('form.description_label')}
            placeholder={t('form.description_placeholder')}
            value={form.description}
            onValueChange={(val) => updateField('description', val)}
            variant="bordered"
            minRows={4}
            isRequired
            isInvalid={Boolean(errors.description)}
            errorMessage={errors.description}
          />

          {/* Category */}
          <Input
            label={t('form.category_label')}
            placeholder={t('form.category_placeholder')}
            value={form.category}
            onValueChange={(val) => updateField('category', val)}
            variant="bordered"
          />

          {/* Cover Image URL */}
          <Input
            label={t('cover_image.label')}
            placeholder={t('cover_image.placeholder')}
            description={t('cover_image.helper')}
            value={form.cover_image}
            onValueChange={(val) => updateField('cover_image', val)}
            variant="bordered"
          />

          {/* Tags */}
          <div>
            <Input
              label={t('tags.label')}
              placeholder={t('tags.placeholder')}
              description={t('tags.helper')}
              value={tagInput}
              onValueChange={setTagInput}
              onKeyDown={handleTagKeyDown}
              variant="bordered"
            />
            {form.tags.length > 0 && (
              <div className="flex flex-wrap gap-1.5 mt-2">
                {form.tags.map((tag) => (
                  <Chip
                    key={tag}
                    size="sm"
                    variant="flat"
                    onClose={() => removeTag(tag)}
                    endContent={<X className="w-3 h-3" />}
                  >
                    {tag}
                  </Chip>
                ))}
              </div>
            )}
          </div>

          {/* Prize Description */}
          <Textarea
            label={t('form.prize_label')}
            placeholder={t('form.prize_placeholder')}
            value={form.prize_description}
            onValueChange={(val) => updateField('prize_description', val)}
            variant="bordered"
            minRows={2}
          />

          {/* Deadlines */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              type="datetime-local"
              label={t('form.submission_deadline_label')}
              value={form.submission_deadline}
              onValueChange={(val) => updateField('submission_deadline', val)}
              variant="bordered"
            />
            <Input
              type="datetime-local"
              label={t('form.voting_deadline_label')}
              value={form.voting_deadline}
              onValueChange={(val) => updateField('voting_deadline', val)}
              variant="bordered"
            />
          </div>

          {/* Max Ideas Per User */}
          <Input
            type="number"
            label={t('form.max_ideas_label')}
            placeholder={t('form.max_ideas_placeholder')}
            value={form.max_ideas_per_user}
            onValueChange={(val) => updateField('max_ideas_per_user', val)}
            variant="bordered"
            min={1}
          />

          {/* Status (only for create) */}
          {!isEdit && (
            <Select
              label={t('form.status_label')}
              selectedKeys={[form.status]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected) updateField('status', String(selected));
              }}
              variant="bordered"
            >
              <SelectItem key="draft">{t('status.draft')}</SelectItem>
              <SelectItem key="open">{t('status.open')}</SelectItem>
            </Select>
          )}

          {/* Submit */}
          <div className="flex justify-end gap-3 pt-2">
            <Button
              variant="flat"
              onPress={() => navigate(isEdit
                ? tenantPath(`/ideation/${id}`)
                : tenantPath('/ideation')
              )}
            >
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isSaving}
              onPress={handleSubmit}
            >
              {isSaving
                ? (isEdit ? t('form.updating') : t('form.creating'))
                : (isEdit ? t('form.update') : t('form.create'))
              }
            </Button>
          </div>
        </div>
      </GlassCard>
    </div>
  );
}

export default CreateChallengePage;
