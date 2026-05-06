// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Divider,
  Input,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Textarea,
} from '@heroui/react';
import Inbox from 'lucide-react/icons/inbox';
import MessageSquare from 'lucide-react/icons/message-square';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type FeedbackStatus = 'new' | 'triaging' | 'in_progress' | 'resolved' | 'closed';
type FeedbackCategory = 'question' | 'idea' | 'issue_report' | 'sentiment';
type SentimentTag = 'positive' | 'neutral' | 'negative' | 'concerned';

interface MyFeedbackRow {
  id: number;
  category: FeedbackCategory;
  subject: string;
  body: string;
  status: FeedbackStatus;
  is_anonymous: boolean;
  is_public: boolean;
  sentiment_tag: SentimentTag | null;
  created_at: string;
  updated_at: string;
}

const STATUS_COLOR: Record<FeedbackStatus, 'default' | 'primary' | 'warning' | 'success' | 'danger'> = {
  new: 'primary',
  triaging: 'warning',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'default',
};

function fmtDate(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function MunicipalityFeedbackPage() {
  const { t } = useTranslation('municipality_feedback');
  const { showToast } = useToast();
  usePageTitle(t('page_title'));

  const [category, setCategory] = useState<FeedbackCategory>('question');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [sentimentTag, setSentimentTag] = useState<string>('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [isPublic, setIsPublic] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [items, setItems] = useState<MyFeedbackRow[]>([]);
  const [loadingList, setLoadingList] = useState(true);

  const loadMine = useCallback(async () => {
    setLoadingList(true);
    try {
      const res = await api.get<MyFeedbackRow[]>('/v2/caring-community/feedback/mine');
      if (!res.success) {
        showToast(res.error || t('submit_error'), 'error');
        return;
      }
      setItems(Array.isArray(res.data) ? res.data : []);
    } catch {
      showToast(t('submit_error'), 'error');
    } finally {
      setLoadingList(false);
    }
  }, [showToast, t]);

  useEffect(() => {
    void loadMine();
  }, [loadMine]);

  const handleSubmit = useCallback(async () => {
    if (!subject.trim() || !body.trim()) {
      showToast(t('submit_error'), 'error');
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post('/v2/caring-community/feedback', {
        category,
        subject: subject.trim(),
        body: body.trim(),
        sentiment_tag: sentimentTag || undefined,
        is_anonymous: isAnonymous,
        is_public: isPublic,
      });
      if (!res.success) {
        showToast(res.error || t('submit_error'), 'error');
        return;
      }
      showToast(t('submit_success'), 'success');
      setSubject('');
      setBody('');
      setSentimentTag('');
      setIsAnonymous(false);
      setIsPublic(false);
      await loadMine();
    } catch {
      showToast(t('submit_error'), 'error');
    } finally {
      setSubmitting(false);
    }
  }, [category, subject, body, sentimentTag, isAnonymous, isPublic, loadMine, showToast, t]);

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Card>
        <CardBody className="space-y-2">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15">
              <Inbox size={20} className="text-primary" />
            </span>
            <h1 className="text-xl font-bold sm:text-2xl">{t('intro_title')}</h1>
          </div>
          <p className="text-sm leading-relaxed text-default-500">{t('intro_body')}</p>
        </CardBody>
      </Card>

      {/* Submit form */}
      <Card>
        <CardBody className="space-y-4">
          <h2 className="text-lg font-semibold">{t('form_title')}</h2>

          <Select
            label={t('field_category')}
            selectedKeys={[category]}
            onChange={(e) => setCategory(e.target.value as FeedbackCategory)}
            variant="bordered"
            size="sm"
            isRequired
          >
            <SelectItem key="question">{t('category_question')}</SelectItem>
            <SelectItem key="idea">{t('category_idea')}</SelectItem>
            <SelectItem key="issue_report">{t('category_issue_report')}</SelectItem>
            <SelectItem key="sentiment">{t('category_sentiment')}</SelectItem>
          </Select>

          <Input
            label={t('field_subject')}
            value={subject}
            onValueChange={setSubject}
            variant="bordered"
            size="sm"
            maxLength={200}
            isRequired
          />

          <Textarea
            label={t('field_body')}
            value={body}
            onValueChange={setBody}
            variant="bordered"
            minRows={4}
            maxLength={5000}
            isRequired
          />

          <Select
            label={t('field_sentiment')}
            placeholder={t('field_sentiment')}
            selectedKeys={sentimentTag ? [sentimentTag] : []}
            onChange={(e) => setSentimentTag(e.target.value)}
            variant="bordered"
            size="sm"
          >
            <SelectItem key="positive">{t('sentiment_positive')}</SelectItem>
            <SelectItem key="neutral">{t('sentiment_neutral')}</SelectItem>
            <SelectItem key="negative">{t('sentiment_negative')}</SelectItem>
            <SelectItem key="concerned">{t('sentiment_concerned')}</SelectItem>
          </Select>

          <Divider />

          <div className="space-y-3">
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1">
                <p className="text-sm font-medium">{t('field_anonymous')}</p>
                <p className="text-xs text-default-500">{t('anonymous_help')}</p>
              </div>
              <Switch
                size="sm"
                isSelected={isAnonymous}
                onValueChange={setIsAnonymous}
                aria-label={t('field_anonymous')}
              />
            </div>
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1">
                <p className="text-sm font-medium">{t('field_public')}</p>
                <p className="text-xs text-default-500">{t('public_help')}</p>
              </div>
              <Switch
                size="sm"
                isSelected={isPublic}
                onValueChange={setIsPublic}
                aria-label={t('field_public')}
              />
            </div>
          </div>

          <div className="flex justify-end">
            <Button
              color="primary"
              startContent={<MessageSquare size={14} />}
              onPress={handleSubmit}
              isLoading={submitting}
              isDisabled={!subject.trim() || !body.trim()}
            >
              {t('submit')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* My submissions list */}
      <Card>
        <CardBody className="space-y-3">
          <h2 className="text-lg font-semibold">{t('my_submissions_title')}</h2>
          {loadingList ? (
            <div className="flex justify-center py-8">
              <Spinner size="sm" />
            </div>
          ) : items.length === 0 ? (
            <p className="py-6 text-center text-sm text-default-500">{t('my_submissions_empty')}</p>
          ) : (
            <ul className="space-y-3">
              {items.map((row) => (
                <li
                  key={row.id}
                  className="rounded-lg border border-default-200 p-3 hover:bg-default-50"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      <Chip size="sm" variant="flat">
                        {t(`category_${row.category}`)}
                      </Chip>
                      <Chip size="sm" variant="flat" color={STATUS_COLOR[row.status]}>
                        {t(`status_${row.status}`)}
                      </Chip>
                      {row.is_anonymous && (
                        <Chip size="sm" variant="flat">
                          {t('field_anonymous')}
                        </Chip>
                      )}
                    </div>
                    <span className="text-xs text-default-500">{fmtDate(row.created_at)}</span>
                  </div>
                  <p className="mt-2 text-sm font-medium">{row.subject}</p>
                  <p className="mt-1 text-xs text-default-500 line-clamp-2">{row.body}</p>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
