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
  PodcastAudioPlayer: ({ episode, showSlug }: { episode: { id: number }; showSlug?: string }) => (
    <div data-testid="audio-player" data-episode-id={episode.id} data-show-slug={showSlug} />
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

  // Listen-completion analytics are owned by PodcastPlayerContext now (they
  // must survive this page unmounting) — see PodcastPlayerContext.test.tsx.
  it('renders the player bound to the loaded episode and route show slug', async () => {
    renderEpisodePage();

    expect(await screen.findByRole('heading', { name: 'Public episode' })).toBeInTheDocument();
    const player = screen.getByTestId('audio-player');
    expect(player.getAttribute('data-episode-id')).toBe('42');
    expect(player.getAttribute('data-show-slug')).toBe('community-show');
    expect(mockRecordListen).not.toHaveBeenCalled();
  });
});
