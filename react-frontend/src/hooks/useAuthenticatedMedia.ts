// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

/** Resolve protected API media to a short-lived in-memory object URL. */
export function useAuthenticatedMedia(url?: string): string | undefined {
  const [resolved, setResolved] = useState<string>();

  useEffect(() => {
    let objectUrl: string | undefined;
    let active = true;
    if (!url) {
      setResolved(undefined);
      return () => undefined;
    }
    if (!url.startsWith('/api/')) {
      setResolved(url);
      return () => undefined;
    }

    void api.download(url.slice(4)).then((blob) => {
      if (!active) return;
      objectUrl = URL.createObjectURL(blob);
      setResolved(objectUrl);
    }).catch(() => {
      if (active) setResolved(undefined);
    });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [url]);

  return resolved;
}
