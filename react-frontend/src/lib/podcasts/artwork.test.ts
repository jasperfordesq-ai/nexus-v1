// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { safePodcastArtworkUrl } from './artwork';

describe('safePodcastArtworkUrl', () => {
  it('rejects creator-controlled external artwork', () => {
    expect(safePodcastArtworkUrl('https://tracker.example/cover.jpg')).toBeNull();
  });

  it.each(['/uploads/podcasts/cover.jpg', '/storage/podcasts/cover.jpg'])(
    'allows platform-hosted artwork at %s',
    (path) => {
      const result = safePodcastArtworkUrl(path);
      expect(result).not.toBeNull();
      expect(new URL(result!).pathname).toBe(path);
    },
  );
});
