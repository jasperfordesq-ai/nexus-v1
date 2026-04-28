// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MunicipalSurveyAdminPage — AG62 Municipality Survey & Feedback Tool
 *
 * Admin management console for Gemeinde-grade surveys.
 * English-only — NO t() calls (admin panel policy).
 *
 * Features:
 *  - Survey list table with status, question count, response count, actions
 *  - 2-step create modal (survey details → question builder)
 *  - Publish / Close actions
 *  - View Analytics modal (response count, per-question breakdown with % bars)
 *  - Export CSV download
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import CheckCircle from 'lucide-react/icons/check-circle';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import Download from 'lucide-react/icons/download';
import Eye from 'lucide-react/icons/eye';
import Plus from 'lucide-react/icons/plus';
import Users from 'lucide-react/icons/users';
import XCircle from 'lucide-react/icons/x-circle';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type SurveyStatus = 'draft' | 'active' | 'closed';
type QuestionType = 'single_choice' | 'multi_choice' | 'likert' | 'open_text' | 'yes_no';

interface SurveyRow {
  id: number;
  title: string;
  status: SurveyStatus;
  is_anonymous: number | boolean;
  question_count: number;
  response_count: number;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
}

interface SurveyQuestion {
  id: number;
  question_text: string;
  question_type: QuestionType;
  options: string[] | null;
  is_required: number | boolean;
  sort_order: number;
}

interface QuestionDraft {
  question_text: string;
  question_type: QuestionType;
  options: string;      // newline-separated raw input
  is_required: boolean;
  sort_order: number;
}

interface AnalyticsQuestion {
  question_id: number;
  question_text: string;
  question_type: QuestionType;
  answer_count: number;
  breakdown?: Array<{ option: string; count: number; percentage: number }>;
  verbatims?: string[];
}

interface Analytics {
  survey_id: number;
  response_count: number;
  daily_chart: Array<{ day: string; count: number }>;
  questions: AnalyticsQuestion[];
}

