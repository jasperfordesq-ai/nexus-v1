// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TemplateForm — Create / Edit newsletter template.
 * Detects edit mode via URL param `:id`.
 * Two-column layout: form (2/3) + sidebar (1/3).
 * Parity: PHP Admin newsletter template form.
 */

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Textarea,
  Button,
  Select,
  SelectItem,
  Switch,
  Chip,
  Divider,
  Spinner,
} from '@heroui/react';
import {
  ArrowLeft,
  Save,
  Copy,
  Lightbulb,
  BarChart3,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);

const MERGE_VARIABLES = [
  '{{first_name}}',
  '{{last_name}}',
  '{{email}}',
  '{{tenant_name}}',
  '{{unsubscribe_link}}',
  '{{view_in_browser}}',
];

const CATEGORIES = [
  { key: 'custom', label: 'Custom' },
  { key: 'saved', label: 'Saved' },
  { key: 'starter', label: 'Starter' },
];

interface TemplateData {
  id: number;
  name: string;
  description: string;
  category: string;
  is_active: number | boolean;
  subject: string;
  preview_text: string;
  content: string;
  usage_count?: number;
  created_at?: string;
  updated_at?: string;
}

export function TemplateForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  // Loading states
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Original template data (for edit mode)
  const [template, setTemplate] = useState<TemplateData | null>(null);

  // Form state
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState('custom');
  const [isActive, setIsActive] = useState(true);
  const [subject, setSubject] = useState('');
  const [previewText, setPreviewText] = useState('');
  const [content, setContent] = useState('');

  // Validation
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Dynamic page title
  usePageTitle(
    isEdit && template
      ? `Admin - Edit Template: ${template.name}`
      : 'Admin - Create Template',
  );

  // Load template for edit mode
  const loadTemplate = useCallback(async () => {
    if (!id) return;

    setLoading(true);
    setLoadError(null);

    try {
      const res = await adminNewsletters.getTemplate(Number(id));

      if (res.success && res.data) {
        const data = res.data as TemplateData;
        setTemplate(data);

        // Populate form fields
        setName(data.name || '');
        setDescription(data.description || '');
        setCategory(data.category || 'custom');
        setIsActive(!!data.is_active);
        setSubject(data.subject || '');
        setPreviewText(data.preview_text || '');
        setContent(data.content || '');
      } else {
        setLoadError(res.error || 'Failed to load template');
      }
    } catch {
      setLoadError('An unexpected error occurred while loading the template');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (isEdit) {
      loadTemplate();
    }
  }, [isEdit, loadTemplate]);

  function validate(): boolean {
    const newErrors: Record<string, string> = {};

    if (!name.trim()) {
      newErrors.name = 'Template name is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validate()) return;

    setSubmitting(true);

    try {
      const payload = {
        name: name.trim(),
        description: description.trim(),
        category,
        is_active: isActive,
        subject: subject.trim(),
        preview_text: previewText.trim(),
        content,
      };

      const res = isEdit
        ? await adminNewsletters.updateTemplate(Number(id), payload)
        : await adminNewsletters.createTemplate(payload);

      if (res.success) {
        toast.success(
          isEdit ? 'Template updated successfully' : 'Template created successfully',
        );
        navigate(tenantPath('/admin/newsletters/templates'));
      } else {
        toast.error(res.error || `Failed to ${isEdit ? 'update' : 'create'} template`);
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDuplicate() {
    if (!id) return;
    try {
      const res = await adminNewsletters.duplicateTemplate(Number(id));
      if (res.success) {
        toast.success('Template duplicated successfully');
        navigate(tenantPath('/admin/newsletters/templates'));
      } else {
        toast.error(res.error || 'Failed to duplicate template');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  }

  function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).then(() => {
      toast.success(`Copied ${text} to clipboard`);
    }).catch(() => {
      toast.error('Failed to copy to clipboard');
    });
  }

  // Loading state (edit mode)
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading template..." />
      </div>
    );
  }

  // Error state (edit mode)
  if (isEdit && (loadError || !template)) {
    return (
      <div>
        <PageHeader
          title="Edit Template"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters/templates'))}
            >
              Back to Templates
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">
              {loadError || 'Template not found'}
            </p>
            <div className="mt-4 flex justify-center">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/newsletters/templates'))}
              >
                Return to Templates
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? `Edit Template: ${template?.name}` : 'Create Template'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/newsletters/templates'))}
          >
            Back to Templates
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* ── Left column: Form fields (2/3 width) ── */}
          <div className="flex flex-col gap-6 lg:col-span-2">
            {/* Template Details */}
            <Card>
              <CardHeader>
                <h3 className="text-lg font-semibold">Template Details</h3>
              </CardHeader>
              <CardBody className="gap-4">
                <Input
                  label="Name"
                  placeholder="e.g. Monthly Newsletter"
                  value={name}
                  onValueChange={setName}
                  isRequired
                  isInvalid={!!errors.name}
                  errorMessage={errors.name}
                  isDisabled={submitting}
                />
                <Textarea
                  label="Description"
                  placeholder="Brief description of this template"
                  value={description}
                  onValueChange={setDescription}
                  minRows={2}
                  maxRows={4}
                  isDisabled={submitting}
                />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Select
                    label="Category"
                    placeholder="Select category"
                    selectedKeys={category ? [category] : []}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0] as string;
                      if (selected) setCategory(selected);
                    }}
                    isDisabled={submitting}
                  >
                    {CATEGORIES.map((cat) => (
                      <SelectItem key={cat.key}>{cat.label}</SelectItem>
                    ))}
                  </Select>
                  <div className="flex items-center gap-3 pt-2">
                    <Switch
                      isSelected={isActive}
                      onValueChange={setIsActive}
                      isDisabled={submitting}
                    />
                    <span className="text-sm">
                      {isActive ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                </div>
              </CardBody>
            </Card>

            {/* Email Settings */}
            <Card>
              <CardHeader>
                <h3 className="text-lg font-semibold">Email Settings</h3>
              </CardHeader>
              <CardBody className="gap-4">
                <Input
                  label="Default Subject Line"
                  placeholder="e.g. Your Monthly Community Update"
                  value={subject}
                  onValueChange={setSubject}
                  isDisabled={submitting}
                />
                <Input
                  label="Preview Text"
                  placeholder="Short text shown in inbox preview"
                  value={previewText}
                  onValueChange={setPreviewText}
                  isDisabled={submitting}
                  description="The preview text appears after the subject line in most email clients."
                />
              </CardBody>
            </Card>

            {/* Content Editor */}
            <Card>
              <CardHeader>
                <h3 className="text-lg font-semibold">Content</h3>
              </CardHeader>
              <CardBody className="gap-4">
                <Suspense fallback={<Spinner size="sm" className="m-4" />}>
                  <RichTextEditor
                    label="Template Content"
                    placeholder="Design your email template content..."
                    value={content}
                    onChange={setContent}
                    isDisabled={submitting}
                  />
                </Suspense>

                <Divider />

                {/* Merge variables */}
                <div>
                  <p className="mb-2 text-sm font-medium text-default-700">
                    Available Merge Variables
                  </p>
                  <p className="mb-3 text-xs text-default-500">
                    Click to copy. These will be replaced with actual values when sent.
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {MERGE_VARIABLES.map((variable) => (
                      <Chip
                        key={variable}
                        variant="flat"
                        color="primary"
                        size="sm"
                        className="cursor-pointer"
                        onClick={() => copyToClipboard(variable)}
                      >
                        {variable}
                      </Chip>
                    ))}
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* ── Right column: Sidebar (1/3 width, sticky) ── */}
          <div className="flex flex-col gap-6 lg:sticky lg:top-4 lg:self-start">
            {/* Action buttons */}
            <Card>
              <CardBody className="gap-3">
                <Button
                  type="submit"
                  color="primary"
                  className="w-full"
                  startContent={<Save size={16} />}
                  isLoading={submitting}
                >
                  {isEdit ? 'Update Template' : 'Save Template'}
                </Button>

                {isEdit && (
                  <Button
                    variant="flat"
                    className="w-full"
                    startContent={<Copy size={16} />}
                    onPress={handleDuplicate}
                    isDisabled={submitting}
                  >
                    Duplicate Template
                  </Button>
                )}

                <Button
                  variant="flat"
                  className="w-full"
                  onPress={() => navigate(tenantPath('/admin/newsletters/templates'))}
                  isDisabled={submitting}
                >
                  Cancel
                </Button>
              </CardBody>
            </Card>

            {/* Tips card */}
            <Card className="bg-success-50 dark:bg-success-50/10">
              <CardBody className="gap-2">
                <div className="flex items-center gap-2">
                  <Lightbulb size={16} className="text-success-600" />
                  <span className="text-sm font-semibold text-success-700 dark:text-success-400">
                    Email Template Tips
                  </span>
                </div>
                <ul className="list-inside list-disc space-y-1 text-xs text-success-700 dark:text-success-400">
                  <li>Use inline CSS for email client compatibility</li>
                  <li>Keep content width under 600px</li>
                  <li>Test with multiple email clients</li>
                  <li>Always include an unsubscribe link</li>
                  <li>Use web-safe fonts (Arial, Georgia, etc.)</li>
                </ul>
              </CardBody>
            </Card>

            {/* Usage stats (edit mode only) */}
            {isEdit && template && (
              <Card>
                <CardBody className="gap-2">
                  <div className="flex items-center gap-2">
                    <BarChart3 size={16} className="text-default-500" />
                    <span className="text-sm font-semibold">Usage Stats</span>
                  </div>
                  <div className="flex items-baseline gap-2">
                    <span className="text-3xl font-bold">
                      {template.usage_count ?? 0}
                    </span>
                    <span className="text-sm text-default-500">
                      newsletter{(template.usage_count ?? 0) !== 1 ? 's' : ''} sent with
                      this template
                    </span>
                  </div>
                </CardBody>
              </Card>
            )}
          </div>
        </div>
      </form>
    </div>
  );
}

export default TemplateForm;
