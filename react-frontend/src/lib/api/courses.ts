// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Courses module (alpha) — typed API client.
 * Thin wrapper over the shared `api` client. Response data is already unwrapped
 * by the client (response.data IS the payload — never double-unwrap).
 */

import { api } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type CourseLevel = 'beginner' | 'intermediate' | 'advanced';
export type CourseVisibility = 'public' | 'members' | 'group';
export type CourseStatus = 'draft' | 'published' | 'archived';
export type LessonContentType = 'video' | 'text' | 'pdf' | 'embed' | 'quiz';

export interface CourseCategory {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  position: number;
}

export interface CourseLesson {
  id: number;
  course_id: number;
  section_id: number | null;
  title: string;
  content_type: LessonContentType;
  body?: string | null;
  video_url?: string | null;
  attachment_url?: string | null;
  embed_url?: string | null;
  position: number;
  min_watch_percent: number;
  is_preview: boolean;
}

export interface CourseSection {
  id: number;
  course_id: number;
  title: string;
  position: number;
  lessons?: CourseLesson[];
}

export interface Course {
  id: number;
  author_user_id: number;
  category_id: number | null;
  title: string;
  slug: string;
  summary?: string | null;
  description?: string | null;
  cover_image?: string | null;
  level: CourseLevel;
  visibility: CourseVisibility;
  enrollment_type: 'self_paced' | 'cohort';
  status: CourseStatus;
  moderation_status: 'pending' | 'approved' | 'rejected' | 'flagged';
  credit_cost: string | number;
  learner_credit_reward: string | number;
  instructor_credit_reward: string | number;
  enrollment_count: number;
  completion_count: number;
  rating_avg: string | number;
  rating_count: number;
  published_at?: string | null;
  author?: { id: number; name: string; avatar_url?: string | null };
  category?: CourseCategory | null;
  sections?: CourseSection[];
  is_enrolled?: boolean;
}

export interface CourseEnrollment {
  id: number;
  course_id: number;
  user_id: number;
  status: 'active' | 'completed' | 'dropped';
  progress_percent: string | number;
  enrolled_at?: string | null;
  completed_at?: string | null;
  course?: Partial<Course>;
}

export interface LessonProgress {
  lesson_id: number;
  status: 'not_started' | 'in_progress' | 'completed';
  watch_percent: number;
  completed_at?: string | null;
}

export interface QuizQuestion {
  id: number;
  type: 'mcq' | 'multi' | 'truefalse' | 'short' | 'essay';
  prompt: string;
  options?: Array<{ id: string; label: string }> | null;
  points: number;
  position: number;
}

export interface Quiz {
  id: number;
  course_id: number;
  lesson_id: number | null;
  title: string;
  description?: string | null;
  pass_mark_percent: number;
  max_attempts: number;
  time_limit_minutes?: number | null;
  questions: QuizQuestion[];
}

export interface BrowseResult {
  items: Course[];
  total: number;
  page: number;
  per_page: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Client
// ─────────────────────────────────────────────────────────────────────────────

export const coursesApi = {
  // Public / browse
  browse: (params: Record<string, string | number | undefined> = {}) => {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== '') qs.append(k, String(v));
    });
    const suffix = qs.toString() ? `?${qs.toString()}` : '';
    return api.get<Course[]>(`/v2/courses${suffix}`);
  },
  categories: () => api.get<CourseCategory[]>('/v2/courses/categories'),
  show: (idOrSlug: string | number) => api.get<Course>(`/v2/courses/${idOrSlug}`),
  reviews: (courseId: number) => api.get(`/v2/courses/${courseId}/reviews`),

  // Learner
  enroll: (courseId: number) => api.post<CourseEnrollment>(`/v2/courses/${courseId}/enroll`, {}),
  drop: (courseId: number) => api.delete(`/v2/courses/${courseId}/enroll`),
  myCourses: () => api.get<CourseEnrollment[]>('/v2/me/courses'),
  progress: (courseId: number) =>
    api.get<{ enrollment: CourseEnrollment; lessons: LessonProgress[] }>(`/v2/courses/${courseId}/progress`),
  completeLesson: (courseId: number, lessonId: number, watchPercent = 100) =>
    api.post<{ progress_percent: number; course_completed: boolean }>(
      `/v2/courses/${courseId}/lessons/${lessonId}/complete`,
      { watch_percent: watchPercent },
    ),
  review: (courseId: number, rating: number, body: string) =>
    api.post(`/v2/courses/${courseId}/reviews`, { rating, body }),

  // Quizzes (learner)
  getQuiz: (quizId: number) => api.get<Quiz>(`/v2/courses/quizzes/${quizId}`),
  submitQuiz: (quizId: number, answers: Record<string, unknown>) =>
    api.post<{ score_percent: number; passed: boolean; needs_review: boolean; attempt_id: number }>(
      `/v2/courses/quizzes/${quizId}/attempt`,
      { answers },
    ),

  // Authoring (instructor/admin)
  authored: () => api.get<Course[]>('/v2/courses/mine'),
  create: (data: Partial<Course>) => api.post<Course>('/v2/courses', data),
  update: (id: number, data: Partial<Course>) => api.put<Course>(`/v2/courses/${id}`, data),
  publish: (id: number) => api.post<Course>(`/v2/courses/${id}/publish`, {}),
  unpublish: (id: number) => api.post<Course>(`/v2/courses/${id}/unpublish`, {}),
  remove: (id: number) => api.delete(`/v2/courses/${id}`),
  createSection: (courseId: number, data: Partial<CourseSection>) =>
    api.post<CourseSection>(`/v2/courses/${courseId}/sections`, data),
  updateSection: (courseId: number, sectionId: number, data: Partial<CourseSection>) =>
    api.put<CourseSection>(`/v2/courses/${courseId}/sections/${sectionId}`, data),
  deleteSection: (courseId: number, sectionId: number) =>
    api.delete(`/v2/courses/${courseId}/sections/${sectionId}`),
  createLesson: (courseId: number, data: Partial<CourseLesson>) =>
    api.post<CourseLesson>(`/v2/courses/${courseId}/lessons`, data),
  updateLesson: (courseId: number, lessonId: number, data: Partial<CourseLesson>) =>
    api.put<CourseLesson>(`/v2/courses/${courseId}/lessons/${lessonId}`, data),
  deleteLesson: (courseId: number, lessonId: number) =>
    api.delete(`/v2/courses/${courseId}/lessons/${lessonId}`),
};
