// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Create/Edit Form
 * Full parity with PHP Admin\NewsletterController::create() / edit()
 * Includes: send, test send, recipient preview, A/B testing, recurring, geo/group targeting
 */

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import {
  Card, CardBody, CardHeader, Input, Button, Select, SelectItem,
  Divider, Switch, Chip, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Tooltip, Spinner,
} from '@heroui/react';
import {
  Save, ArrowLeft, Send, TestTube, Users, Calendar, Repeat, Target,
  MapPin, UsersRound, AlertCircle, CheckCircle,
} from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);

interface SegmentOption {
  id: number;
  name: string;
  member_count?: number;
}

interface TemplateOption {
  id: number;
  name: string;
  subject?: string;
  preview_text?: string;
  content?: string;
}

interface GroupOption {
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
  const [subject, setSubject] = useState('');
  const [previewText, setPreviewText] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');

  // Targeting
  const [targetAudience, setTargetAudience] = useState('all_members');
  const [segmentId, setSegmentId] = useState('');

  // Geo/group targeting
  const [targetCounties, setTargetCounties] = useState('');
  const [targetTowns, setTargetTowns] = useState('');
  const [targetGroups, setTargetGroups] = useState<string[]>([]);

  // Scheduling
  const [scheduledAt, setScheduledAt] = useState('');

  // Recurring
  const [isRecurring, setIsRecurring] = useState(false);
  const [recurringFrequency, setRecurringFrequency] = useState('weekly');
  const [recurringDay, setRecurringDay] = useState('1'); // Mon
  const [recurringDayOfMonth, setRecurringDayOfMonth] = useState('1');
  const [recurringTime, setRecurringTime] = useState('09:00');
  const [recurringEndDate, setRecurringEndDate] = useState('');

  // A/B Testing
  const [abTestEnabled, setAbTestEnabled] = useState(false);
  const [subjectB, setSubjectB] = useState('');
  const [abSplitPercentage, setAbSplitPercentage] = useState(50);
  const [abWinnerMetric, setAbWinnerMetric] = useState('opens');
  const [abAutoSelectWinner, setAbAutoSelectWinner] = useState(false);
  const [abAutoSelectAfterHours, setAbAutoSelectAfterHours] = useState(24);

  // Template
  const [templateId, setTemplateId] = useState('');

  // Options data
  const [segments, setSegments] = useState<SegmentOption[]>([]);
  const [templates, setTemplates] = useState<TemplateOption[]>([]);
  const [groups, setGroups] = useState<GroupOption[]>([]);

  // UI state
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(isEdit);
  const [sending, setSending] = useState(false);
  const [sendingTest, setSendingTest] = useState(false);
  const [recipientCount, setRecipientCount] = useState<number | null>(null);
  const [recipientLoading, setRecipientLoading] = useState(false);
  const [confirmSendOpen, setConfirmSendOpen] = useState(false);
  const [newsletterStatus, setNewsletterStatus] = useState('draft');

  // Load segments, templates, groups
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

