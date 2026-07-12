// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupWikiAuthor {
  id: number;
  name: string | null;
}

export interface GroupWikiPageSummary {
  id: number;
  title: string;
  slug: string;
  parent_id: number | null;
  sort_order: number;
  is_published: boolean;
  author: GroupWikiAuthor;
  updated_at: string;
}

export interface GroupWikiPageDetail extends GroupWikiPageSummary {
  content: string;
}

export interface GroupWikiRevision {
  id: number;
  change_summary: string | null;
  editor: GroupWikiAuthor;
  created_at: string;
}

export interface GroupWikiReadOptions {
  signal?: AbortSignal;
}

export interface CreateGroupWikiPageInput {
  title: string;
  content: string;
  parent_id?: number;
}

export interface UpdateGroupWikiPageInput {
  content: string;
  change_summary?: string;
}

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function invalidResponse(): never {
  throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
}

function isAuthor(value: unknown): value is GroupWikiAuthor {
  return isRecord(value)
    && typeof value.id === 'number'
    && (value.name === null || typeof value.name === 'string');
}

function isBooleanFlag(value: unknown): value is boolean | 0 | 1 {
  return typeof value === 'boolean' || value === 0 || value === 1;
}

function isPageSummary(value: unknown): boolean {
  return isRecord(value)
    && typeof value.id === 'number'
    && typeof value.title === 'string'
    && typeof value.slug === 'string'
    && (value.parent_id === null || typeof value.parent_id === 'number')
    && typeof value.sort_order === 'number'
    && isBooleanFlag(value.is_published)
    && isAuthor(value.author)
    && typeof value.updated_at === 'string';
}

function isPageDetail(value: unknown): boolean {
  return isPageSummary(value)
    && isRecord(value)
    && typeof value.content === 'string';
}

function isRevision(value: unknown): value is GroupWikiRevision {
  return isRecord(value)
    && typeof value.id === 'number'
    && (value.change_summary === null || typeof value.change_summary === 'string')
    && isAuthor(value.editor)
    && typeof value.created_at === 'string';
}

function readCollection(payload: unknown, key: 'pages' | 'revisions'): unknown[] {
  if (Array.isArray(payload)) return payload;
  if (isRecord(payload) && Array.isArray(payload[key])) return payload[key];
  return invalidResponse();
}

function normalizePages(payload: unknown): GroupWikiPageSummary[] {
  const pages = readCollection(payload, 'pages');
  return pages.every(isPageSummary) ? pages.map(normalizePageSummary) : invalidResponse();
}

function normalizePageSummary(payload: unknown): GroupWikiPageSummary {
  if (!isPageSummary(payload) || !isRecord(payload)) return invalidResponse();
  return {
    ...(payload as unknown as GroupWikiPageSummary),
    is_published: Boolean(payload.is_published),
  };
}

function normalizePage(payload: unknown): GroupWikiPageDetail {
  if (!isPageDetail(payload) || !isRecord(payload)) return invalidResponse();
  return {
    ...normalizePageSummary(payload),
    content: payload.content as string,
  };
}

function normalizeRevisions(payload: unknown): GroupWikiRevision[] {
  const revisions = readCollection(payload, 'revisions');
  return revisions.every(isRevision) ? revisions : invalidResponse();
}

/** List the wiki navigation tree. */
export async function listGroupWikiPages(
  groupId: number,
  options: GroupWikiReadOptions = {},
): Promise<GroupWikiPageSummary[]> {
  try {
    const response = await api.get<unknown>(`/v2/groups/${groupId}/wiki`, {
      signal: options.signal,
    });
    return normalizePages(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Read a wiki page by slug. */
export async function getGroupWikiPage(
  groupId: number,
  slug: string,
  options: GroupWikiReadOptions = {},
): Promise<GroupWikiPageDetail> {
  try {
    const response = await api.get<unknown>(`/v2/groups/${groupId}/wiki/${slug}`, {
      signal: options.signal,
    });
    return normalizePage(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Create a page and return the server-authored page contract. */
export async function createGroupWikiPage(
  groupId: number,
  input: CreateGroupWikiPageInput,
): Promise<GroupWikiPageDetail> {
  try {
    return normalizePage(unwrapGroupResponse(await api.post<unknown>(
      `/v2/groups/${groupId}/wiki`,
      input,
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Save page content and return the updated page contract. */
export async function updateGroupWikiPage(
  groupId: number,
  pageId: number,
  input: UpdateGroupWikiPageInput,
): Promise<GroupWikiPageDetail> {
  try {
    return normalizePage(unwrapGroupResponse(await api.put<unknown>(
      `/v2/groups/${groupId}/wiki/${pageId}`,
      input,
    )));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Delete a page after the consumer's explicit confirmation. */
export async function deleteGroupWikiPage(
  groupId: number,
  pageId: number,
): Promise<void> {
  try {
    unwrapGroupResponse<unknown>(await api.delete<unknown>(
      `/v2/groups/${groupId}/wiki/${pageId}`,
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Read revision history for one page. */
export async function listGroupWikiRevisions(
  groupId: number,
  pageId: number,
  options: GroupWikiReadOptions = {},
): Promise<GroupWikiRevision[]> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/wiki/${pageId}/revisions`,
      { signal: options.signal },
    );
    return normalizeRevisions(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
