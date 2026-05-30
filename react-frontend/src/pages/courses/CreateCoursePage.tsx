// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CreateCoursePage — course builder (create + edit) for instructors/admins.
 * Create mode captures the course details; once saved, the section/lesson
 * builder unlocks (edit mode).
 */

import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, Input, Textarea, Select, SelectItem, Spinner } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { coursesApi, type Course, type CourseCategory, type CourseSection } from '@/lib/api/courses';
import { CourseBuilder } from '@/components/courses/CourseBuilder';

const LEVELS = ['beginner', 'intermediate', 'advanced'] as const;
const VISIBILITIES = ['public', 'members'] as const;

export default function CreateCoursePage() {
  const { t } = useTranslation('courses');
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const courseId = Number(id);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  usePageTitle(isEdit ? t('instructor.edit_course') : t('instructor.new_course'));

  const [categories, setCategories] = useState<CourseCategory[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [sections, setSections] = useState<CourseSection[]>([]);

  const [form, setForm] = useState({
    title: '',
    summary: '',
    description: '',
    level: 'beginner',
    visibility: 'members',
    category_id: '',
  });

  useEffect(() => {
    coursesApi.categories().then((res) => { if (res.success && res.data) setCategories(res.data); });
  }, []);

  useEffect(() => {
    if (!isEdit) return;
    setLoading(true);
    coursesApi.show(courseId)
      .then((res) => {
        if (res.success && res.data) {
          const c = res.data;
          setForm({
            title: c.title ?? '',
            summary: c.summary ?? '',
            description: c.description ?? '',
            level: c.level ?? 'beginner',
            visibility: c.visibility === 'group' ? 'members' : (c.visibility ?? 'members'),
            category_id: c.category_id ? String(c.category_id) : '',
          });
          setSections(c.sections ?? []);
        }
      })
      .finally(() => setLoading(false));
  }, [isEdit, courseId]);

  const saveDetails = async () => {
    if (!form.title.trim()) {
      toast.error(t('form.required'));
      return;
    }
    setSaving(true);
    const payload: Partial<Course> = {
      title: form.title,
      summary: form.summary,
      description: form.description,
      level: form.level as Course['level'],
      visibility: form.visibility as Course['visibility'],
      category_id: form.category_id ? Number(form.category_id) : null,
    };
    const res = isEdit
      ? await coursesApi.update(courseId, payload)
      : await coursesApi.create(payload);
    setSaving(false);
    if (res.success && res.data) {
      toast.success(t('instructor.saved'));
      if (!isEdit) navigate(tenantPath(`/courses/instructor/${res.data.id}/edit`));
    } else {
      toast.error(t('instructor.create_error'));
    }
  };

  if (loading) {
    return <div className="flex justify-center py-20" role="status" aria-busy="true"><Spinner size="lg" /></div>;
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-6">
      <h1 className="text-2xl font-bold mb-6">{isEdit ? t('instructor.edit_course') : t('instructor.new_course')}</h1>

      <Card className="mb-6">
        <CardBody className="p-5 flex flex-col gap-4">
          <Input label={t('instructor.title_label')} value={form.title} onValueChange={(v) => setForm({ ...form, title: v })} isRequired />
          <Input label={t('instructor.summary_label')} value={form.summary} onValueChange={(v) => setForm({ ...form, summary: v })} />
          <Textarea label={t('instructor.description_label')} value={form.description} onValueChange={(v) => setForm({ ...form, description: v })} rows={5} />
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <Select label={t('instructor.level_label')} selectedKeys={[form.level]} onSelectionChange={(k) => setForm({ ...form, level: (Array.from(k)[0] as string) ?? 'beginner' })}>
              {LEVELS.map((l) => <SelectItem key={l} id={l}>{t(`level.${l}`)}</SelectItem>)}
            </Select>
            <Select label={t('instructor.visibility_label')} selectedKeys={[form.visibility]} onSelectionChange={(k) => setForm({ ...form, visibility: (Array.from(k)[0] as string) ?? 'members' })}>
              {VISIBILITIES.map((v) => <SelectItem key={v} id={v}>{t(`instructor.visibility_${v}`)}</SelectItem>)}
            </Select>
            <Select label={t('instructor.category_label')} selectedKeys={[form.category_id]} onSelectionChange={(k) => setForm({ ...form, category_id: (Array.from(k)[0] as string) ?? '' })}>
              <SelectItem id="">—</SelectItem>
              {categories.map((c) => <SelectItem key={c.id} id={String(c.id)}>{c.name}</SelectItem>)}
            </Select>
          </div>
          <div>
            <Button color="primary" isLoading={saving} onPress={saveDetails}>{t('instructor.save')}</Button>
          </div>
        </CardBody>
      </Card>

      {isEdit && (
        <>
          <CourseBuilder courseId={courseId} initialSections={sections} />
          <div className="mt-6 flex items-center gap-2">
            <Button as={Link} to={tenantPath('/courses/instructor')} variant="secondary" size="sm">
              {t('builder.done')}
            </Button>
            <Button as={Link} to={tenantPath(`/courses/${courseId}/learn`)} variant="tertiary" size="sm">
              {t('builder.preview_course')}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}