      // Load groups for targeting
      try {
        const api = await import('../../api/adminApi');
        if (api.adminGroups?.list) {
          const gRes = await api.adminGroups.list({ per_page: 200 });
          if (gRes.success) {
            const payload = gRes.data as unknown;
            if (payload && typeof payload === 'object' && 'data' in payload) {
              setGroups((payload as { data: GroupOption[] }).data || []);
            } else if (Array.isArray(payload)) {
              setGroups(payload as GroupOption[]);
            }
          }
        }
      } catch { /* non-critical */ }
    })();
  }, []);

  // Load newsletter data for edit
  useEffect(() => {
    if (isEdit && id) {
      (async () => {
        try {
          const res = await adminNewsletters.get(Number(id));
          if (res.success && res.data) {
            const d = res.data as Record<string, unknown>;
            setSubject((d.subject as string) || '');
            setPreviewText((d.preview_text as string) || '');
            setContent((d.content as string) || '');
            setStatus((d.status as string) || 'draft');
            setNewsletterStatus((d.status as string) || 'draft');
            setTargetAudience((d.target_audience as string) || 'all_members');
            setSegmentId(d.segment_id ? String(d.segment_id) : '');
            setScheduledAt((d.scheduled_at as string) || '');
            setAbTestEnabled(!!d.ab_test_enabled);
            setSubjectB((d.subject_b as string) || '');
            setAbSplitPercentage((d.ab_split_percentage as number) || 50);
            setAbWinnerMetric((d.ab_winner_metric as string) || 'opens');
            setAbAutoSelectWinner(!!d.ab_auto_select_winner);
            setAbAutoSelectAfterHours((d.ab_auto_select_after_hours as number) || 24);
            setTemplateId(d.template_id ? String(d.template_id) : '');
            setTargetCounties((d.target_counties as string) || '');
            setTargetTowns((d.target_towns as string) || '');
            setIsRecurring(!!d.is_recurring);
            setRecurringFrequency((d.recurring_frequency as string) || 'weekly');
            setRecurringDay(d.recurring_day ? String(d.recurring_day) : '1');
            setRecurringDayOfMonth(d.recurring_day_of_month ? String(d.recurring_day_of_month) : '1');
            setRecurringTime((d.recurring_time as string) || '09:00');
            setRecurringEndDate((d.recurring_end_date as string) || '');

            if (d.target_groups) {
              try {
                const parsed = typeof d.target_groups === 'string'
                  ? JSON.parse(d.target_groups as string)
                  : d.target_groups;
                if (Array.isArray(parsed)) {
                  setTargetGroups(parsed.map(String));
                }
              } catch { /* ignore */ }
            }
          }
        } catch (err) {
          logError('NewsletterForm: failed to load newsletter data', err);
          toast.error('Failed to load newsletter. Please try again.');
        }
        setLoading(false);
      })();
    }
  }, [id, isEdit]);

  // Fetch recipient count when targeting changes
  const fetchRecipientCount = useCallback(async () => {
    setRecipientLoading(true);
    try {
      const params: { target_audience: string; segment_id?: number } = {
        target_audience: targetAudience === 'segment' ? 'all_members' : targetAudience,
      };
      if (targetAudience === 'segment' && segmentId) {
        params.segment_id = Number(segmentId);
      }
      const res = await adminNewsletters.getRecipientCount(params);
      if (res.success && res.data) {
        setRecipientCount((res.data as { count: number }).count);
      }
    } catch {
      setRecipientCount(null);
    }
    setRecipientLoading(false);
  }, [targetAudience, segmentId]);

  useEffect(() => {
    fetchRecipientCount();
  }, [fetchRecipientCount]);

  // Load template content when template changes
  useEffect(() => {
    if (templateId && !isEdit) {
      const tpl = templates.find(t => String(t.id) === templateId);
      if (tpl) {
        if (tpl.content) setContent(tpl.content);
        if (tpl.subject && !subject) setSubject(tpl.subject);
        if (tpl.preview_text && !previewText) setPreviewText(tpl.preview_text);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [templateId]);

  const buildPayload = (): Record<string, unknown> => ({
    subject,
    name: subject, // PHP requires name — use subject as name
    preview_text: previewText,
    content,
    status,
    target_audience: targetAudience,
    segment_id: segmentId ? Number(segmentId) : null,
    scheduled_at: status === 'scheduled' ? scheduledAt : null,
    ab_test_enabled: abTestEnabled,
    subject_b: abTestEnabled ? subjectB : null,
    ab_split_percentage: abTestEnabled ? abSplitPercentage : 50,
    ab_winner_metric: abTestEnabled ? abWinnerMetric : 'opens',
    ab_auto_select_winner: abTestEnabled ? abAutoSelectWinner : false,
    ab_auto_select_after_hours: abTestEnabled && abAutoSelectWinner ? abAutoSelectAfterHours : null,
    template_id: templateId ? Number(templateId) : null,
    target_counties: targetCounties || null,
    target_towns: targetTowns || null,
    target_groups: targetGroups.length > 0 ? JSON.stringify(targetGroups.map(Number)) : null,
    is_recurring: isRecurring,
    recurring_frequency: isRecurring ? recurringFrequency : null,
    recurring_day: isRecurring && recurringFrequency === 'weekly' ? Number(recurringDay) : null,
    recurring_day_of_month: isRecurring && recurringFrequency === 'monthly' ? Number(recurringDayOfMonth) : null,
    recurring_time: isRecurring ? recurringTime : null,
    recurring_end_date: isRecurring && recurringEndDate ? recurringEndDate : null,
  });

  const saveNewsletter = async (): Promise<number | null> => {
    if (!subject.trim()) {
      toast.error('Subject line is required');
      return null;
    }

    const payload = buildPayload();
    const res = isEdit && id
      ? await adminNewsletters.update(Number(id), payload)
      : await adminNewsletters.create(payload);

    if (res.success) {
      const newId = isEdit ? Number(id) : (res.data as { id: number }).id;
      return newId;
    } else {
      toast.error((res as { error?: string }).error || 'Failed to save newsletter');
      return null;
    }
  };

  const handleSubmit = async () => {
    setSaving(true);
    try {
      const savedId = await saveNewsletter();
      if (savedId !== null) {
        toast.success(isEdit ? 'Newsletter updated' : 'Newsletter created');
        navigate(tenantPath('/admin/newsletters'));
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
    setSaving(false);
  };

  const handleSendNow = async () => {
    if (!id) return;
    setSending(true);
    try {
      // Save first, then send
      const savedId = await saveNewsletter();
      if (savedId === null) {
        setSending(false);
        return;
      }

      const res = await adminNewsletters.sendNewsletter(savedId);
      if (res.success) {
        const data = res.data as { queued?: number; message?: string };
        toast.success(data.message || `Newsletter queued for ${data.queued || 0} recipients`);
        setConfirmSendOpen(false);
        navigate(tenantPath(`/admin/newsletters/${id}/stats`));
      } else {
        toast.error((res as { error?: string }).error || 'Failed to send newsletter');
      }
    } catch {
      toast.error('Failed to send newsletter');
    }
    setSending(false);
  };

  const handleSendTest = async () => {
    if (!id) {
      toast.error('Please save the newsletter first before sending a test');
      return;
    }
    setSendingTest(true);
    try {
      // Save first so the test email uses current content
      const savedId = await saveNewsletter();
      if (savedId === null) {
        setSendingTest(false);
        return;
      }

      const res = await adminNewsletters.sendTest(savedId);
      if (res.success) {
        const data = res.data as { sent_to?: string; message?: string };
        toast.success(data.message || `Test email sent to ${data.sent_to}`);
      } else {
        toast.error((res as { error?: string }).error || 'Failed to send test email');
      }
    } catch {
      toast.error('Failed to send test email');
    }
    setSendingTest(false);
  };

  if (loading) {
    return <div className="flex justify-center py-16"><span className="text-default-400">Loading...</span></div>;
  }

  const isSent = newsletterStatus === 'sent' || newsletterStatus === 'sending';
  const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Newsletter' : 'Create Newsletter'}
        description={isEdit ? 'Update newsletter details' : 'Create a new email campaign'}
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/newsletters'))}>
              Back
            </Button>
            {isEdit && !isSent && (
              <Button
                variant="flat"
                color="secondary"
                startContent={<TestTube size={16} />}
                onPress={handleSendTest}
                isLoading={sendingTest}
              >
                Send Test
              </Button>
            )}
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* ── Main Content ── */}
        <div className="lg:col-span-2 space-y-6">
          <Card shadow="sm">
            <CardHeader><h3 className="text-lg font-semibold">Newsletter Details</h3></CardHeader>
            <CardBody className="gap-4">
              <Input
                label="Subject Line"
                placeholder="e.g., Your February Update"
                value={subject}
                onValueChange={setSubject}
                isRequired
                variant="bordered"
                isDisabled={isSent}
              />
              <Input
                label="Preview Text"
                placeholder="Brief text shown in inbox preview"
                value={previewText}
                onValueChange={setPreviewText}
                variant="bordered"
                description="Appears as the email preview in most email clients"
                isDisabled={isSent}
              />

              {/* A/B Testing */}
              <div className="flex items-center justify-between p-3 rounded-lg border border-default-200">
                <div>
                  <p className="text-sm font-medium">A/B Test Subject Lines</p>
                  <p className="text-xs text-default-400">Test two subject lines to optimize open rates</p>
                </div>
                <Switch isSelected={abTestEnabled} onValueChange={setAbTestEnabled} size="sm" isDisabled={isSent} />
              </div>
              {abTestEnabled && (
                <div className="space-y-3 pl-4 border-l-2 border-warning-200">
                  <Input
                    label="Subject B (Variant)"
                    placeholder="Alternative subject line"
                    value={subjectB}
                    onValueChange={setSubjectB}
                    variant="bordered"
                    isDisabled={isSent}
                  />
                  <div className="grid grid-cols-2 gap-3">
                    <Input
                      type="number"
                      label="Split % (Variant A)"
                      value={String(abSplitPercentage)}
                      onValueChange={(v) => setAbSplitPercentage(Math.max(10, Math.min(90, Number(v) || 50)))}
                      variant="bordered"
                      size="sm"
                      description={`A: ${abSplitPercentage}% / B: ${100 - abSplitPercentage}%`}
                      isDisabled={isSent}
                    />
                    <Select
                      label="Winning Metric"
                      selectedKeys={[abWinnerMetric]}
                      onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setAbWinnerMetric(String(v)); }}
                      variant="bordered"
                      size="sm"
                      isDisabled={isSent}
                    >
                      <SelectItem key="opens">Open Rate</SelectItem>
                      <SelectItem key="clicks">Click Rate</SelectItem>
                      <SelectItem key="conversions">Conversion Rate</SelectItem>
                    </Select>
                  </div>
                  <div className="flex items-center justify-between p-2 rounded-lg bg-default-50">
                    <div>
                      <p className="text-xs font-medium">Auto-select winner</p>
                      <p className="text-xs text-default-400">Automatically choose best performing variant</p>
                    </div>
                    <Switch
                      isSelected={abAutoSelectWinner}
                      onValueChange={setAbAutoSelectWinner}
                      size="sm"
                      isDisabled={isSent}
                    />
                  </div>
                  {abAutoSelectWinner && (
                    <Input
                      type="number"
                      label="Auto-select after (hours)"
                      value={String(abAutoSelectAfterHours)}
                      onValueChange={(v) => setAbAutoSelectAfterHours(Math.max(1, Number(v) || 24))}
                      variant="bordered"
                      size="sm"
                      isDisabled={isSent}
                    />
                  )}
                </div>
              )}

              <Divider />
              <Suspense fallback={<Spinner size="sm" className="m-4" />}>
                <RichTextEditor
                  label="Content"
                  placeholder="Write your newsletter content..."
                  value={content}
                  onChange={setContent}
                  isDisabled={saving || isSent}
                />
              </Suspense>
            </CardBody>
          </Card>
        </div>

        {/* ── Sidebar ── */}
        <div className="space-y-6">
          {/* Recipient Preview */}
          <Card shadow="sm" className="border-2 border-primary-100">
            <CardBody className="gap-3">
              <div className="flex items-center gap-2">
                <Users size={18} className="text-primary" />
                <span className="text-sm font-semibold">Estimated Recipients</span>
              </div>
              <div className="text-center">
                {recipientLoading ? (
                  <span className="text-sm text-default-400">Calculating...</span>
                ) : recipientCount !== null ? (
                  <div>
                    <p className="text-3xl font-bold text-primary">{recipientCount.toLocaleString()}</p>
                    <p className="text-xs text-default-500">
                      {targetAudience === 'segment' ? 'matching segment rules' : targetAudience.replace(/_/g, ' ')}
                    </p>
                  </div>
                ) : (
                  <span className="text-sm text-default-400">Unable to calculate</span>
                )}
              </div>
              <Button size="sm" variant="flat" onPress={fetchRecipientCount} isLoading={recipientLoading}>
                Refresh Count
              </Button>
            </CardBody>
          </Card>

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
                isDisabled={isSent}
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
                  isDisabled={isSent}
                />
              )}
            </CardBody>
          </Card>

          {/* Recurring Schedule */}
          <Card shadow="sm">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Repeat size={16} />
                <h3 className="text-sm font-semibold">Recurring Schedule</h3>
              </div>
            </CardHeader>
            <CardBody className="gap-4">
              <div className="flex items-center justify-between">
                <p className="text-sm">Enable recurring sends</p>
                <Switch isSelected={isRecurring} onValueChange={setIsRecurring} size="sm" isDisabled={isSent} />
              </div>

              {isRecurring && (
                <div className="space-y-3">
                  <Select
                    label="Frequency"
                    selectedKeys={[recurringFrequency]}
                    onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setRecurringFrequency(String(v)); }}
                    variant="bordered"
                    size="sm"
                    isDisabled={isSent}
                  >
                    <SelectItem key="daily">Daily</SelectItem>
                    <SelectItem key="weekly">Weekly</SelectItem>
                    <SelectItem key="monthly">Monthly</SelectItem>
                  </Select>

                  {recurringFrequency === 'weekly' && (
                    <Select
                      label="Day of Week"
                      selectedKeys={[recurringDay]}
                      onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setRecurringDay(String(v)); }}
                      variant="bordered"
                      size="sm"
                      isDisabled={isSent}
                    >
                      {dayNames.map((name, i) => (
                        <SelectItem key={String(i + 1)}>{name}</SelectItem>
                      ))}
                    </Select>
                  )}

                  {recurringFrequency === 'monthly' && (
                    <Select
                      label="Day of Month"
                      selectedKeys={[recurringDayOfMonth]}
                      onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setRecurringDayOfMonth(String(v)); }}
                      variant="bordered"
                      size="sm"
                      isDisabled={isSent}
                    >
                      {Array.from({ length: 28 }, (_, i) => (
                        <SelectItem key={String(i + 1)}>{String(i + 1)}</SelectItem>
                      ))}
                    </Select>
                  )}

                  <Input
                    label="Send Time"
                    type="time"
                    value={recurringTime}
                    onValueChange={setRecurringTime}
                    variant="bordered"
                    size="sm"
                    isDisabled={isSent}
                  />

                  <Input
                    label="End Date (Optional)"
                    type="date"
                    value={recurringEndDate}
                    onValueChange={setRecurringEndDate}
                    variant="bordered"
                    size="sm"
                    description="Leave blank to run indefinitely"
                    isDisabled={isSent}
                  />
                </div>
              )}
            </CardBody>
          </Card>

          {/* Audience */}
          <Card shadow="sm">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Target size={16} />
                <h3 className="text-sm font-semibold">Target Audience</h3>
              </div>
            </CardHeader>
            <CardBody className="gap-4">
              <Select
                label="Recipients"
                selectedKeys={[targetAudience]}
                onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setTargetAudience(String(v)); }}
                variant="bordered"
                size="sm"
                isDisabled={isSent}
              >
                <SelectItem key="all_members">All Members</SelectItem>
                <SelectItem key="subscribers_only">Subscribers Only</SelectItem>
                <SelectItem key="both">Members + Subscribers</SelectItem>
                <SelectItem key="segment">Specific Segment</SelectItem>
              </Select>

              {targetAudience === 'segment' && segments.length > 0 && (
                <Select
                  label="Segment"
                  selectedKeys={segmentId ? [segmentId] : []}
                  onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setSegmentId(String(v)); }}
                  variant="bordered"
                  size="sm"
                  isDisabled={isSent}
                >
                  {segments.map((s) => (
                    <SelectItem key={String(s.id)} textValue={s.name}>
                      <div className="flex justify-between items-center">
                        <span>{s.name}</span>
                        {s.member_count !== undefined && (
                          <Chip size="sm" variant="flat">{s.member_count}</Chip>
                        )}
                      </div>
                    </SelectItem>
                  ))}
                </Select>
              )}

              <Divider />

              {/* Geo targeting */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <MapPin size={14} className="text-default-400" />
                  <p className="text-xs font-medium text-default-600">Geographic Targeting (Optional)</p>
                </div>
                <Input
                  label="Counties"
                  placeholder="e.g., Somerset, Devon"
                  value={targetCounties}
                  onValueChange={setTargetCounties}
                  variant="bordered"
                  size="sm"
                  description="Comma-separated list of counties"
                  isDisabled={isSent}
                />
                <Input
                  label="Towns/Cities"
                  placeholder="e.g., Bristol, Bath"
                  value={targetTowns}
                  onValueChange={setTargetTowns}
                  variant="bordered"
                  size="sm"
                  description="Comma-separated list of towns"
                  isDisabled={isSent}
                />
              </div>

              {/* Group targeting */}
              {groups.length > 0 && (
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <UsersRound size={14} className="text-default-400" />
                    <p className="text-xs font-medium text-default-600">Group Targeting (Optional)</p>
                  </div>
                  <Select
                    label="Target Groups"
                    selectionMode="multiple"
                    selectedKeys={new Set(targetGroups)}
                    onSelectionChange={(keys) => setTargetGroups(Array.from(keys) as string[])}
                    variant="bordered"
                    size="sm"
                    isDisabled={isSent}
                    placeholder="Select groups..."
                  >
                    {groups.map((g) => (
                      <SelectItem key={String(g.id)}>{g.name}</SelectItem>
                    ))}
                  </Select>
                  {targetGroups.length > 0 && (
                    <p className="text-xs text-default-500">{targetGroups.length} group{targetGroups.length !== 1 ? 's' : ''} selected</p>
                  )}
                </div>
              )}
            </CardBody>
          </Card>

          {/* Template */}
          {templates.length > 0 && (
            <Card shadow="sm">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Calendar size={16} />
                  <h3 className="text-sm font-semibold">Template</h3>
                </div>
              </CardHeader>
              <CardBody className="gap-4">
                <Select
                  label="Load Template"
                  selectedKeys={templateId ? [templateId] : []}
                  onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setTemplateId(String(v)); }}
                  variant="bordered"
                  size="sm"
                  placeholder="Choose a template..."
                  isDisabled={isSent}
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
            {!isSent && (
              <>
                <Button
                  color="primary"
                  startContent={<Save size={16} />}
                  onPress={handleSubmit}
                  isLoading={saving}
                  className="w-full"
                >
                  {isEdit ? 'Update' : 'Create'} Newsletter
                </Button>

                {isEdit && (
                  <Tooltip content={recipientCount === 0 ? 'No recipients match targeting' : 'Send this newsletter to all targeted recipients'}>
                    <Button
                      color="success"
                      startContent={<Send size={16} />}
                      onPress={() => setConfirmSendOpen(true)}
                      isDisabled={recipientCount === 0}
                      className="w-full"
                    >
                      Send Now{recipientCount !== null ? ` (${recipientCount.toLocaleString()})` : ''}
                    </Button>
                  </Tooltip>
                )}
              </>
            )}

            {isSent && (
              <Card className="bg-success-50 dark:bg-success-50/10">
                <CardBody className="flex-row items-center gap-3">
                  <CheckCircle size={20} className="text-success" />
                  <div>
                    <p className="text-sm font-medium text-success">Newsletter Sent</p>
                    <p className="text-xs text-success-600 dark:text-success-400">This newsletter has been sent and cannot be edited</p>
                  </div>
                </CardBody>
              </Card>
            )}

            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/newsletters'))} className="w-full">
              Cancel
            </Button>
          </div>
        </div>
      </div>

      {/* ── Confirm Send Modal ── */}
      <Modal isOpen={confirmSendOpen} onClose={() => setConfirmSendOpen(false)} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <AlertCircle size={20} className="text-warning" />
            Confirm Send
          </ModalHeader>
          <ModalBody>
            <div className="space-y-3">
              <p className="text-sm">Are you sure you want to send this newsletter?</p>
              <Card className="bg-default-50">
                <CardBody className="gap-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-default-500">Subject</span>
                    <span className="font-medium">{subject}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-default-500">Recipients</span>
                    <span className="font-medium text-primary">{recipientCount?.toLocaleString() || '—'}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-default-500">Audience</span>
                    <span className="font-medium">{targetAudience.replace(/_/g, ' ')}</span>
                  </div>
                  {abTestEnabled && (
                    <div className="flex justify-between text-sm">
                      <span className="text-default-500">A/B Test</span>
                      <Chip size="sm" color="warning" variant="flat">Enabled ({abSplitPercentage}/{100 - abSplitPercentage})</Chip>
                    </div>
                  )}
                  {isRecurring && (
                    <div className="flex justify-between text-sm">
                      <span className="text-default-500">Recurring</span>
                      <Chip size="sm" color="secondary" variant="flat">{recurringFrequency}</Chip>
                    </div>
                  )}
                </CardBody>
              </Card>
              <p className="text-xs text-warning-600 dark:text-warning-400">
                This action cannot be undone. The newsletter will be queued for delivery to all targeted recipients.
              </p>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setConfirmSendOpen(false)} isDisabled={sending}>
              Cancel
            </Button>
            <Button
              color="success"
              startContent={<Send size={16} />}
              onPress={handleSendNow}
              isLoading={sending}
            >
              Confirm & Send
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default NewsletterForm;
