// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PodcastEpisodePage from './PodcastEpisodePage';

const mockEpisode = vi.fn();
const mockRecordListen = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: () => ({
    isAuthenticated: false,
    user: null,
  }),
  useTenant: () => ({
    tenantPath: (path: string) => path,
  }),
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}));

vi.mock('@/lib/api/podcasts', () => ({
  podcastsApi: {
    episode: (...args: unknown[]) => mockEpisode(...args),
    recordListen: (...args: unknown[]) => mockRecordListen(...args),
    toggleReaction: vi.fn(),
    reportEpisode: vi.fn(),
  },
}));

vi.mock('@/components/podcasts/PodcastAudioPlayer', () => ({
  PodcastAudioPlayer: ({ onCompleted }: { onCompleted?: (seconds: number) => void }) => (
    <button type="button" onClick={() => onCompleted?.(42)}>
      Complete audio
    </button>
  ),
}));

function renderEpisodePage(): void {
  render(
    <MemoryRouter initialEntries={['/podcasts/community-show/public-episode']}>
      <Routes>
        <Route path="/podcasts/:showSlug/:episodeSlug" element={<PodcastEpisodePage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('PodcastEpisodePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.sessionStorage.clear();
    mockEpisode.mockResolvedValue({
      success: true,
      data: {
        id: 42,
        show_id: 7,
        author_user_id: 3,
        title: 'Public episode',
        slug: 'public-episode',
        audio_url: 'https://cdn.example.test/public.mp3',
        explicit: false,
        episode_type: 'full',
        visibility: 'public',
        status: 'published',
        moderation_status: 'approved',
        listen_count: 0,
        reaction_count: 0,
        viewer_has_reacted: false,
        show: {
          id: 7,
          owner_user_id: 3,
          title: 'Community show',
          slug: 'community-show',
          language: 'en',
          visibility: 'public',
          status: 'published',
          moderation_status: 'approved',
          episode_count: 1,
          subscriber_count: 0,
        },
      },
    });
  });

  it('records completed public listens for anonymous visitors with a stable browser session id', async () => {
    renderEpisodePage();

    expect(await screen.findByRole('heading', { name: 'Public episode' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Complete audio' }));
    fireEvent.click(screen.getByRole('button', { name: 'Complete audio' }));

    await waitFor(() => expect(mockRecordListen).toHaveBeenCalledTimes(2));

    expect(mockRecordListen).toHaveBeenNthCalledWith(1, 42, {
      listened_seconds: 42,
      completed: true,
      session_id: expect.any(String),
    });
    expect(mockRecordListen.mock.calls[1][1].session_id).toBe(mockRecordListen.mock.calls[0][1].session_id);
  });
});
