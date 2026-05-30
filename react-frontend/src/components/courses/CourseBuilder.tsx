// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CourseBuilder — curriculum editor for a course: sections with inline-editable
 * titles containing lessons, each lesson with an inline editor (title, content
 * type, and the matching content field). Supports add / edit / delete / reorder
 * for both sections and lessons, persisting every change via the courses API.
 *
 * Patterns follow mainstream LMS builders (Thinkific / Teachable / Circle /
 * LearnDash): a single curriculum view where everything is editable in place.
 */

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, Input, Textarea, Select, SelectItem, Switch } from '@/components/ui';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import ChevronUp from 'lucide-react/icons/chevron-up';
import ChevronDown from 'lucide-react/icons/chevron-down';
import GripVertical from 'lucide-react/icons/grip-vertical';
import { useToast } from '@/contexts';
import { coursesApi, type CourseSection, type CourseLesson, type LessonContentType } from '@/lib/api/courses';

const CONTENT_TYPES: LessonContentType[] = ['text', 'video', 'pdf', 'embed'];

interface CourseBuilderProps {
  courseId: number;
  initialSections: CourseSection[];
}

export function CourseBuilder({ courseId, initialSections }: CourseBuilderProps) {
  const { t } = useTranslation('courses');
  const toast = useToast();
  const [sections, setSections] = useState<CourseSection[]>(
    () => (initialSections ?? []).map((s) => ({ ...s, lessons: s.lessons ?? [] })),
  );

  const addSection = async () => {
    const res = await coursesApi.createSection(courseId, { title: t('builder.new_section'), position: sections.length });
    if (res.success && res.data) {
      setSections((prev) => [...prev, { ...res.data!, lessons: [] }]);
    } else {
      toast.error(t('builder.save_error'));
    }
  };

  const renameSection = async (sectionId: number, title: string) => {
    setSections((prev) => prev.map((s) => (s.id === sectionId ? { ...s, title } : s)));
    await coursesApi.updateSection(courseId, sectionId, { title });
  };

  const deleteSection = async (sectionId: number) => {
    await coursesApi.deleteSection(courseId, sectionId);
    setSections((prev) => prev.filter((s) => s.id !== sectionId));
  };

  const moveSection = async (index: number, dir: -1 | 1) => {
    const target = index + dir;
    if (target < 0 || target >= sections.length) return;
    const next = [...sections];
    [next[index], next[target]] = [next[target]!, next[index]!];
    setSections(next);
    await Promise.all([
      coursesApi.updateSection(courseId, next[index]!.id, { position: index }),
      coursesApi.updateSection(courseId, next[target]!.id, { position: target }),
    ]);
  };

  const addLesson = async (sectionId: number) => {
    const section = sections.find((s) => s.id === sectionId);
    const res = await coursesApi.createLesson(courseId, {
      section_id: sectionId,
      title: t('builder.new_lesson'),
      content_type: 'text',
      position: section?.lessons?.length ?? 0,
    });
    if (res.success && res.data) {
      setSections((prev) => prev.map((s) => (s.id === sectionId ? { ...s, lessons: [...(s.lessons ?? []), res.data!] } : s)));
    } else {
      toast.error(t('builder.save_error'));
    }
  };

  const updateLessonLocal = (sectionId: number, lesson: CourseLesson) => {
    setSections((prev) => prev.map((s) => (
      s.id === sectionId ? { ...s, lessons: (s.lessons ?? []).map((l) => (l.id === lesson.id ? lesson : l)) } : s
    )));
  };

  const deleteLesson = async (sectionId: number, lessonId: number) => {
    await coursesApi.deleteLesson(courseId, lessonId);
    setSections((prev) => prev.map((s) => (
      s.id === sectionId ? { ...s, lessons: (s.lessons ?? []).filter((l) => l.id !== lessonId) } : s
    )));
  };

  const moveLesson = async (sectionId: number, index: number, dir: -1 | 1) => {
    const section = sections.find((s) => s.id === sectionId);
    if (!section) return;
    const lessons = [...(section.lessons ?? [])];
    const target = index + dir;
    if (target < 0 || target >= lessons.length) return;
    [lessons[index], lessons[target]] = [lessons[target]!, lessons[index]!];
    setSections((prev) => prev.map((s) => (s.id === sectionId ? { ...s, lessons } : s)));
    await Promise.all([
      coursesApi.updateLesson(courseId, lessons[index]!.id, { position: index }),
      coursesApi.updateLesson(courseId, lessons[target]!.id, { position: target }),
    ]);
  };

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold">{t('instructor.builder')}</h2>
        <Button size="sm" color="primary" startContent={<Plus size={16} />} onPress={addSection}>
          {t('builder.add_section')}
        </Button>
      </div>

      {sections.length === 0 ? (
        <Card><CardBody className="p-6 text-center text-muted text-sm">{t('builder.empty')}</CardBody></Card>
      ) : (
        <div className="flex flex-col gap-4">
          {sections.map((section, si) => (
            <Card key={section.id}>
              <CardBody className="p-4">
                <div className="flex items-center gap-2 mb-3">
                  <GripVertical size={16} className="text-muted flex-shrink-0" aria-hidden="true" />
                  <Input
                    size="sm"
                    aria-label={t('builder.section_name')}
                    defaultValue={section.title}
                    onBlur={(e) => {
                      const v = (e.target as HTMLInputElement).value.trim();
                      if (v && v !== section.title) renameSection(section.id, v);
                    }}
                    className="flex-1"
                  />
                  <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.move_up')} isDisabled={si === 0} onPress={() => moveSection(si, -1)}><ChevronUp size={14} /></Button>
                  <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.move_down')} isDisabled={si === sections.length - 1} onPress={() => moveSection(si, 1)}><ChevronDown size={14} /></Button>
                  <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.delete_section')} onPress={() => deleteSection(section.id)}><Trash2 size={14} /></Button>
                </div>

                <div className="flex flex-col gap-2 pl-6">
                  {(section.lessons ?? []).map((lesson, li) => (
                    <LessonRow
                      key={lesson.id}
                      courseId={courseId}
                      lesson={lesson}
                      isFirst={li === 0}
                      isLast={li === (section.lessons?.length ?? 0) - 1}
                      onChange={(l) => updateLessonLocal(section.id, l)}
                      onDelete={() => deleteLesson(section.id, lesson.id)}
                      onMove={(dir) => moveLesson(section.id, li, dir)}
                    />
                  ))}
                  <div>
                    <Button size="sm" variant="tertiary" startContent={<Plus size={14} />} onPress={() => addLesson(section.id)}>
                      {t('builder.add_lesson')}
                    </Button>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </section>
  );
}

interface LessonRowProps {
  courseId: number;
  lesson: CourseLesson;
  isFirst: boolean;
  isLast: boolean;
  onChange: (lesson: CourseLesson) => void;
  onDelete: () => void;
  onMove: (dir: -1 | 1) => void;
}

function LessonRow({ courseId, lesson, isFirst, isLast, onChange, onDelete, onMove }: LessonRowProps) {
  const { t } = useTranslation('courses');
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<CourseLesson>(lesson);
  const [saving, setSaving] = useState(false);

  const set = (patch: Partial<CourseLesson>) => setDraft((d) => ({ ...d, ...patch }));

  const save = async () => {
    setSaving(true);
    const res = await coursesApi.updateLesson(courseId, lesson.id, {
      title: draft.title,
      content_type: draft.content_type,
      body: draft.body,
      video_url: draft.video_url,
      embed_url: draft.embed_url,
      attachment_url: draft.attachment_url,
      is_preview: draft.is_preview,
    });
    setSaving(false);
    if (res.success) {
      onChange(draft);
      toast.success(t('builder.lesson_saved'));
    } else {
      toast.error(t('builder.save_error'));
    }
  };

  return (
    <div className="rounded-md border border-[var(--color-border)]">
      <div className="flex items-center gap-2 px-3 py-2">
        <button type="button" className="flex-1 text-left text-sm" onClick={() => setOpen((o) => !o)}>
          {draft.title || t('builder.untitled_lesson')}
          <span className="ml-2 text-xs text-muted">· {t(`lesson_content.${draft.content_type}`)}</span>
        </button>
        <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.move_up')} isDisabled={isFirst} onPress={() => onMove(-1)}><ChevronUp size={14} /></Button>
        <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.move_down')} isDisabled={isLast} onPress={() => onMove(1)}><ChevronDown size={14} /></Button>
        <Button isIconOnly size="sm" variant="tertiary" aria-label={t('builder.delete_lesson')} onPress={onDelete}><Trash2 size={14} /></Button>
      </div>

      {open ? (
        <div className="px-3 pb-3 flex flex-col gap-3 border-t border-[var(--color-border)] pt-3">
          <Input size="sm" label={t('builder.lesson_name')} value={draft.title} onValueChange={(v) => set({ title: v })} />
          <Select
            size="sm"
            label={t('builder.content_type')}
            selectedKeys={[draft.content_type]}
            onSelectionChange={(k) => set({ content_type: (Array.from(k)[0] as LessonContentType) ?? 'text' })}
          >
            {CONTENT_TYPES.map((ct) => <SelectItem key={ct} id={ct}>{t(`lesson_content.${ct}`)}</SelectItem>)}
          </Select>

          {draft.content_type === 'text' ? (
            <Textarea label={t('builder.body')} value={draft.body ?? ''} onValueChange={(v) => set({ body: v })} rows={5} />
          ) : null}
          {draft.content_type === 'video' ? (
            <Input size="sm" label={t('builder.video_url')} placeholder="https://…" value={draft.video_url ?? ''} onValueChange={(v) => set({ video_url: v })} />
          ) : null}
          {draft.content_type === 'embed' ? (
            <Input size="sm" label={t('builder.embed_url')} placeholder="https://…" value={draft.embed_url ?? ''} onValueChange={(v) => set({ embed_url: v })} />
          ) : null}
          {draft.content_type === 'pdf' ? (
            <Input size="sm" label={t('builder.attachment_url')} placeholder="https://…" value={draft.attachment_url ?? ''} onValueChange={(v) => set({ attachment_url: v })} />
          ) : null}

          <div className="flex items-center gap-2">
            <Switch isSelected={!!draft.is_preview} onValueChange={(v) => set({ is_preview: v })} aria-label={t('builder.free_preview')} />
            <span className="text-sm">{t('builder.free_preview')}</span>
          </div>

          <div>
            <Button size="sm" color="primary" isLoading={saving} onPress={save}>{t('builder.save_lesson')}</Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default CourseBuilder;
