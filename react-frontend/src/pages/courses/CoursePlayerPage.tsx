// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CoursePlayerPage — lesson viewer with progress tracking and completion.
 */

import { useEffect, useMemo, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, Spinner, Progress } from '@/components/ui';
import CheckCircle from 'lucide-react/icons/circle-check';
import PlayCircle from 'lucide-react/icons/play-circle';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { coursesApi, type Course, type CourseLesson, type LessonProgress } from '@/lib/api/courses';

export default function CoursePlayerPage() {
  const { t } = useTranslation('courses');
  const { id } = useParams<{ id: string }>();
  const courseId = Number(id);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [course, setCourse] = useState<Course | null>(null);
  const [progress, setProgress] = useState<Record<number, LessonProgress>>({});
  const [percent, setPercent] = useState(0);
  const [activeLessonId, setActiveLessonId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  usePageTitle(course?.title ?? t('title'));

  const lessons: CourseLesson[] = useMemo(
    () => (course?.sections ?? []).flatMap((s) => s.lessons ?? []),
    [course],
  );

  useEffect(() => {
    if (!courseId) return;
    setLoading(true);
    Promise.all([coursesApi.show(courseId), coursesApi.progress(courseId)])
      .then(([courseRes, progRes]) => {
        if (courseRes.success && courseRes.data) setCourse(courseRes.data);
        if (progRes.success && progRes.data) {
          const map: Record<number, LessonProgress> = {};
          progRes.data.lessons.forEach((lp) => { map[lp.lesson_id] = lp; });
          setProgress(map);
          setPercent(Number(progRes.data.enrollment.progress_percent) || 0);
        }
      })
      .finally(() => setLoading(false));
  }, [courseId]);

  useEffect(() => {
    const first = lessons[0];
    if (activeLessonId === null && first) {
      setActiveLessonId(first.id);
    }
  }, [lessons, activeLessonId]);

  const activeLesson = lessons.find((l) => l.id === activeLessonId) ?? null;

  const markComplete = async (lesson: CourseLesson) => {
    const res = await coursesApi.completeLesson(courseId, lesson.id);
    if (res.success && res.data) {
      setProgress((prev) => ({
        ...prev,
        [lesson.id]: { lesson_id: lesson.id, status: 'completed', watch_percent: 100 },
      }));
      setPercent(Number(res.data.progress_percent) || 0);
      if (res.data.course_completed) {
        toast.success(t('player.course_completed_body'));
      } else {
        toast.success(t('player.lesson_completed'));
      }
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-20" role="status" aria-busy="true">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!course) {
    return <div className="max-w-3xl mx-auto px-4 py-16 text-center text-muted">{t('browse.empty')}</div>;
  }

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 flex flex-col lg:flex-row gap-6">
      {/* Lesson list */}
      <aside className="lg:w-72 flex-shrink-0">
        <div className="mb-3">
          <div className="text-xs text-muted mb-1">{t('player.course_progress')}</div>
          <Progress value={percent} aria-label={t('player.course_progress')} />
          <div className="text-xs text-muted mt-1">{Math.round(percent)}%</div>
        </div>
        <nav className="flex flex-col gap-1">
          {lessons.map((lesson) => {
            const done = progress[lesson.id]?.status === 'completed';
            const active = lesson.id === activeLessonId;
            return (
              <button
                key={lesson.id}
                type="button"
                onClick={() => setActiveLessonId(lesson.id)}
                className={`flex items-center gap-2 text-left text-sm px-3 py-2 rounded-md transition-colors ${
                  active ? 'bg-accent-soft text-accent' : 'hover:bg-[var(--color-surface-2)]'
                }`}
              >
                {done ? <CheckCircle size={16} className="text-success" aria-hidden="true" /> : <PlayCircle size={16} aria-hidden="true" />}
                <span className="line-clamp-1">{lesson.title}</span>
              </button>
            );
          })}
        </nav>
        <Button variant="tertiary" size="sm" className="mt-4" onPress={() => navigate(tenantPath(`/courses/${course.slug}`))}>
          {t('player.back_to_course')}
        </Button>
      </aside>

      {/* Active lesson */}
      <div className="flex-1">
        {activeLesson ? (
          <Card>
            <CardBody className="p-5">
              <h1 className="text-xl font-bold mb-4">{activeLesson.title}</h1>
              <LessonContent lesson={activeLesson} />
              <div className="mt-6 flex items-center gap-3">
                {progress[activeLesson.id]?.status === 'completed' ? (
                  <span className="inline-flex items-center gap-1 text-success text-sm">
                    <CheckCircle size={16} aria-hidden="true" /> {t('player.completed')}
                  </span>
                ) : (
                  <Button color="primary" onPress={() => markComplete(activeLesson)}>
                    {t('player.mark_complete')}
                  </Button>
                )}
              </div>
            </CardBody>
          </Card>
        ) : (
          <p className="text-muted">{t('detail.no_lessons')}</p>
        )}
      </div>
    </div>
  );
}

function LessonContent({ lesson }: { lesson: CourseLesson }) {
  switch (lesson.content_type) {
    case 'video':
      return lesson.video_url ? (
        <video controls className="w-full rounded-md" src={lesson.video_url}>
          <track kind="captions" />
        </video>
      ) : null;
    case 'embed':
      return lesson.embed_url ? (
        <div className="aspect-video">
          <iframe title={lesson.title} src={lesson.embed_url} className="w-full h-full rounded-md" allowFullScreen />
        </div>
      ) : null;
    case 'pdf':
      return lesson.attachment_url ? (
        <iframe title={lesson.title} src={lesson.attachment_url} className="w-full h-[70vh] rounded-md" />
      ) : null;
    case 'text':
    default:
      return <div className="prose prose-sm max-w-none whitespace-pre-wrap">{lesson.body}</div>;
  }
}
