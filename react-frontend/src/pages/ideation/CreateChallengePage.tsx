// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Challenge Page - Admin-only form for ideation challenges
 *
 * Features:
 * - Create new challenge or edit existing (via :id param)
 * - Category dropdown from API (I1)
 * - Template picker modal (I9)
 * - All fields: title, description, category, prize, deadlines, max ideas, status
 * - Tag input with chips
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
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  ArrowLeft,
  FileText,
  Eye,
  HelpCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
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

interface Category {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  color: string | null;
}

interface Template {
  id: number;
  name: string;
  description: string | null;
  category: string | null;
  tags: string[];
  created_at: string;
}

interface TemplateData {
  title: string;
  description: string;
  category: string | null;
  prize_description: string | null;
  submission_deadline: string | null;
  voting_deadline: string | null;
  max_ideas_per_user: number | null;
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

  // Categories (I1)
  const [categories, setCategories] = useState<Category[]>([]);

  // Templates (I9)
  const { isOpen: isTemplateOpen, onOpen: onTemplateOpen, onClose: onTemplateClose } = useDisclosure();
  const [templates, setTemplates] = useState<Template[]>([]);
  const [isLoadingTemplates, setIsLoadingTemplates] = useState(false);
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);
  const [isApplyingTemplate, setIsApplyingTemplate] = useState(false);

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  // Redirect non-admins
  useEffect(() => {
    if (!isAdmin) {
      navigate(tenantPath('/ideation'), { replace: true });
    }
  }, [isAdmin, navigate, tenantPath]);

  // Fetch categories
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const response = await api.get<Category[]>('/v2/ideation-categories');
        if (response.success && response.data) {
          setCategories(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to fetch categories', err);
      }
    };
    fetchCategories();
  }, []);

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
              ? challenge.submission_deadline.slice(0, 16)
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

  // Template picker (I9)
  const handleOpenTemplates = async () => {
    onTemplateOpen();
    if (templates.length === 0) {
      setIsLoadingTemplates(true);
      try {
        const response = await api.get<Template[]>('/v2/ideation-templates');
        if (response.success && response.data) {
          setTemplates(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to fetch templates', err);
      } finally {
        setIsLoadingTemplates(false);
      }
    }
  };

  const handleApplyTemplate = async () => {
    if (!selectedTemplateId) return;

    setIsApplyingTemplate(true);
    try {
      const response = await api.get<TemplateData>(`/v2/ideation-templates/${selectedTemplateId}/data`);
      if (response.success && response.data) {
        const td = response.data;
        setForm({
          title: td.title ?? '',
          description: td.description ?? '',
          category: td.category ?? '',
          prize_description: td.prize_description ?? '',
          submission_deadline: td.submission_deadline
            ? td.submission_deadline.slice(0, 16)
            : '',
          voting_deadline: td.voting_deadline
            ? td.voting_deadline.slice(0, 16)
            : '',
          max_ideas_per_user: td.max_ideas_per_user != null
            ? String(td.max_ideas_per_user)
            : '',
          status: 'draft',
          cover_image: td.cover_image ?? '',
          tags: td.tags ?? [],
        });
        onTemplateClose();
        toast.success(t('toast.template_applied', { defaultValue: 'Template applied successfully' }));
      }
    } catch (err) {
      logError('Failed to apply template', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsApplyingTemplate(false);
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
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text)]">
            {isEdit ? t('edit_page.title') : t('create_page.title')}
          </h1>
          {!isEdit && (
            <p className="text-sm text-[var(--color-text-secondary)] mt-1">
              {t('create_page.subtitle')}
            </p>
          )}
        </div>

        {/* Template picker button (I9) - only on create */}
        {!isEdit && (
          <Button
            variant="flat"
            startContent={<FileText className="w-4 h-4" />}
            onPress={handleOpenTemplates}
          >
            {t('templates.start_from_template')}
          </Button>
        )}
      </div>

      {/* Form */}
      <GlassCard className="p-6">
        <div className="space-y-5">
          {/* Challenge Framing Guidance */}
          {!isEdit && (
            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-3">
              <div className="flex items-start gap-2">
                <HelpCircle className="w-5 h-5 text-primary shrink-0 mt-0.5" />
                <div>
                  <h3 className="text-sm font-semibold text-[var(--color-text)] mb-1">
                    {t('guidance.title')}
                  </h3>
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    {t('guidance.intro')}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <p className="text-xs font-medium text-[var(--color-text)]">
                    {t('guidance.tips.clear_problem.title')}
                  </p>
                  <p className="text-xs text-[var(--color-text-tertiary)]">
                    {t('guidance.tips.clear_problem.description')}
                  </p>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-medium text-[var(--color-text)]">
                    {t('guidance.tips.scope.title')}
                  </p>
                  <p className="text-xs text-[var(--color-text-tertiary)]">
                    {t('guidance.tips.scope.description')}
                  </p>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-medium text-[var(--color-text)]">
                    {t('guidance.tips.criteria.title')}
                  </p>
                  <p className="text-xs text-[var(--color-text-tertiary)]">
                    {t('guidance.tips.criteria.description')}
                  </p>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-medium text-[var(--color-text)]">
                    {t('guidance.tips.engagement.title')}
                  </p>
                  <p className="text-xs text-[var(--color-text-tertiary)]">
                    {t('guidance.tips.engagement.description')}
                  </p>
                </div>
              </div>

              <details className="group">
                <summary className="text-xs font-medium text-primary cursor-pointer hover:underline">
                  {t('guidance.examples_toggle')}
                </summary>
                <div className="mt-2 space-y-2 text-xs text-[var(--color-text-secondary)]">
                  <div className="p-2 rounded-lg bg-[var(--color-surface)]">
                    <p className="font-medium text-[var(--color-text)]">{t('guidance.example_good.label')}</p>
                    <p className="italic mt-0.5">&ldquo;{t('guidance.example_good.text')}&rdquo;</p>
                  </div>
                  <div className="p-2 rounded-lg bg-[var(--color-surface)]">
                    <p className="font-medium text-[var(--color-text)]">{t('guidance.example_weak.label')}</p>
                    <p className="italic mt-0.5">&ldquo;{t('guidance.example_weak.text')}&rdquo;</p>
                    <p className="text-[var(--color-text-tertiary)] mt-0.5">{t('guidance.example_weak.why')}</p>
                  </div>
                </div>
              </details>
            </div>
          )}

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
            description={t('form.description_helper')}
            value={form.description}
            onValueChange={(val) => updateField('description', val)}
            variant="bordered"
            minRows={4}
            isRequired
            isInvalid={Boolean(errors.description)}
            errorMessage={errors.description}
          />

          {/* Category (I1 - dropdown from API) */}
          {categories.length > 0 ? (
            <Select
              label={t('form.category_label')}
              placeholder={t('form.category_placeholder')}
              selectedKeys={form.category ? new Set([form.category]) : new Set<string>()}
              onSelectionChange={(keys) => {
                if (keys === 'all') return;
                const selected = Array.from(keys)[0];
                updateField('category', selected ? String(selected) : '');
              }}
              variant="bordered"
            >
              {categories.map((cat) => (
                <SelectItem key={cat.name}>
                  {cat.name}
                </SelectItem>
              ))}
            </Select>
          ) : (
            <Input
              label={t('form.category_free_label')}
              placeholder={t('form.category_free_placeholder')}
              value={form.category}
              onValueChange={(val) => updateField('category', val)}
              variant="bordered"
            />
          )}

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
            description={t('form.prize_helper')}
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
            description={t('form.max_ideas_helper')}
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

      {/* Template Picker Modal (I9) */}
      <Modal isOpen={isTemplateOpen} onClose={onTemplateClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{t('templates.title')}</ModalHeader>
          <ModalBody>
            {isLoadingTemplates && (
              <div className="flex justify-center py-8">
                <Spinner size="md" />
              </div>
            )}

            {!isLoadingTemplates && templates.length === 0 && (
              <EmptyState
                icon={<FileText className="w-10 h-10 text-theme-subtle" />}
                title={t('templates.empty_title')}
                description={t('templates.empty_description')}
              />
            )}

            {!isLoadingTemplates && templates.length > 0 && (
              <div className="space-y-3">
                {templates.map((tmpl) => (
                  <GlassCard
                    key={tmpl.id}
                    className={`p-4 cursor-pointer transition-all ${
                      selectedTemplateId === tmpl.id
                        ? 'ring-2 ring-primary bg-primary/5'
                        : 'hover:bg-[var(--color-surface-hover)]'
                    }`}
                    onClick={() => setSelectedTemplateId(tmpl.id)}
                  >
                    <h4 className="font-semibold text-[var(--color-text)] mb-1">
                      {tmpl.name}
                    </h4>
                    {tmpl.description && (
                      <p className="text-sm text-[var(--color-text-secondary)] mb-2">
                        {tmpl.description}
                      </p>
                    )}
                    <div className="flex items-center gap-2 flex-wrap">
                      {tmpl.category && (
                        <Chip size="sm" variant="flat">{tmpl.category}</Chip>
                      )}
                      {tmpl.tags?.map((tag) => (
                        <Chip key={tag} size="sm" variant="bordered" className="text-xs">{tag}</Chip>
                      ))}
                    </div>
                  </GlassCard>
                ))}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onTemplateClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isApplyingTemplate}
              isDisabled={!selectedTemplateId}
              startContent={<Eye className="w-4 h-4" />}
              onPress={handleApplyTemplate}
            >
              {t('templates.use_template')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CreateChallengePage;
