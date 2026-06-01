// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CourseGradingPage — instructor grading queue for quiz attempts that contain
 * subjective (short-answer / essay) questions awaiting manual review.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Avatar, Button, Card, CardBody, Input, Spinner, Switch, Textarea } from '@/components/ui';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { coursesApi, type PendingAttempt, type QuizQuestion } from '@/lib/api/courses';

/**
 * Render a learner's answer to a question in human-readable form: map objective
 * option id(s) back to their labels; show short-answer/essay text as-is.
 */
function formatAnswer(question: QuizQuestion, answers: PendingAttempt['answers']): string {
  if (!answers) return '';
  const raw = answers[String(question.id)];
  if (raw == null || raw === '') return '';
  if (question.options && question.options.length) {
    const ids = Array.isArray(raw) ? raw.map(String) : [String(raw)];
    return ids.map((id) => question.options!.find((o) => o.id === id)?.label ?? id).join(', ');
  }
  return Array.isArray(raw) ? raw.map(String).join(', ') : String(raw);
}

export default function CourseGradingPage() {
  const { t } = useTranslation('courses');
  const { id } = useParams<{ id: string }>();
  const courseId = Number(id);
  const { tenantPath } = useTenant();

  const [attempts, setAttempts] = useState<PendingAttempt[]>([]);
  const [loading, setLoading] = useState(true);

  usePageTitle(t('grading.title'));

  const load = useCallback(() => {
    setLoading(true);
    coursesApi.gradingQueue(courseId)
      .then((res) => setAttempts(res.success && res.data ? res.data : []))
      .finally(() => setLoading(false));
  }, [courseId]);

  useEffect(load, [load]);

  if (loading) {
    return <div className="flex justify-center py-20" role="status" aria-busy="true"><Spinner size="lg" /></div>;
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-6">
      <Button as={Link} to={tenantPath('/courses/instructor')} variant="tertiary" size="sm" startContent={<ArrowLeft size={16} />} className="mb-4">
        {t('instructor.dashboard')}
      </Button>
      <h1 className="text-2xl font-bold mb-6">{t('grading.title')}</h1>

      {attempts.length === 0 ? (
        <p className="text-sm text-muted">{t('grading.empty')}</p>
      ) : (
        <div className="flex flex-col gap-4">
          {attempts.map((a) => (
            <GradeCard key={a.id} attempt={a} onGraded={load} />
          ))}
        </div>
      )}
    </div>
  );
}

function GradeCard({ attempt, onGraded }: { attempt: PendingAttempt; onGraded: () => void }) {
  const { t } = useTranslation('courses');
  const toast = useToast();
  const [score, setScore] = useState('70');
  const [passed, setPassed] = useState(true);
  const [feedback, setFeedback] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    setSaving(true);
    const res = await coursesApi.gradeAttempt(attempt.id, Number(score) || 0, passed, feedback.trim());
    setSaving(false);
    if (res.success) {
      toast.success(t('grading.graded'));
      onGraded();
    } else {
      toast.error(t('grading.error'));
    }
  };

  return (
    <Card>
      <CardBody className="p-4 flex flex-col gap-3">
        <div className="flex items-center gap-2">
          <Avatar size="sm" src={attempt.user?.avatar_url ?? undefined} name={attempt.user?.name ?? '?'} />
          <div>
            <div className="text-sm font-semibold">{attempt.user?.name ?? `#${attempt.user_id}`}</div>
            <div className="text-xs text-muted">{attempt.quiz?.title}</div>
          </div>
        </div>

        {attempt.quiz?.questions?.length ? (
          <div className="flex flex-col gap-2">
            {attempt.quiz.questions.map((q) => {
              const ans = formatAnswer(q, attempt.answers);
              return (
                <div key={q.id} className="rounded-md border border-[var(--color-border)] p-3">
                  <div className="text-sm font-medium whitespace-pre-wrap">{q.prompt}</div>
                  <div className="mt-1 text-sm whitespace-pre-wrap">
                    <span className="text-muted">{t('grading.answer')}: </span>
                    {ans ? <span>{ans}</span> : <span className="italic text-muted">{t('grading.no_answer')}</span>}
                  </div>
                </div>
              );
            })}
          </div>
        ) : attempt.answers && Object.keys(attempt.answers).length ? (
          // Defensive fallback when question metadata is unavailable — readable
          // lines rather than a raw JSON blob.
          <div className="flex flex-col gap-1 text-sm">
            {Object.entries(attempt.answers).map(([qid, val]) => (
              <div key={qid} className="whitespace-pre-wrap">
                <span className="text-muted">#{qid}: </span>
                {Array.isArray(val) ? val.map(String).join(', ') : String(val ?? '')}
              </div>
            ))}
          </div>
        ) : null}

        <div className="flex items-end gap-3 flex-wrap">
          <Input
            size="sm"
            type="number"
            label={t('grading.score')}
            value={score}
            onValueChange={setScore}
            className="w-28"
          />
          <div className="flex items-center gap-2">
            <Switch isSelected={passed} onValueChange={setPassed} aria-label={t('grading.passed')} />
            <span className="text-sm">{t('grading.passed')}</span>
          </div>
        </div>
        <Textarea
          aria-label={t('grading.feedback')}
          placeholder={t('grading.feedback')}
          value={feedback}
          onValueChange={setFeedback}
          rows={2}
        />
        <div>
          <Button color="primary" size="sm" isLoading={saving} onPress={submit}>{t('grading.submit')}</Button>
        </div>
      </CardBody>
    </Card>
  );
}
