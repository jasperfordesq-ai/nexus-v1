// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupQuestionAuthor {
  id: number;
  name: string;
  avatar: string | null;
}

export interface GroupAnswer {
  id: number;
  question_id: number;
  body: string;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  is_accepted: boolean;
  author: GroupQuestionAuthor;
  created_at: string;
}

export interface GroupQuestion {
  id: number;
  group_id: number;
  title: string;
  body: string;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  answer_count: number;
  has_accepted_answer: boolean;
  author: GroupQuestionAuthor;
  created_at: string;
}

export interface GroupQuestionDetail extends GroupQuestion {
  answers: GroupAnswer[];
}

export type GroupQuestionSort = 'newest' | 'most_voted' | 'unanswered';

export interface GroupQuestionPage {
  items: GroupQuestion[];
  cursor: string | null;
  has_more: boolean;
}

export interface ListGroupQuestionsOptions {
  sort: GroupQuestionSort;
  cursor?: string | null;
  query?: string;
  perPage?: number;
  signal?: AbortSignal;
}

export interface ReadGroupQuestionOptions {
  signal?: AbortSignal;
}

export interface AskGroupQuestionInput {
  title: string;
  body: string;
}

export interface CreatedGroupQuestion {
  id: number;
  title: string;
}

export interface PostGroupAnswerInput {
  body: string;
}

export interface CreatedGroupAnswer {
  id: number;
  question_id: number;
}

export interface VoteOnGroupQAInput {
  type: 'question' | 'answer';
  target_id: number;
  vote: 1 | -1;
}

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function invalidResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function isBooleanFlag(value: unknown): value is boolean | 0 | 1 {
  return typeof value === 'boolean' || value === 0 || value === 1;
}

function isQuestion(value: unknown): boolean {
  if (!isRecord(value) || !isRecord(value.author)) return false;
  return typeof value.id === 'number'
    && typeof value.group_id === 'number'
    && typeof value.title === 'string'
    && typeof value.body === 'string'
    && typeof value.vote_count === 'number'
    && (value.user_vote === -1 || value.user_vote === 0 || value.user_vote === 1)
    && typeof value.answer_count === 'number'
    && isBooleanFlag(value.has_accepted_answer)
    && typeof value.author.id === 'number'
    && typeof value.author.name === 'string'
    && typeof value.created_at === 'string';
}

function isAnswer(value: unknown): boolean {
  if (!isRecord(value) || !isRecord(value.author)) return false;
  return typeof value.id === 'number'
    && typeof value.question_id === 'number'
    && typeof value.body === 'string'
    && typeof value.vote_count === 'number'
    && (value.user_vote === -1 || value.user_vote === 0 || value.user_vote === 1)
    && isBooleanFlag(value.is_accepted)
    && typeof value.author.id === 'number'
    && typeof value.author.name === 'string'
    && typeof value.created_at === 'string';
}

function normalizeQuestionPage(payload: unknown): GroupQuestionPage {
  if (!isRecord(payload) || !Array.isArray(payload.items) || !payload.items.every(isQuestion)) {
    return invalidResponse();
  }
  if (payload.cursor !== undefined && payload.cursor !== null && typeof payload.cursor !== 'string') {
    return invalidResponse();
  }
  if (payload.has_more !== undefined && typeof payload.has_more !== 'boolean') {
    return invalidResponse();
  }

  return {
    items: payload.items.map(normalizeQuestion),
    cursor: typeof payload.cursor === 'string' ? payload.cursor : null,
    has_more: payload.has_more === true,
  };
}

function normalizeQuestion(payload: unknown): GroupQuestion {
  if (!isQuestion(payload) || !isRecord(payload)) return invalidResponse();
  return {
    ...(payload as unknown as GroupQuestion),
    has_accepted_answer: Boolean(payload.has_accepted_answer),
  };
}

function normalizeQuestionDetail(payload: unknown): GroupQuestionDetail {
  if (!isRecord(payload) || !Array.isArray(payload.answers)) {
    return invalidResponse();
  }
  if (!payload.answers.every(isAnswer)) return invalidResponse();
  return {
    ...normalizeQuestion(payload),
    answers: payload.answers.map(normalizeAnswer),
  };
}

function normalizeAnswer(payload: unknown): GroupAnswer {
  if (!isAnswer(payload) || !isRecord(payload)) return invalidResponse();
  return {
    ...(payload as unknown as GroupAnswer),
    is_accepted: Boolean(payload.is_accepted),
  };
}

function normalizeCreatedQuestion(payload: unknown): CreatedGroupQuestion {
  if (!isRecord(payload) || typeof payload.id !== 'number' || typeof payload.title !== 'string') {
    return invalidResponse();
  }
  return { id: payload.id, title: payload.title };
}

function normalizeCreatedAnswer(payload: unknown): CreatedGroupAnswer {
  if (!isRecord(payload) || typeof payload.id !== 'number' || typeof payload.question_id !== 'number') {
    return invalidResponse();
  }
  return { id: payload.id, question_id: payload.question_id };
}

/** List, search, sort, or continue the cursor-paginated group Q&A board. */
export async function listGroupQuestions(
  groupId: number,
  options: ListGroupQuestionsOptions,
): Promise<GroupQuestionPage> {
  const params = new URLSearchParams({
    sort: options.sort,
    per_page: String(options.perPage ?? 20),
  });
  if (options.cursor) params.set('cursor', options.cursor);
  if (options.query?.trim()) params.set('q', options.query.trim());

  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/questions?${params.toString()}`,
      { signal: options.signal },
    );
    return normalizeQuestionPage(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Read one question together with every visible answer. */
export async function getGroupQuestion(
  groupId: number,
  questionId: number,
  options: ReadGroupQuestionOptions = {},
): Promise<GroupQuestionDetail> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/questions/${questionId}`,
      { signal: options.signal },
    );
    return normalizeQuestionDetail(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Ask a question and require a valid created-question payload. */
export async function askGroupQuestion(
  groupId: number,
  input: AskGroupQuestionInput,
): Promise<CreatedGroupQuestion> {
  try {
    return normalizeCreatedQuestion(unwrapGroupResponse(await api.post<unknown>(
      `/v2/groups/${groupId}/questions`,
      input,
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Post an answer and require a valid created-answer payload. */
export async function postGroupAnswer(
  groupId: number,
  questionId: number,
  input: PostGroupAnswerInput,
): Promise<CreatedGroupAnswer> {
  try {
    return normalizeCreatedAnswer(unwrapGroupResponse(await api.post<unknown>(
      `/v2/groups/${groupId}/questions/${questionId}/answers`,
      input,
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Record or toggle a question/answer vote. */
export async function voteOnGroupQA(
  groupId: number,
  input: VoteOnGroupQAInput,
): Promise<void> {
  try {
    unwrapGroupResponse<unknown>(await api.post<unknown>(
      `/v2/groups/${groupId}/qa/vote`,
      input,
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Mark an answer as accepted. */
export async function acceptGroupAnswer(
  groupId: number,
  answerId: number,
): Promise<void> {
  try {
    unwrapGroupResponse<unknown>(await api.post<unknown>(
      `/v2/groups/${groupId}/answers/${answerId}/accept`,
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
