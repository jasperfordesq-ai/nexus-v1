// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { GROUP_API_MESSAGE_KEYS } from './core';
import {
  createGroupWikiPage,
  deleteGroupWikiPage,
  getGroupWikiPage,
  listGroupWikiPages,
  listGroupWikiRevisions,
  updateGroupWikiPage,
} from './wiki';

const { mockDelete, mockGet, mockPost, mockPut } = vi.hoisted(() => ({
  mockDelete: vi.fn(),
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: { delete: mockDelete, get: mockGet, post: mockPost, put: mockPut },
}));

const makePage = (overrides: Record<string, unknown> = {}) => ({
  id: 9,
  title: 'Getting Started',
  slug: 'getting-started',
  parent_id: null,
  sort_order: 0,
  is_published: true,
  author: { id: 4, name: 'Alex' },
  updated_at: '2026-07-11T10:00:00Z',
  ...overrides,
});

const makeDetail = (overrides: Record<string, unknown> = {}) => ({
  ...makePage(),
  content: 'Welcome to the wiki.',
  ...overrides,
});

const makeRevision = (overrides: Record<string, unknown> = {}) => ({
  id: 20,
  change_summary: 'Initial version',
  editor: { id: 4, name: 'Alex' },
  created_at: '2026-07-11T10:00:00Z',
  ...overrides,
});

describe('group wiki contract', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('lists pages from direct and legacy envelopes and forwards cancellation', async () => {
    const controller = new AbortController();
    mockGet
      .mockResolvedValueOnce({ success: true, data: [makePage()] })
      .mockResolvedValueOnce({ success: true, data: { pages: [makePage({ id: 10 })] } });

    await expect(listGroupWikiPages(2, { signal: controller.signal }))
      .resolves.toEqual([expect.objectContaining({ id: 9 })]);
    expect(mockGet).toHaveBeenNthCalledWith(1, '/v2/groups/2/wiki', {
      signal: controller.signal,
    });
    await expect(listGroupWikiPages(2)).resolves.toEqual([
      expect.objectContaining({ id: 10 }),
    ]);
  });

  it('reads page detail and revision history through typed endpoints', async () => {
    const controller = new AbortController();
    mockGet
      .mockResolvedValueOnce({ success: true, data: makeDetail() })
      .mockResolvedValueOnce({ success: true, data: { revisions: [makeRevision()] } });

    await expect(getGroupWikiPage(2, 'getting-started', { signal: controller.signal }))
      .resolves.toMatchObject({ id: 9, content: 'Welcome to the wiki.' });
    await expect(listGroupWikiRevisions(2, 9, { signal: controller.signal }))
      .resolves.toEqual([expect.objectContaining({ id: 20 })]);
    expect(mockGet).toHaveBeenNthCalledWith(1, '/v2/groups/2/wiki/getting-started', {
      signal: controller.signal,
    });
    expect(mockGet).toHaveBeenNthCalledWith(2, '/v2/groups/2/wiki/9/revisions', {
      signal: controller.signal,
    });
  });

  it('normalizes database integer publication flags', async () => {
    mockGet.mockResolvedValue({ success: true, data: [makePage({ is_published: 0 })] });

    await expect(listGroupWikiPages(2)).resolves.toEqual([
      expect.objectContaining({ is_published: false }),
    ]);
  });

  it('routes create, update, and delete operations', async () => {
    mockPost.mockResolvedValue({ success: true, data: makeDetail() });
    mockPut.mockResolvedValue({ success: true, data: makeDetail({ content: 'Updated' }) });
    mockDelete.mockResolvedValue({ success: true, data: { message: 'deleted' } });

    await expect(createGroupWikiPage(2, {
      title: 'Getting Started', content: 'Welcome', parent_id: 4,
    })).resolves.toMatchObject({ id: 9 });
    await expect(updateGroupWikiPage(2, 9, {
      content: 'Updated', change_summary: 'Clarified',
    })).resolves.toMatchObject({ content: 'Updated' });
    await expect(deleteGroupWikiPage(2, 9)).resolves.toBeUndefined();

    expect(mockPost).toHaveBeenCalledWith('/v2/groups/2/wiki', {
      title: 'Getting Started', content: 'Welcome', parent_id: 4,
    });
    expect(mockPut).toHaveBeenCalledWith('/v2/groups/2/wiki/9', {
      content: 'Updated', change_summary: 'Clarified',
    });
    expect(mockDelete).toHaveBeenCalledWith('/v2/groups/2/wiki/9');
  });

  it.each([
    ['create', () => createGroupWikiPage(2, { title: 'A', content: 'B' }), () => mockPost],
    ['update', () => updateGroupWikiPage(2, 9, { content: 'B' }), () => mockPut],
    ['delete', () => deleteGroupWikiPage(2, 9), () => mockDelete],
  ] as const)('turns resolved success:false %s responses into errors', async (_name, action, getMock) => {
    getMock().mockResolvedValue({ success: false, code: 'HTTP_403', error: 'Forbidden' });

    await expect(action()).rejects.toMatchObject({
      code: 'FORBIDDEN',
      status: 403,
      messageKey: GROUP_API_MESSAGE_KEYS.forbidden,
    });
  });

  it.each([
    [{ unexpected: [] }, () => listGroupWikiPages(2)],
    [{ ...makePage(), content: 42 }, () => getGroupWikiPage(2, 'getting-started')],
    [{ revisions: [{}] }, () => listGroupWikiRevisions(2, 9)],
  ] as const)('rejects malformed successful reads', async (payload, action) => {
    mockGet.mockResolvedValue({ success: true, data: payload });

    await expect(action()).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
    });
  });

  it.each([
    [() => createGroupWikiPage(2, { title: 'Page', content: 'Body' }), () => mockPost],
    [() => updateGroupWikiPage(2, 9, { content: 'Body' }), () => mockPut],
  ] as const)('rejects malformed successful write payloads', async (action, getMock) => {
    getMock().mockResolvedValue({ success: true, data: {} });

    await expect(action()).rejects.toMatchObject({
      code: 'INVALID_RESPONSE',
      messageKey: GROUP_API_MESSAGE_KEYS.invalidResponse,
    });
  });

  it.each([
    [new TypeError('Failed to fetch'), 'NETWORK_ERROR', GROUP_API_MESSAGE_KEYS.network],
    [Object.assign(new Error('aborted'), { name: 'AbortError' }), 'CANCELLED', GROUP_API_MESSAGE_KEYS.cancelled],
  ] as const)('normalizes transport and cancellation failures', async (failure, code, messageKey) => {
    mockGet.mockRejectedValue(failure);

    await expect(listGroupWikiPages(2)).rejects.toMatchObject({ code, messageKey });
  });
});
