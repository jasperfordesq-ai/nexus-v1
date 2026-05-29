// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CourseReviews — rating summary, review list, and (for enrolled learners) a
 * star-rating write form on the course detail page.
 */

import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Avatar, Button, Card, CardBody, Spinner, Textarea } from '@/components/ui';
import Star from 'lucide-react/icons/star';
import { useToast } from '@/contexts';
import { coursesApi, type CourseReview } from '@/lib/api/courses';

interface CourseReviewsProps {
  courseId: number;
  ratingAvg: number;
  ratingCount: number;
  canReview: boolean;
}

function StarRow({ value, onChange, size = 18 }: { value: number; onChange?: (v: number) => void; size?: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {[1, 2, 3, 4, 5].map((n) => {
        const filled = n <= value;
        const star = (
          <Star
            size={size}
            className={filled ? 'text-warning' : 'text-muted'}
            fill={filled ? 'currentColor' : 'none'}
            aria-hidden="true"
          />
        );
        return onChange ? (
          <button key={n} type="button" onClick={() => onChange(n)} aria-label={`${n}`} className="p-0.5">
            {star}
          </button>
        ) : (
          <span key={n}>{star}</span>
        );
      })}
    </div>
  );
}

export function CourseReviews({ courseId, ratingAvg, ratingCount, canReview }: CourseReviewsProps) {
  const { t } = useTranslation('courses');
  const toast = useToast();
  const [reviews, setReviews] = useState<CourseReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    coursesApi.reviews(courseId)
      .then((res) => setReviews(res.success && res.data ? res.data : []))
      .finally(() => setLoading(false));
  }, [courseId]);

  useEffect(load, [load]);

  const submit = async () => {
    if (!rating) {
      toast.error(t('reviews.rating_required'));
      return;
    }
    setSubmitting(true);
    const res = await coursesApi.review(courseId, rating, body.trim());
    setSubmitting(false);
    if (res.success) {
      toast.success(t('reviews.thanks'));
      setBody('');
      setRating(0);
      load();
    } else {
      toast.error(t('detail.enroll_error'));
    }
  };

  return (
    <section className="mt-8">
      <div className="flex items-center gap-3 mb-3">
        <h2 className="text-lg font-semibold">{t('reviews.title')}</h2>
        {ratingCount > 0 ? (
          <div className="flex items-center gap-2 text-sm text-muted">
            <StarRow value={Math.round(ratingAvg)} />
            <span>{Number(ratingAvg).toFixed(1)} · {t('reviews.count', { count: ratingCount })}</span>
          </div>
        ) : null}
      </div>

      {canReview ? (
        <Card className="mb-4">
          <CardBody className="p-4 flex flex-col gap-2">
            <span className="text-sm font-medium">{t('reviews.your_rating')}</span>
            <StarRow value={rating} onChange={setRating} size={24} />
            <Textarea
              aria-label={t('reviews.write')}
              placeholder={t('reviews.write')}
              value={body}
              onValueChange={setBody}
              rows={3}
            />
            <div>
              <Button color="primary" size="sm" isLoading={submitting} onPress={submit}>{t('reviews.submit')}</Button>
            </div>
          </CardBody>
        </Card>
      ) : null}

      {loading ? (
        <div className="flex justify-center py-6" role="status" aria-busy="true"><Spinner /></div>
      ) : reviews.length === 0 ? (
        <p className="text-sm text-muted">{t('reviews.empty')}</p>
      ) : (
        <div className="flex flex-col gap-3">
          {reviews.map((r) => (
            <div key={r.id} className="flex gap-3">
              <Avatar size="sm" src={r.user?.avatar_url ?? undefined} name={r.user?.name ?? '?'} />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold">{r.user?.name ?? `#${r.user_id}`}</span>
                  <StarRow value={r.rating} size={14} />
                </div>
                {r.body ? <p className="text-sm whitespace-pre-wrap">{r.body}</p> : null}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

export default CourseReviews;
