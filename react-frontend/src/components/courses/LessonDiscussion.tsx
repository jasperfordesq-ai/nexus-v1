// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LessonDiscussion — threaded per-lesson discussion panel for the course player.
 */

import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Avatar, Button, Spinner, Textarea } from '@/components/ui';
import Trash2 from 'lucide-react/icons/trash-2';
import { useAuth } from '@/contexts';
import { coursesApi, type CourseDiscussion } from '@/lib/api/courses';

interface LessonDiscussionProps {
  courseId: number;
  lessonId: number;
}

export function LessonDiscussion({ courseId, lessonId }: LessonDiscussionProps) {
  const { t } = useTranslation('courses');
  const { user } = useAuth();
  const [comments, setComments] = useState<CourseDiscussion[]>([]);
  const [loading, setLoading] = useState(true);
  const [body, setBody] = useState('');
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [posting, setPosting] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    coursesApi.listDiscussions(courseId, lessonId)
      .then((res) => setComments(res.success && res.data ? res.data : []))
      .finally(() => setLoading(false));
  }, [courseId, lessonId]);

  useEffect(load, [load]);

  const submit = async () => {
    const text = body.trim();
    if (!text) return;
    setPosting(true);
    const res = await coursesApi.postDiscussion(courseId, lessonId, text, replyTo ?? undefined);
    setPosting(false);
    if (res.success) {
      setBody('');
      setReplyTo(null);
      load();
    }
  };

  const remove = async (id: number) => {
    await coursesApi.deleteDiscussion(id);
    load();
  };

  const Comment = ({ c, isReply = false }: { c: CourseDiscussion; isReply?: boolean }) => (
    <div className={`flex gap-3 ${isReply ? 'ml-10 mt-3' : 'mt-4'}`}>
      <Avatar size="sm" src={c.user?.avatar_url ?? undefined} name={c.user?.name ?? '?'} />
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-sm font-semibold">{c.user?.name ?? `#${c.user_id}`}</span>
          {user && Number(user.id) === c.user_id ? (
            <Button isIconOnly size="sm" variant="tertiary" aria-label={t('discussion.delete')} onPress={() => remove(c.id)}>
              <Trash2 size={12} />
            </Button>
          ) : null}
        </div>
        <p className="text-sm whitespace-pre-wrap">{c.body}</p>
        {!isReply ? (
          <button type="button" className="text-xs text-accent mt-1" onClick={() => setReplyTo(c.id)}>
            {t('discussion.reply')}
          </button>
        ) : null}
        {(c.replies ?? []).map((r) => <Comment key={r.id} c={r} isReply />)}
      </div>
    </div>
  );

  return (
    <div className="mt-8">
      <h2 className="text-lg font-semibold mb-2">{t('discussion.title')}</h2>

      <div className="flex flex-col gap-2 mb-4">
        {replyTo !== null ? (
          <div className="text-xs text-muted flex items-center gap-2">
            {t('discussion.replying')}
            <button type="button" className="text-accent" onClick={() => setReplyTo(null)}>{t('discussion.cancel')}</button>
          </div>
        ) : null}
        <Textarea
          aria-label={t('discussion.placeholder')}
          placeholder={t('discussion.placeholder')}
          value={body}
          onValueChange={setBody}
          rows={3}
        />
        <div>
          <Button color="primary" size="sm" isLoading={posting} onPress={submit}>{t('discussion.post')}</Button>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-6" role="status" aria-busy="true"><Spinner /></div>
      ) : comments.length === 0 ? (
        <p className="text-sm text-muted">{t('discussion.empty')}</p>
      ) : (
        <div>{comments.map((c) => <Comment key={c.id} c={c} />)}</div>
      )}
    </div>
  );
}

export default LessonDiscussion;
