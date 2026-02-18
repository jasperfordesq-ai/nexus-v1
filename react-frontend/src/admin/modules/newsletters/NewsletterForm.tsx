/**
 * Newsletter Create/Edit Form
 * Form for creating or editing a newsletter campaign.
 * Parity: PHP Admin\NewsletterController::create() / edit()
 */

import { useState, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Input, Button, Select, SelectItem,
  Divider, Switch,
} from '@heroui/react';
import { Save, ArrowLeft } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { RichTextEditor, PageHeader } from '../../components';

interface SegmentOption {
  id: number;
  name: string;
}

interface TemplateOption {
  id: number;
  name: string;
}

export function NewsletterForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Newsletter`);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Core fields
  const [name, setName] = useState('');
  const [subject, setSubject] = useState('');
  const [previewText, setPreviewText] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');

  // Targeting
  const [targetAudience, setTargetAudience] = useState('all_members');
  const [segmentId, setSegmentId] = useState('');

  // Scheduling
  const [scheduledAt, setScheduledAt] = useState('');

  // A/B Testing
  const [abTestEnabled, setAbTestEnabled] = useState(false);
  const [subjectB, setSubjectB] = useState('');

  // Template
  const [templateId, setTemplateId] = useState('');

  // Options data
  const [segments, setSegments] = useState<SegmentOption[]>([]);
  const [templates, setTemplates] = useState<TemplateOption[]>([]);

  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(isEdit);

  // Load segments and templates
  useEffect(() => {
    (async () => {
      try {
        const [segRes, tplRes] = await Promise.all([
          adminNewsletters.getSegments(),
          adminNewsletters.getTemplates(),
        ]);
        if (segRes.success && Array.isArray(segRes.data)) {
          setSegments(segRes.data as SegmentOption[]);
        }
        if (tplRes.success && Array.isArray(tplRes.data)) {
          setTemplates(tplRes.data as TemplateOption[]);
        }
      } catch { /* non-critical */ }
    })();
  }, []);

  useEffect(() => {
    if (isEdit && id) {
      (async () => {
        try {
          const res = await adminNewsletters.get(Number(id));
          if (res.success && res.data) {
            const d = res.data as Record<string, unknown>;
            setName((d.name as string) || '');
            setSubject((d.subject as string) || '');
            setPreviewText((d.preview_text as string) || '');
            setContent((d.content as string) || '');
            setStatus((d.status as string) || 'draft');
            setTargetAudience((d.target_audience as string) || 'all_members');
            setSegmentId(d.segment_id ? String(d.segment_id) : '');
            setScheduledAt((d.scheduled_at as string) || '');
            setAbTestEnabled(!!d.ab_test_enabled);
            setSubjectB((d.subject_b as string) || '');
            setTemplateId(d.template_id ? String(d.template_id) : '');
          }
        } catch { /* empty */ }
        setLoading(false);
      })();
    }
  }, [id, isEdit]);

  const handleSubmit = async () => {
    if (!name.trim()) {
      toast.error('Campaign name is required');
      return;
    }
    if (!subject.trim()) {
      toast.error('Subject line is required');
      return;
    }

    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        name,
        subject,
        preview_text: previewText,
        content,
        status,
        target_audience: targetAudience,
        segment_id: segmentId ? Number(segmentId) : null,
        scheduled_at: scheduledAt || null,
        ab_test_enabled: abTestEnabled,
        subject_b: abTestEnabled ? subjectB : null,
        template_id: templateId ? Number(templateId) : null,
      };

      const res = isEdit && id
        ? await adminNewsletters.update(Number(id), payload)
        : await adminNewsletters.create(payload);

      if (res.success) {
        toast.success(isEdit ? 'Newsletter updated' : 'Newsletter created');
        navigate(tenantPath('/admin/newsletters'));
      } else {
        toast.error((res as { error?: string }).error || 'Failed to save newsletter');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
    setSaving(false);
  };

  if (loading) {
    return <div className="flex justify-center py-16"><span className="text-default-400">Loading...</span></div>;
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Newsletter' : 'Create Newsletter'}
        description={isEdit ? 'Update newsletter details' : 'Create a new email campaign'}
        actions={
          <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/newsletters'))}>
            Back
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          <Card shadow="sm">
            <CardHeader><h3 className="text-lg font-semibold">Newsletter Details</h3></CardHeader>
            <CardBody className="gap-4">
              <Input label="Campaign Name" placeholder="e.g., Monthly Update" value={name} onValueChange={setName} isRequired variant="bordered" />
              <Input label="Subject Line" placeholder="e.g., Your February Update" value={subject} onValueChange={setSubject} isRequired variant="bordered" />
              <Input label="Preview Text" placeholder="Brief text shown in inbox preview" value={previewText} onValueChange={setPreviewText} variant="bordered" description="Appears as the email preview in most email clients" />

              {/* A/B Testing */}
              <div className="flex items-center justify-between p-3 rounded-lg border border-default-200">
                <div>
                  <p className="text-sm font-medium">A/B Test</p>
                  <p className="text-xs text-default-400">Test two subject lines to optimize open rates</p>
                </div>
                <Switch isSelected={abTestEnabled} onValueChange={setAbTestEnabled} size="sm" />
              </div>
              {abTestEnabled && (
                <Input label="Subject B (Variant)" placeholder="Alternative subject line" value={subjectB} onValueChange={setSubjectB} variant="bordered" />
              )}

              <Divider />
              <RichTextEditor
                label="Content"
                placeholder="Write your newsletter content..."
                value={content}
                onChange={setContent}
                isDisabled={saving}
              />
            </CardBody>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Status & Scheduling */}
          <Card shadow="sm">
            <CardHeader><h3 className="text-sm font-semibold">Status & Scheduling</h3></CardHeader>
            <CardBody className="gap-4">
              <Select
                label="Status"
                selectedKeys={[status]}
                onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setStatus(String(v)); }}
                variant="bordered"
                size="sm"
              >
                <SelectItem key="draft">Draft</SelectItem>
                <SelectItem key="scheduled">Scheduled</SelectItem>
              </Select>

              {status === 'scheduled' && (
                <Input
                  label="Scheduled Date & Time"
                  type="datetime-local"
                  value={scheduledAt}
                  onValueChange={setScheduledAt}
                  variant="bordered"
                  size="sm"
                />
              )}
            </CardBody>
          </Card>

          {/* Audience */}
          <Card shadow="sm">
            <CardHeader><h3 className="text-sm font-semibold">Target Audience</h3></CardHeader>
            <CardBody className="gap-4">
              <Select
                label="Recipients"
                selectedKeys={[targetAudience]}
                onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setTargetAudience(String(v)); }}
                variant="bordered"
                size="sm"
              >
                <SelectItem key="all_members">All Members</SelectItem>
                <SelectItem key="subscribers_only">Subscribers Only</SelectItem>
                <SelectItem key="segment">Specific Segment</SelectItem>
              </Select>

              {targetAudience === 'segment' && segments.length > 0 && (
                <Select
                  label="Segment"
                  selectedKeys={segmentId ? [segmentId] : []}
                  onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setSegmentId(String(v)); }}
                  variant="bordered"
                  size="sm"
                >
                  {segments.map((s) => (
                    <SelectItem key={String(s.id)}>{s.name}</SelectItem>
                  ))}
                </Select>
              )}
            </CardBody>
          </Card>

          {/* Template */}
          {templates.length > 0 && (
            <Card shadow="sm">
              <CardHeader><h3 className="text-sm font-semibold">Template</h3></CardHeader>
              <CardBody className="gap-4">
                <Select
                  label="Load Template"
                  selectedKeys={templateId ? [templateId] : []}
                  onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setTemplateId(String(v)); }}
                  variant="bordered"
                  size="sm"
                  placeholder="Choose a template..."
                >
                  {templates.map((t) => (
                    <SelectItem key={String(t.id)}>{t.name}</SelectItem>
                  ))}
                </Select>
              </CardBody>
            </Card>
          )}

          {/* Actions */}
          <div className="flex flex-col gap-2">
            <Button color="primary" startContent={<Save size={16} />} onPress={handleSubmit} isLoading={saving} className="w-full">
              {isEdit ? 'Update' : 'Create'} Newsletter
            </Button>
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/newsletters'))} className="w-full">
              Cancel
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default NewsletterForm;