interface SurveyDetail {
  id: number;
  title: string;
  status: SurveyStatus;
  is_anonymous: number | boolean;
  response_count: number;
  starts_at: string | null;
  ends_at: string | null;
  questions: SurveyQuestion[];
  analytics?: Analytics | null;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const QUESTION_TYPES: Array<{ key: QuestionType; label: string }> = [
  { key: 'single_choice', label: 'Single choice' },
  { key: 'multi_choice', label: 'Multiple choice' },
  { key: 'likert', label: 'Likert scale (1–5)' },
  { key: 'yes_no', label: 'Yes / No' },
  { key: 'open_text', label: 'Open text' },
];

const STATUS_FILTERS = [
  { key: '', label: 'All statuses' },
  { key: 'draft', label: 'Draft' },
  { key: 'active', label: 'Active' },
  { key: 'closed', label: 'Closed' },
];

function statusChip(status: SurveyStatus) {
  const map: Record<SurveyStatus, { color: 'default' | 'success' | 'secondary'; label: string }> = {
    draft:  { color: 'default',   label: 'Draft'  },
    active: { color: 'success',   label: 'Active' },
    closed: { color: 'secondary', label: 'Closed' },
  };
  const { color, label } = map[status] ?? { color: 'default', label: status };
  return <Chip size="sm" color={color} variant="flat">{label}</Chip>;
}

function formatDate(ts: string | null): string {
  if (!ts) return '—';
  return new Date(ts).toLocaleDateString();
}

function emptyQuestion(idx: number): QuestionDraft {
  return {
    question_text: '',
    question_type: 'single_choice',
    options: '',
    is_required: true,
    sort_order: idx,
  };
}

// ---------------------------------------------------------------------------
// Analytics Modal Content
// ---------------------------------------------------------------------------

function AnalyticsView({ analytics }: { analytics: Analytics }) {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center gap-2 text-sm text-default-500">
        <Users size={14} />
        <span>Total responses: <strong>{analytics.response_count}</strong></span>
      </div>

      {analytics.questions.map((q) => (
        <div key={q.question_id} className="flex flex-col gap-2">
          <p className="font-medium text-sm">{q.question_text}</p>
          <p className="text-xs text-default-400 capitalize">{q.question_type.replace('_', ' ')} · {q.answer_count} answer{q.answer_count !== 1 ? 's' : ''}</p>

          {q.question_type === 'open_text' && q.verbatims && (
            <ul className="list-disc list-inside flex flex-col gap-1">
              {q.verbatims.map((v, i) => (
                <li key={i} className="text-sm text-default-700">{v}</li>
              ))}
              {q.verbatims.length === 0 && (
                <li className="text-sm text-default-400">No open-text responses yet.</li>
              )}
            </ul>
          )}

          {q.breakdown && q.breakdown.map((b) => (
            <div key={b.option} className="flex items-center gap-3">
              <span className="text-xs w-40 shrink-0 truncate">{b.option}</span>
              <div className="flex-1 bg-default-100 rounded-full h-2 overflow-hidden">
                <div
                  className="h-2 bg-primary rounded-full transition-all"
                  style={{ width: `${b.percentage}%` }}
                />
              </div>
              <span className="text-xs text-default-500 w-20 text-right shrink-0">
                {b.percentage}% ({b.count})
              </span>
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function MunicipalSurveyAdminPage() {
  const createModal    = useDisclosure();
  const analyticsModal = useDisclosure();

  const [surveys, setSurveys] = useState<SurveyRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('');

  // Create form state
  const [createStep, setCreateStep] = useState<1 | 2>(1);
  const [title, setTitle]           = useState('');
  const [description, setDescription] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [startsAt, setStartsAt]     = useState('');
  const [endsAt, setEndsAt]         = useState('');
  const [questions, setQuestions]   = useState<QuestionDraft[]>([emptyQuestion(0)]);
  const [creating, setCreating]     = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  // Analytics state
  const [analyticsData, setAnalyticsData]   = useState<Analytics | null>(null);
  const [analyticsTitle, setAnalyticsTitle] = useState('');
  const [analyticsLoading, setAnalyticsLoading] = useState(false);

  // Per-row action state
  const [actionId, setActionId] = useState<number | null>(null);

  // ── Fetch surveys ──────────────────────────────────────────────────────────

  const fetchSurveys = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const url = statusFilter
        ? `/v2/admin/caring-community/surveys?status=${statusFilter}`
        : '/v2/admin/caring-community/surveys';
      const res = await api.get<{ data: SurveyRow[] } | SurveyRow[]>(url);
      const raw = res.data;
      const list: SurveyRow[] = Array.isArray(raw)
        ? raw
        : (Array.isArray((raw as { data?: SurveyRow[] }).data)
            ? (raw as { data: SurveyRow[] }).data
            : []);
      setSurveys(list);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to load surveys');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => { void fetchSurveys(); }, [fetchSurveys]);

  // ── Create survey ──────────────────────────────────────────────────────────

  const openCreate = () => {
    setCreateStep(1);
    setTitle('');
    setDescription('');
    setIsAnonymous(false);
    setStartsAt('');
    setEndsAt('');
    setQuestions([emptyQuestion(0)]);
    setCreateError(null);
    createModal.onOpen();
  };

  const handleCreate = async () => {
    if (!title.trim()) return;
    setCreating(true);
    setCreateError(null);
    try {
      const payload = {
        title: title.trim(),
        description: description.trim() || null,
        is_anonymous: isAnonymous,
        starts_at: startsAt || null,
        ends_at: endsAt || null,
        questions: questions
          .filter((q) => q.question_text.trim())
          .map((q, i) => ({
            question_text: q.question_text.trim(),
            question_type: q.question_type,
            options: ['single_choice', 'multi_choice'].includes(q.question_type)
              ? q.options.split('\n').map((o) => o.trim()).filter(Boolean)
              : null,
            is_required: q.is_required,
            sort_order: i,
          })),
      };
      await api.post('/v2/admin/caring-community/surveys', payload);
      createModal.onClose();
      await fetchSurveys();
    } catch (e: unknown) {
      setCreateError(e instanceof Error ? e.message : 'Failed to create survey');
    } finally {
      setCreating(false);
    }
  };

  // ── Question builder helpers ───────────────────────────────────────────────

  const addQuestion = () =>
    setQuestions((prev) => [...prev, emptyQuestion(prev.length)]);

  const removeQuestion = (idx: number) =>
    setQuestions((prev) => prev.filter((_, i) => i !== idx));

  const updateQuestion = <K extends keyof QuestionDraft>(
    idx: number,
    key: K,
    value: QuestionDraft[K]
  ) =>
    setQuestions((prev) =>
      prev.map((q, i) => (i === idx ? { ...q, [key]: value } : q))
    );

  // ── Publish / Close ────────────────────────────────────────────────────────

  const handlePublish = async (id: number) => {
    setActionId(id);
    try {
      await api.post(`/v2/admin/caring-community/surveys/${id}/publish`);
      await fetchSurveys();
    } finally {
      setActionId(null);
    }
  };

  const handleClose = async (id: number) => {
    setActionId(id);
    try {
      await api.post(`/v2/admin/caring-community/surveys/${id}/close`);
      await fetchSurveys();
    } finally {
      setActionId(null);
    }
  };

  // ── Analytics ──────────────────────────────────────────────────────────────

  const openAnalytics = async (survey: SurveyRow) => {
    setAnalyticsTitle(survey.title);
    setAnalyticsData(null);
    setAnalyticsLoading(true);
    analyticsModal.onOpen();
    try {
      const res = await api.get<{ data: SurveyDetail } | SurveyDetail>(
        `/v2/admin/caring-community/surveys/${survey.id}`
      );
      const raw = res.data;
      const detail: SurveyDetail = 'data' in raw ? (raw as { data: SurveyDetail }).data : raw;
      if (detail.analytics) {
        setAnalyticsData(detail.analytics);
      }
    } finally {
      setAnalyticsLoading(false);
    }
  };

  // ── CSV export ─────────────────────────────────────────────────────────────

  const handleExport = async (id: number, surveyTitle: string) => {
    try {
      const res = await api.get<BlobPart>(
        `/v2/admin/caring-community/surveys/${id}/export`,
        { responseType: 'blob' }
      );
      const blob = new Blob([res.data as BlobPart], { type: 'text/csv' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href     = url;
      a.download = `survey-${id}-${surveyTitle.replace(/\s+/g, '-').toLowerCase()}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // Silently ignore — the button is still clickable next time
    }
  };

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <>
      <Card>
        <CardHeader className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <ClipboardList size={20} className="text-primary" />
            <h2 className="text-lg font-semibold">Municipality Surveys (AG62)</h2>
          </div>
          <div className="flex items-center gap-3 flex-wrap">
            <Select
              size="sm"
              aria-label="Filter by status"
              selectedKeys={[statusFilter]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string ?? '';
                setStatusFilter(val);
              }}
              className="w-40"
              variant="bordered"
            >
              {STATUS_FILTERS.map((f) => (
                <SelectItem key={f.key} textValue={f.label}>
                  {f.label}
                </SelectItem>
              ))}
            </Select>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={openCreate}
            >
              Create Survey
            </Button>
          </div>
        </CardHeader>
        <Divider />
        <CardBody>
          {loading && (
            <div className="flex justify-center py-10">
              <Spinner size="lg" />
            </div>
          )}
          {!loading && error && (
            <p className="text-danger text-sm">{error}</p>
          )}
          {!loading && !error && surveys.length === 0 && (
            <p className="text-default-400 text-sm py-4">No surveys yet.</p>
          )}
          {!loading && !error && surveys.length > 0 && (
            <Table aria-label="Municipality surveys" removeWrapper>
              <TableHeader>
                <TableColumn>Title</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Questions</TableColumn>
                <TableColumn>Responses</TableColumn>
                <TableColumn>Anonymous</TableColumn>
                <TableColumn>Ends</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {surveys.map((survey) => (
                  <TableRow key={survey.id}>
                    <TableCell>
                      <p className="font-medium text-sm">{survey.title}</p>
                      <p className="text-xs text-default-400">{formatDate(survey.created_at)}</p>
                    </TableCell>
                    <TableCell>{statusChip(survey.status)}</TableCell>
                    <TableCell>
                      <span className="text-sm">{survey.question_count}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        <Users size={12} className="text-default-400" />
                        <span className="text-sm">{survey.response_count}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      {Boolean(survey.is_anonymous) ? (
                        <Chip size="sm" color="default" variant="flat">Yes</Chip>
                      ) : (
                        <span className="text-xs text-default-400">No</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="text-xs">{formatDate(survey.ends_at)}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2 flex-wrap">
                        {survey.status === 'draft' && (
                          <Button
                            size="sm"
                            color="success"
                            variant="flat"
                            startContent={<CheckCircle size={12} />}
                            isLoading={actionId === survey.id}
                            onPress={() => void handlePublish(survey.id)}
                          >
                            Publish
                          </Button>
                        )}
                        {survey.status === 'active' && (
                          <Button
                            size="sm"
                            color="warning"
                            variant="flat"
                            startContent={<XCircle size={12} />}
                            isLoading={actionId === survey.id}
                            onPress={() => void handleClose(survey.id)}
                          >
                            Close
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Eye size={12} />}
                          onPress={() => void openAnalytics(survey)}
                        >
                          Analytics
                        </Button>
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Download size={12} />}
                          onPress={() => void handleExport(survey.id, survey.title)}
                        >
                          CSV
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* ── Create Survey Modal ────────────────────────────────────────────── */}
      <Modal
        isOpen={createModal.isOpen}
        onClose={createModal.onClose}
        size="2xl"
        isDismissable={!creating}
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <ClipboardList size={18} className="text-primary" />
            Create Survey — Step {createStep} of 2
          </ModalHeader>
          <ModalBody>
            {createStep === 1 && (
              <div className="flex flex-col gap-4">
                <Input
                  label="Survey title"
                  placeholder="e.g. Satisfaction with local cycling lanes"
                  value={title}
                  onValueChange={setTitle}
                  isRequired
                  variant="bordered"
                  maxLength={255}
                />
                <Textarea
                  label="Description (optional)"
                  placeholder="Briefly explain what this survey is about and why it matters."
                  value={description}
                  onValueChange={setDescription}
                  variant="bordered"
                  minRows={2}
                  maxRows={6}
                />
                <div className="flex items-center gap-3">
                  <Switch
                    isSelected={isAnonymous}
                    onValueChange={setIsAnonymous}
                    size="sm"
                  />
                  <div>
                    <p className="text-sm font-medium">Anonymous responses</p>
                    <p className="text-xs text-default-400">
                      When enabled, respondent identities are NOT stored.
                    </p>
                  </div>
                </div>
                <div className="flex gap-3">
                  <Input
                    label="Opens at (optional)"
                    type="datetime-local"
                    value={startsAt}
                    onValueChange={setStartsAt}
                    variant="bordered"
                    className="flex-1"
                  />
                  <Input
                    label="Closes at (optional)"
                    type="datetime-local"
                    value={endsAt}
                    onValueChange={setEndsAt}
                    variant="bordered"
                    className="flex-1"
                  />
                </div>
              </div>
            )}

            {createStep === 2 && (
              <div className="flex flex-col gap-6">
                <p className="text-sm text-default-500">
                  Add the questions respondents will see. At least one question required to publish.
                </p>
                {questions.map((q, idx) => (
                  <Card key={idx} className="border border-default-200" shadow="none">
                    <CardBody className="flex flex-col gap-3">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-default-500 uppercase tracking-wide">
                          Question {idx + 1}
                        </span>
                        {questions.length > 1 && (
                          <Button
                            size="sm"
                            color="danger"
                            variant="light"
                            onPress={() => removeQuestion(idx)}
                          >
                            Remove
                          </Button>
                        )}
                      </div>
                      <Input
                        label="Question text"
                        value={q.question_text}
                        onValueChange={(v) => updateQuestion(idx, 'question_text', v)}
                        variant="bordered"
                        maxLength={500}
                        isRequired
                      />
                      <Select
                        label="Question type"
                        selectedKeys={[q.question_type]}
                        onSelectionChange={(keys) => {
                          const val = Array.from(keys)[0] as QuestionType;
                          if (val) updateQuestion(idx, 'question_type', val);
                        }}
                        variant="bordered"
                      >
                        {QUESTION_TYPES.map((qt) => (
                          <SelectItem key={qt.key} textValue={qt.label}>
                            {qt.label}
                          </SelectItem>
                        ))}
                      </Select>
                      {['single_choice', 'multi_choice'].includes(q.question_type) && (
                        <Textarea
                          label="Options (one per line)"
                          placeholder={"Option A\nOption B\nOption C"}
                          value={q.options}
                          onValueChange={(v) => updateQuestion(idx, 'options', v)}
                          variant="bordered"
                          minRows={3}
                          description="Each line becomes one selectable option."
                        />
                      )}
                      {q.question_type === 'likert' && (
                        <p className="text-xs text-default-400">
                          Likert scale uses the standard 5-point scale (Very dissatisfied → Very satisfied).
                        </p>
                      )}
                      <div className="flex items-center gap-2">
                        <Switch
                          isSelected={q.is_required}
                          onValueChange={(v) => updateQuestion(idx, 'is_required', v)}
                          size="sm"
                        />
                        <span className="text-sm">Required</span>
                      </div>
                    </CardBody>
                  </Card>
                ))}
                <Button
                  variant="dashed"
                  startContent={<Plus size={14} />}
                  onPress={addQuestion}
                  className="w-full"
                >
                  Add question
                </Button>
                {createError && <p className="text-danger text-sm">{createError}</p>}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            {createStep === 2 && (
              <Button
                variant="flat"
                onPress={() => setCreateStep(1)}
                isDisabled={creating}
              >
                Back
              </Button>
            )}
            <Button
              variant="flat"
              onPress={createModal.onClose}
              isDisabled={creating}
            >
              Cancel
            </Button>
            {createStep === 1 && (
              <Button
                color="primary"
                onPress={() => setCreateStep(2)}
                isDisabled={!title.trim()}
              >
                Next: Questions
              </Button>
            )}
            {createStep === 2 && (
              <Button
                color="primary"
                startContent={<BarChart3 size={16} />}
                onPress={() => void handleCreate()}
                isLoading={creating}
                isDisabled={creating}
              >
                Create Survey
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ── Analytics Modal ────────────────────────────────────────────────── */}
      <Modal
        isOpen={analyticsModal.isOpen}
        onClose={analyticsModal.onClose}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <BarChart3 size={18} className="text-primary" />
            Analytics — {analyticsTitle}
          </ModalHeader>
          <ModalBody>
            {analyticsLoading && (
              <div className="flex justify-center py-8">
                <Spinner size="lg" />
              </div>
            )}
            {!analyticsLoading && analyticsData && (
              <AnalyticsView analytics={analyticsData} />
            )}
            {!analyticsLoading && !analyticsData && (
              <p className="text-default-400 text-sm py-4">No analytics data available yet.</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={analyticsModal.onClose}>Close</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
