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
import { Button, Card, CardBody, Input, Textarea, Select, SelectItem, Spinner, Chip } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { coursesApi, type Course, type CourseCategory, type CourseSection } from '@/lib/api/courses';
import { CourseBuilder } from '@/components/courses/CourseBuilder';

const LEVELS = ['beginner', 'intermediate', 'advanced'] as const;
const VISIBILITIES = ['public', 'members'] as const;
const ENROLLMENT_TYPES = ['self_paced', 'cohort'] as const;

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
  const [publishing, setPublishing] = useState(false);
  const [status, setStatus] = useState<string>('draft');
  const [moderationStatus, setModerationStatus] = useState<string>('pending');
  const [sections, setSections] = useState<CourseSection[]>([]);
  const [cohorts, setCohorts] = useState<Array<{ id: number; name: string; start_date?: string | null; end_date?: string | null; capacity?: number | null }>>([]);
  const [cohortName, setCohortName] = useState('');

  const [form, setForm] = useState({
    title: '',
    summary: '',
    description: '',
    level: 'beginner',
    visibility: 'members',
    enrollment_type: 'self_paced',
    category_id: '',
    credit_cost: '0',
    prerequisites: '',
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
            enrollment_type: c.enrollment_type ?? 'self_paced',
            category_id: c.category_id ? String(c.category_id) : '',
            credit_cost: String(c.credit_cost ?? 0),
            prerequisites: Array.isArray(c.prerequisites) ? c.prerequisites.join(', ') : '',
          });
          setSections(c.sections ?? []);
          setStatus(c.status ?? 'draft');
          setModerationStatus(c.moderation_status ?? 'pending');
          coursesApi.cohorts(courseId).then((cohortRes) => {
            if (cohortRes.success && cohortRes.data) setCohorts(cohortRes.data);
          });
        }
      })
      .finally(() => setLoading(false));
  }, [isEdit, courseId]);

  const publishCourse = async () => {
    setPublishing(true);
    const res = status === 'published'
      ? await coursesApi.unpublish(courseId)
      : await coursesApi.publish(courseId);
    setPublishing(false);
    if (res.success && res.data) {
      setStatus(res.data.status);
      setModerationStatus(res.data.moderation_status);
      toast.success(
        res.data.status === 'published'
          ? res.data.moderation_status === 'approved'
            ? t('builder.published_toast')
            : t('instructor.pending_review')
          : t('builder.unpublished_toast'),
      );
    } else {
      toast.error(t('builder.save_error'));
    }
  };

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
      enrollment_type: form.enrollment_type as Course['enrollment_type'],
      category_id: form.category_id ? Number(form.category_id) : null,
      credit_cost: Number(form.credit_cost) || 0,
      prerequisites: form.prerequisites
        .split(',')
        .map((v) => Number(v.trim()))
        .filter((v) => Number.isInteger(v) && v > 0),
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

  const addCohort = async () => {
    if (!isEdit || !cohortName.trim()) return;
    const res = await coursesApi.createCohort(courseId, { name: cohortName.trim() });
    if (res.success) {
      const list = await coursesApi.cohorts(courseId);
      if (list.success && list.data) setCohorts(list.data);
      setCohortName('');
      toast.success(t('builder.cohort_added'));
    } else {
      toast.error(t('builder.save_error'));
    }
  };

  if (loading) {
    return <div className="flex justify-center py-20" role="status" aria-busy="true"><Spinner size="lg" /></div>;
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-6">
      <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{isEdit ? t('instructor.edit_course') : t('instructor.new_course')}</h1>
          {isEdit ? (
            status === 'published' && moderationStatus === 'approved'
              ? <Chip size="sm" color="success" variant="soft">{t('instructor.published')}</Chip>
              : moderationStatus === 'pending' && status !== 'draft'
                ? <Chip size="sm" color="warning" variant="soft">{t('instructor.pending_review')}</Chip>
                : <Chip size="sm" variant="soft">{t('instructor.draft')}</Chip>
          ) : null}
        </div>
        {isEdit ? (
          <Button
            color={status === 'published' ? 'secondary' : 'primary'}
            isLoading={publishing}
            onPress={publishCourse}
          >
            {status === 'published' ? t('instructor.unpublish') : t('instructor.publish')}
          </Button>
        ) : null}
      </div>
      {isEdit && status !== 'published' ? (
        <p className="text-sm text-muted -mt-3 mb-6">{t('builder.publish_hint')}</p>
      ) : null}

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
              <SelectItem id="">{t('instructor.no_category')}</SelectItem>
              {categories.map((c) => <SelectItem key={c.id} id={String(c.id)}>{c.name}</SelectItem>)}
            </Select>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <Select label={t('instructor.enrollment_type_label')} selectedKeys={[form.enrollment_type]} onSelectionChange={(k) => setForm({ ...form, enrollment_type: (Array.from(k)[0] as string) ?? 'self_paced' })}>
              {ENROLLMENT_TYPES.map((v) => <SelectItem key={v} id={v}>{t(`instructor.enrollment_${v}`)}</SelectItem>)}
            </Select>
            <Input label={t('instructor.credit_cost_label')} type="number" value={form.credit_cost} onValueChange={(v) => setForm({ ...form, credit_cost: v })} />
            <Input label={t('instructor.prerequisites_label')} value={form.prerequisites} onValueChange={(v) => setForm({ ...form, prerequisites: v })} />
          </div>
          <div>
            <Button color="primary" isLoading={saving} onPress={saveDetails}>{t('instructor.save')}</Button>
          </div>
        </CardBody>
      </Card>

      {isEdit && (
        <>
          <CourseBuilder courseId={courseId} initialSections={sections} />
          {form.enrollment_type === 'cohort' ? (
            <Card className="mt-6">
              <CardBody className="p-5 flex flex-col gap-3">
                <h2 className="text-lg font-semibold">{t('builder.cohorts')}</h2>
                {cohorts.length > 0 ? (
                  <ul className="text-sm text-muted list-disc pl-5">
                    {cohorts.map((cohort) => <li key={cohort.id}>{cohort.name}</li>)}
                  </ul>
                ) : (
                  <p className="text-sm text-muted">{t('builder.no_cohorts')}</p>
                )}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                  <Input size="sm" label={t('builder.cohort_name')} value={cohortName} onValueChange={setCohortName} />
                  <Button className="sm:self-end" size="sm" variant="secondary" onPress={addCohort}>{t('builder.add_cohort')}</Button>
                </div>
              </CardBody>
            </Card>
          ) : null}
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
