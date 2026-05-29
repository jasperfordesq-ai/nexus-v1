// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * InstructorDashboardPage — authored courses with publish controls.
 * Accessible to members granted the instructor role and to admins.
 */

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, Chip, Spinner } from '@/components/ui';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { coursesApi, type Course } from '@/lib/api/courses';

export default function InstructorDashboardPage() {
  const { t } = useTranslation('courses');
  usePageTitle(t('instructor.dashboard'));
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const toast = useToast();

  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    coursesApi.authored()
      .then((res) => setCourses(res.success && res.data ? res.data : []))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const togglePublish = async (course: Course) => {
    const res = course.status === 'published'
      ? await coursesApi.unpublish(course.id)
      : await coursesApi.publish(course.id);
    if (res.success) {
      toast.success(t('instructor.saved'));
      load();
    } else {
      toast.error(t('instructor.create_error'));
    }
  };

  const statusChip = (course: Course) => {
    if (course.status === 'published') return <Chip size="sm" color="success" variant="soft">{t('instructor.published')}</Chip>;
    if (course.moderation_status === 'pending' && course.status !== 'draft') return <Chip size="sm" color="warning" variant="soft">{t('instructor.pending_review')}</Chip>;
    return <Chip size="sm" variant="soft">{t('instructor.draft')}</Chip>;
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-6">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold">{t('instructor.dashboard')}</h1>
        <Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate(tenantPath('/courses/instructor/new'))}>
          {t('instructor.create_course')}
        </Button>
      </div>

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true"><Spinner size="lg" /></div>
      ) : courses.length === 0 ? (
        <div className="text-center py-16 text-muted">{t('instructor.my_courses')}</div>
      ) : (
        <div className="flex flex-col gap-3">
          {courses.map((course) => (
            <Card key={course.id}>
              <CardBody className="p-4 flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold text-sm line-clamp-1">{course.title}</h3>
                    {statusChip(course)}
                  </div>
                  <div className="text-xs text-muted mt-1">
                    {t('instructor.enrollments')}: {course.enrollment_count} · {t('instructor.completions')}: {course.completion_count}
                  </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  <Button as={Link} to={tenantPath(`/courses/instructor/${course.id}/analytics`)} size="sm" variant="tertiary">
                    {t('analytics.title')}
                  </Button>
                  <Button as={Link} to={tenantPath(`/courses/instructor/${course.id}/edit`)} size="sm" variant="tertiary">
                    {t('instructor.edit_course')}
                  </Button>
                  <Button size="sm" variant="secondary" onPress={() => togglePublish(course)}>
                    {course.status === 'published' ? t('instructor.unpublish') : t('instructor.publish')}
                  </Button>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
