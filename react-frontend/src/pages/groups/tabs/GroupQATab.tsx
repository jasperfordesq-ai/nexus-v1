// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Q&A Tab (GR1)
 * Stack Overflow-style Q&A within a group: questions, answers, voting, accept.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Spinner,
  Input,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Chip,
  useDisclosure,
} from '@heroui/react';
import {
  HelpCircle,
  ArrowUp,
  ArrowDown,
  Check,
  CheckCircle,
  MessageSquare,
  Plus,
  Search,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface QuestionAuthor {
  id: number;
  name: string;
  avatar: string | null;
}

interface Answer {
  id: number;
  question_id: number;
  body: string;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  is_accepted: boolean;
  author: QuestionAuthor;
  created_at: string;
}

interface Question {
  id: number;
  group_id: number;
  title: string;
  body: string;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  answer_count: number;
  has_accepted_answer: boolean;
  author: QuestionAuthor;
  created_at: string;
}

interface QuestionDetail extends Question {
  answers: Answer[];
}

type SortOption = 'newest' | 'most_voted' | 'unanswered';

interface GroupQATabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupQATab({ groupId, isAdmin, isMember = true }: GroupQATabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const askModal = useDisclosure();

  // Question list state
  const [questions, setQuestions] = useState<Question[]>([]);
  const [loading, setLoading] = useState(true);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<SortOption>('newest');

  // Ask question form
  const [askTitle, setAskTitle] = useState('');
  const [askBody, setAskBody] = useState('');
  const [asking, setAsking] = useState(false);

  // Expanded question state
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [expandedDetail, setExpandedDetail] = useState<QuestionDetail | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);

  // Answer form state
  const [answerBody, setAnswerBody] = useState('');
  const [submittingAnswer, setSubmittingAnswer] = useState(false);

  // Voting in-flight tracker
  const [votingIds, setVotingIds] = useState<Set<string>>(new Set());

  // ─────────────────────────────────────────────────────────────────────
  // Load questions
  // ─────────────────────────────────────────────────────────────────────

  const loadQuestions = useCallback(
    async (reset = false) => {
      try {
        if (reset) setLoading(true);

        const params = new URLSearchParams({ sort, per_page: '20' });
        if (!reset && cursor) params.set('cursor', cursor);
        if (search.trim()) params.set('q', search.trim());

        const resp = await api.get(`/v2/groups/${groupId}/questions?${params}`);
        const data = (resp.data ?? {}) as { items?: Question[]; cursor?: string | null; has_more?: boolean };

        if (reset) {
          setQuestions(data.items ?? []);
        } else {
          setQuestions((prev) => [...prev, ...(data.items ?? [])]);
        }
        setCursor(data.cursor ?? null);
        setHasMore(data.has_more ?? false);
      } catch (err) {
        logError('GroupQATab.loadQuestions', err);
      } finally {
        setLoading(false);
      }
    },
    [groupId, cursor, sort, search],
  );

  useEffect(() => {
    loadQuestions(true);
  }, [groupId, sort]); // eslint-disable-line react-hooks/exhaustive-deps

  // Debounced search
  useEffect(() => {
    const timeout = setTimeout(() => loadQuestions(true), 300);
    return () => clearTimeout(timeout);
  }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

  // ─────────────────────────────────────────────────────────────────────
  // Expand / collapse a question (load detail with answers)
  // ─────────────────────────────────────────────────────────────────────

  const toggleExpand = useCallback(
    async (questionId: number) => {
      if (expandedId === questionId) {
        setExpandedId(null);
        setExpandedDetail(null);
        setAnswerBody('');
        return;
      }

      setExpandedId(questionId);
      setExpandedDetail(null);
      setLoadingDetail(true);

      try {
        const resp = await api.get(`/v2/groups/${groupId}/questions/${questionId}`);
        setExpandedDetail(resp.data as QuestionDetail);
      } catch (err) {
        logError('GroupQATab.loadDetail', err);
        toast.error(t('qa.load_error', 'Failed to load question'));
        setExpandedId(null);
      } finally {
        setLoadingDetail(false);
      }
    },
    [groupId, expandedId, t, toast],
  );

  // ─────────────────────────────────────────────────────────────────────
  // Ask a question
  // ─────────────────────────────────────────────────────────────────────

  const handleAsk = async () => {
    if (!askTitle.trim() || !askBody.trim()) return;
    setAsking(true);

    try {
      await api.post(`/v2/groups/${groupId}/questions`, {
        title: askTitle.trim(),
        body: askBody.trim(),
      });

      toast.success(t('qa.ask_success', 'Question posted'));
      setAskTitle('');
      setAskBody('');
      askModal.onClose();
      loadQuestions(true);
    } catch (err) {
      logError('GroupQATab.ask', err);
      toast.error(t('qa.ask_error', 'Failed to post question'));
    } finally {
      setAsking(false);
    }
  };

  // ─────────────────────────────────────────────────────────────────────
  // Post an answer
  // ─────────────────────────────────────────────────────────────────────

  const handleAnswer = async () => {
    if (!answerBody.trim() || !expandedId) return;
    setSubmittingAnswer(true);

    try {
      await api.post(`/v2/groups/${groupId}/questions/${expandedId}/answers`, {
        body: answerBody.trim(),
      });

      toast.success(t('qa.answer_success', 'Answer posted'));
      setAnswerBody('');
      // Refresh expanded detail and list counts
      toggleExpand(expandedId);
      loadQuestions(true);
    } catch (err) {
      logError('GroupQATab.answer', err);
      toast.error(t('qa.answer_error', 'Failed to post answer'));
    } finally {
      setSubmittingAnswer(false);
    }
  };

  // ─────────────────────────────────────────────────────────────────────
  // Vote (question or answer)
  // ─────────────────────────────────────────────────────────────────────

  const handleVote = async (
    type: 'question' | 'answer',
    targetId: number,
    vote: 1 | -1,
  ) => {
    const key = `${type}-${targetId}`;
    if (votingIds.has(key)) return;

    setVotingIds((prev) => new Set(prev).add(key));

    try {
      await api.post(`/v2/groups/${groupId}/qa/vote`, {
        type,
        target_id: targetId,
        vote,
      });

      // Update local state for immediate feedback
      if (type === 'question') {
        setQuestions((prev) =>
          prev.map((q) =>
            q.id === targetId
              ? {
                  ...q,
                  vote_count: q.vote_count + vote - q.user_vote,
                  user_vote: q.user_vote === vote ? 0 : vote,
                }
              : q,
          ),
        );
        // Also update expanded detail if this question is expanded
        if (expandedDetail && expandedDetail.id === targetId) {
          setExpandedDetail((prev) =>
            prev
              ? {
                  ...prev,
                  vote_count: prev.vote_count + vote - prev.user_vote,
                  user_vote: prev.user_vote === vote ? (0 as 0) : vote,
                }
              : prev,
          );
        }
      } else if (expandedDetail) {
        setExpandedDetail((prev) =>
          prev
            ? {
                ...prev,
                answers: prev.answers.map((a) =>
                  a.id === targetId
                    ? {
                        ...a,
                        vote_count: a.vote_count + vote - a.user_vote,
                        user_vote: a.user_vote === vote ? (0 as 0) : vote,
                      }
                    : a,
                ),
              }
            : prev,
        );
      }
    } catch (err) {
      logError('GroupQATab.vote', err);
      toast.error(t('qa.vote_error', 'Failed to register vote'));
    } finally {
      setVotingIds((prev) => {
        const next = new Set(prev);
        next.delete(key);
        return next;
      });
    }
  };

  // ─────────────────────────────────────────────────────────────────────
  // Accept answer
  // ─────────────────────────────────────────────────────────────────────

  const handleAccept = async (answerId: number) => {
    if (!expandedDetail) return;

    try {
      await api.post(`/v2/groups/${groupId}/answers/${answerId}/accept`);

      toast.success(t('qa.accept_success', 'Answer accepted'));

      // Update local state
      setExpandedDetail((prev) =>
        prev
          ? {
              ...prev,
              has_accepted_answer: true,
              answers: prev.answers.map((a) => ({
                ...a,
                is_accepted: a.id === answerId,
              })),
            }
          : prev,
      );

      // Update list to reflect accepted status
      setQuestions((prev) =>
        prev.map((q) =>
          q.id === expandedDetail.id ? { ...q, has_accepted_answer: true } : q,
        ),
      );
    } catch (err) {
      logError('GroupQATab.accept', err);
      toast.error(t('qa.accept_error', 'Failed to accept answer'));
    }
  };

  // ─────────────────────────────────────────────────────────────────────
  // Sort tabs config
  // ─────────────────────────────────────────────────────────────────────

  const sortOptions: { key: SortOption; label: string }[] = [
    { key: 'newest', label: t('qa.sort_newest', 'Newest') },
    { key: 'most_voted', label: t('qa.sort_most_voted', 'Most Voted') },
    { key: 'unanswered', label: t('qa.sort_unanswered', 'Unanswered') },
  ];

  // ─────────────────────────────────────────────────────────────────────
  // Vote button sub-component
  // ─────────────────────────────────────────────────────────────────────

  const VoteControls = ({
    type,
    targetId,
    voteCount,
    userVote,
  }: {
    type: 'question' | 'answer';
    targetId: number;
    voteCount: number;
    userVote: 1 | -1 | 0;
  }) => {
    const isVoting = votingIds.has(`${type}-${targetId}`);

    return (
      <div className="flex flex-col items-center gap-0.5">
        <Button
          isIconOnly
          variant="light"
          size="sm"
          className={userVote === 1 ? 'text-success' : 'text-default-400'}
          onPress={() => handleVote(type, targetId, 1)}
          isDisabled={isVoting || !isMember}
          aria-label={t('qa.upvote_aria', 'Upvote')}
        >
          <ArrowUp className="w-4 h-4" />
        </Button>
        <span
          className={`text-sm font-semibold ${
            voteCount > 0
              ? 'text-success'
              : voteCount < 0
                ? 'text-danger'
                : 'text-default-500'
          }`}
          aria-label={t('qa.vote_count_aria', { count: voteCount })}
        >
          {voteCount}
        </span>
        <Button
          isIconOnly
          variant="light"
          size="sm"
          className={userVote === -1 ? 'text-danger' : 'text-default-400'}
          onPress={() => handleVote(type, targetId, -1)}
          isDisabled={isVoting || !isMember}
          aria-label={t('qa.downvote_aria', 'Downvote')}
        >
          <ArrowDown className="w-4 h-4" />
        </Button>
      </div>
    );
  };

  // ─────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────

  if (loading && questions.length === 0) {
    return (
      <div
        className="flex justify-center py-12"
        aria-label={t('qa.loading', 'Loading questions')}
        aria-busy="true"
      >
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-3">
        <Input
          placeholder={t('qa.search_placeholder', 'Search questions...')}
          value={search}
          onValueChange={setSearch}
          startContent={<Search className="w-4 h-4 text-default-400" aria-hidden="true" />}
          className="flex-1"
          size="sm"
          aria-label={t('qa.search_aria', 'Search questions')}
        />
        {isMember && (
          <Button
            color="primary"
            size="sm"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={askModal.onOpen}
          >
            {t('qa.ask_question', 'Ask Question')}
          </Button>
        )}
      </div>

      {/* Sort tabs */}
      <div className="flex flex-wrap gap-2" role="tablist" aria-label={t('qa.sort_aria', 'Sort questions')}>
        {sortOptions.map((opt) => (
          <Chip
            key={opt.key}
            variant={sort === opt.key ? 'solid' : 'bordered'}
            color="primary"
            className="cursor-pointer"
            onClick={() => setSort(opt.key)}
            role="tab"
            aria-selected={sort === opt.key}
          >
            {opt.label}
          </Chip>
        ))}
      </div>

      {/* Question list */}
      {questions.length === 0 ? (
        <EmptyState
          icon={<HelpCircle className="w-12 h-12" aria-hidden="true" />}
          title={t('qa.empty_title', 'No questions yet')}
          description={
            search
              ? t('qa.no_results', 'No questions match your search')
              : t('qa.empty_description', 'Be the first to ask a question in this group')
          }
        />
      ) : (
        <div className="space-y-2">
          {questions.map((question) => (
            <GlassCard key={question.id} className="p-0 overflow-hidden">
              {/* Question row */}
              <button
                type="button"
                className="w-full text-left p-3 hover:bg-default-100/50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                onClick={() => toggleExpand(question.id)}
                aria-expanded={expandedId === question.id}
                aria-label={t('qa.expand_aria', {
                  title: question.title,
                  defaultValue: `Toggle question: ${question.title}`,
                })}
              >
                <div className="flex items-start gap-3">
                  {/* Vote controls */}
                  <div
                    className="flex-shrink-0"
                    onClick={(e) => e.stopPropagation()}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') e.stopPropagation();
                    }}
                    role="presentation"
                  >
                    <VoteControls
                      type="question"
                      targetId={question.id}
                      voteCount={question.vote_count}
                      userVote={question.user_vote}
                    />
                  </div>

                  {/* Question content */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-theme-primary line-clamp-2">
                      {question.title}
                    </p>
                    <div className="flex flex-wrap items-center gap-2 mt-1 text-xs text-theme-subtle">
                      <span>{question.author.name}</span>
                      <span aria-hidden="true">&#183;</span>
                      <span>{formatRelativeTime(question.created_at)}</span>
                    </div>
                  </div>

                  {/* Stats badges */}
                  <div className="flex items-center gap-2 flex-shrink-0">
                    {question.has_accepted_answer && (
                      <Chip
                        size="sm"
                        color="success"
                        variant="flat"
                        startContent={<CheckCircle className="w-3 h-3" aria-hidden="true" />}
                        aria-label={t('qa.accepted_badge', 'Has accepted answer')}
                      >
                        {t('qa.accepted', 'Accepted')}
                      </Chip>
                    )}
                    <Chip
                      size="sm"
                      variant="flat"
                      startContent={<MessageSquare className="w-3 h-3" aria-hidden="true" />}
                      aria-label={t('qa.answer_count_aria', {
                        count: question.answer_count,
                        defaultValue: `${question.answer_count} answers`,
                      })}
                    >
                      {question.answer_count}
                    </Chip>
                  </div>
                </div>
              </button>

              {/* Expanded detail */}
              {expandedId === question.id && (
                <div className="border-t border-default-200 p-4 space-y-4">
                  {loadingDetail ? (
                    <div className="flex justify-center py-6" aria-busy="true">
                      <Spinner size="sm" />
                    </div>
                  ) : expandedDetail ? (
                    <>
                      {/* Question body */}
                      <SafeHtml content={expandedDetail.body} className="text-sm text-theme-secondary whitespace-pre-wrap" as="div" />

                      {/* Answers */}
                      {expandedDetail.answers.length > 0 && (
                        <div className="space-y-3">
                          <h4 className="text-sm font-semibold text-theme-primary">
                            {t('qa.answers_heading', {
                              count: expandedDetail.answers.length,
                              defaultValue: `${expandedDetail.answers.length} Answer(s)`,
                            })}
                          </h4>

                          {expandedDetail.answers.map((answer) => (
                            <div
                              key={answer.id}
                              className={`flex items-start gap-3 p-3 rounded-lg ${
                                answer.is_accepted
                                  ? 'bg-success-50 dark:bg-success-50/10 border border-success-200 dark:border-success-800'
                                  : 'bg-default-50 dark:bg-default-100/5'
                              }`}
                            >
                              {/* Answer vote controls */}
                              <VoteControls
                                type="answer"
                                targetId={answer.id}
                                voteCount={answer.vote_count}
                                userVote={answer.user_vote}
                              />

                              {/* Answer content */}
                              <div className="flex-1 min-w-0">
                                <SafeHtml content={answer.body} className="text-sm text-theme-secondary whitespace-pre-wrap" as="div" />
                                <div className="flex items-center gap-2 mt-2 text-xs text-theme-subtle">
                                  <span>{answer.author.name}</span>
                                  <span aria-hidden="true">&#183;</span>
                                  <span>{formatRelativeTime(answer.created_at)}</span>
                                  {answer.is_accepted && (
                                    <>
                                      <span aria-hidden="true">&#183;</span>
                                      <span className="text-success font-medium flex items-center gap-1">
                                        <CheckCircle className="w-3 h-3" aria-hidden="true" />
                                        {t('qa.accepted_answer', 'Accepted Answer')}
                                      </span>
                                    </>
                                  )}
                                </div>
                              </div>

                              {/* Accept button — visible to question author or admin */}
                              {!answer.is_accepted &&
                                (isAdmin ||
                                  expandedDetail.author.id ===
                                    Number(localStorage.getItem('userId'))) && (
                                  <Button
                                    isIconOnly
                                    variant="light"
                                    size="sm"
                                    color="success"
                                    onPress={() => handleAccept(answer.id)}
                                    aria-label={t('qa.accept_aria', 'Accept this answer')}
                                    className="flex-shrink-0"
                                  >
                                    <Check className="w-4 h-4" />
                                  </Button>
                                )}
                            </div>
                          ))}
                        </div>
                      )}

                      {/* Answer form */}
                      {isMember && (
                        <div className="space-y-2 pt-2 border-t border-default-200">
                          <Textarea
                            placeholder={t('qa.answer_placeholder', 'Write your answer...')}
                            value={answerBody}
                            onValueChange={setAnswerBody}
                            minRows={3}
                            size="sm"
                            aria-label={t('qa.answer_input_aria', 'Your answer')}
                          />
                          <div className="flex justify-end">
                            <Button
                              color="primary"
                              size="sm"
                              onPress={handleAnswer}
                              isLoading={submittingAnswer}
                              isDisabled={!answerBody.trim()}
                            >
                              {t('qa.post_answer', 'Post Answer')}
                            </Button>
                          </div>
                        </div>
                      )}
                    </>
                  ) : null}
                </div>
              )}
            </GlassCard>
          ))}
        </div>
      )}

      {/* Load more */}
      {hasMore && (
        <div className="flex justify-center pt-4">
          <Button variant="flat" size="sm" onPress={() => loadQuestions(false)} isLoading={loading}>
            {t('qa.load_more', 'Load More')}
          </Button>
        </div>
      )}

      {/* Ask Question modal */}
      <Modal isOpen={askModal.isOpen} onClose={askModal.onClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('qa.ask_title', 'Ask a Question')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('qa.question_title_label', 'Title')}
                placeholder={t('qa.question_title_placeholder', 'What is your question?')}
                value={askTitle}
                onValueChange={setAskTitle}
                size="sm"
                isRequired
                aria-label={t('qa.question_title_aria', 'Question title')}
              />
              <Textarea
                label={t('qa.question_body_label', 'Details')}
                placeholder={t(
                  'qa.question_body_placeholder',
                  'Provide more context or details about your question...',
                )}
                value={askBody}
                onValueChange={setAskBody}
                minRows={4}
                size="sm"
                isRequired
                aria-label={t('qa.question_body_aria', 'Question details')}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={askModal.onClose}>
              {t('qa.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleAsk}
              isLoading={asking}
              isDisabled={!askTitle.trim() || !askBody.trim()}
            >
              {t('qa.submit_question', 'Post Question')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupQATab;
