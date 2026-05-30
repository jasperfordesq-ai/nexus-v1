// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CoursesPage — public/member browse + search for the Courses module (alpha).
 */

import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { Input, Select, SelectItem, Spinner, AlphaBadge, Button } from '@/components/ui';
import Search from 'lucide-react/icons/search';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant } from '@/contexts';
import { coursesApi, type Course, type CourseCategory } from '@/lib/api/courses';
import { CourseCard } from '@/components/courses/CourseCard';

export default function CoursesPage() {
  const { t } = useTranslation('courses');
  usePageTitle(t('title'));
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  const [courses, setCourses] = useState<Course[]>([]);
  const [categories, setCategories] = useState<CourseCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState<string>('');
  const [level, setLevel] = useState<string>('');

  useEffect(() => {
    coursesApi.categories().then((res) => {
      if (res.success && res.data) setCategories(res.data);
    });
  }, []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    coursesApi
      .browse({
        q: search || undefined,
        category_id: categoryId || undefined,
        level: level || undefined,
      })
      .then((res) => {
        if (cancelled) return;
        setCourses(res.success && res.data ? res.data : []);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [search, categoryId, level]);

  const levelOptions = useMemo(
    () => [
      { value: '', label: t('browse.all_levels') },
      { value: 'beginner', label: t('level.beginner') },
      { value: 'intermediate', label: t('level.intermediate') },
      { value: 'advanced', label: t('level.advanced') },
    ],
    [t],
  );

  return (
    <div className="max-w-7xl mx-auto px-4 py-6">
      <div className="flex items-center justify-between gap-3 mb-1">
        <div className="flex items-center gap-2">
          <h1 className="text-2xl font-bold">{t('title')}</h1>
          <AlphaBadge />
        </div>
        {isAuthenticated && (
          <div className="flex items-center gap-2">
            <Button as={Link} to={tenantPath('/courses/my-learning')} variant="tertiary" size="sm">
              {t('my_learning.title')}
            </Button>
            <Button as={Link} to={tenantPath('/courses/instructor')} variant="tertiary" size="sm">
              {t('instructor.my_courses')}
            </Button>
            <Button as={Link} to={tenantPath('/courses/instructor/new')} color="primary" size="sm" startContent={<Plus size={16} />}>
              {t('instructor.create_course')}
            </Button>
          </div>
        )}
      </div>
      <p className="text-sm text-muted mb-6">{t('subtitle')}</p>

      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <Input
          size="sm"
          type="text"
          placeholder={t('browse.search_placeholder')}
          aria-label={t('browse.search_placeholder')}
          startContent={<Search size={16} className="text-muted" />}
          value={search}
          onValueChange={setSearch}
          isClearable
          onClear={() => setSearch('')}
          className="sm:max-w-xs"
        />
        <Select
          size="sm"
          aria-label={t('browse.all_categories')}
          selectedKeys={[categoryId]}
          onSelectionChange={(keys) => setCategoryId((Array.from(keys)[0] as string) ?? '')}
        >
          <SelectItem id="">{t('browse.all_categories')}</SelectItem>
          {categories.map((c) => (
            <SelectItem key={c.id} id={String(c.id)}>{c.name}</SelectItem>
          ))}
        </Select>
        <Select
          size="sm"
          aria-label={t('browse.all_levels')}
          selectedKeys={[level]}
          onSelectionChange={(keys) => setLevel((Array.from(keys)[0] as string) ?? '')}
        >
          {levelOptions.map((o) => (
            <SelectItem key={o.value} id={o.value}>{o.label}</SelectItem>
          ))}
        </Select>
      </div>

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true">
          <Spinner size="lg" />
        </div>
      ) : courses.length === 0 ? (
        <div className="text-center py-16 text-muted">
          <p className="text-lg">{t('browse.empty')}</p>
          <p className="text-sm mt-1">{t('browse.empty_hint')}</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {courses.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
      )}
    </div>
  );
}
