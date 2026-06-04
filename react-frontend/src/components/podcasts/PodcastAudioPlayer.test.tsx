// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PodcastAudioPlayer } from './PodcastAudioPlayer';
import type { PodcastEpisode } from '@/lib/api/podcasts';

function episode(overrides: Partial<PodcastEpisode> = {}): PodcastEpisode {
  return {
    id: 1,
    show_id: 1,
    author_user_id: 1,
    title: 'First episode',
    slug: 'first-episode',
    audio_url: 'https://example.test/audio/first.mp3',
    duration_seconds: 90,
    explicit: false,
    episode_type: 'full',
    visibility: 'public',
    status: 'published',
    moderation_status: 'approved',
    listen_count: 0,
    ...overrides,
  };
}

describe('PodcastAudioPlayer', () => {
  it('resets playback state when the current episode changes', () => {
    const first = episode();
    const second = episode({
      id: 2,
      title: 'Second episode',
      slug: 'second-episode',
      audio_url: 'https://example.test/audio/second.mp3',
      duration_seconds: 30,
    });

    const { container, rerender } = render(<PodcastAudioPlayer episode={first} />);
    const audio = container.querySelector('audio');
    expect(audio).toBeInstanceOf(HTMLAudioElement);
    if (!audio) throw new Error('Expected audio element to render');

    audio.currentTime = 42;
    audio.playbackRate = 1.5;
    fireEvent.timeUpdate(audio);
    fireEvent.rateChange(audio);
    fireEvent.error(audio);

    expect(screen.getByRole('alert')).toBeInTheDocument();

    rerender(<PodcastAudioPlayer episode={second} />);

    const nextAudio = container.querySelector('audio');
    expect(nextAudio).toBeInstanceOf(HTMLAudioElement);
    if (!nextAudio) throw new Error('Expected audio element to remain rendered');

    expect(nextAudio.getAttribute('src')).toBe(second.audio_url);
    expect(nextAudio.currentTime).toBe(0);
    expect(nextAudio.playbackRate).toBe(1);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.getByText('0:30')).toBeInTheDocument();
  });
});
