// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AlgorithmLabel - Shows the active sorting/ranking algorithm for a page.
 * Fetches from GET /api/v2/config/algorithms and displays a subtle chip.
 */

import { useState, useEffect } from 'react';
import { Tooltip, Chip } from '@heroui/react';
import { Cpu } from 'lucide-react';
import { api } from '@/lib/api';

interface AlgorithmInfo {
  name: string;
  key: string;
  description: string;
}

interface AlgorithmsResponse {
  feed: AlgorithmInfo;
  listings: AlgorithmInfo;
  members: AlgorithmInfo;
  matching: AlgorithmInfo;
}

type AlgorithmArea = 'feed' | 'listings' | 'members' | 'matching';

// Default/fallback keys — not worth surfacing to users, only show smart algorithms
const DEFAULT_KEYS = new Set(['chronological', 'newest', 'alphabetical', 'disabled']);

// Module-level cache so we don't re-fetch on every page navigation
let cachedData: AlgorithmsResponse | null = null;
let fetchPromise: Promise<AlgorithmsResponse | null> | null = null;

function fetchAlgorithms(): Promise<AlgorithmsResponse | null> {
  if (cachedData) return Promise.resolve(cachedData);
  if (fetchPromise) return fetchPromise;

  fetchPromise = api
    .get<AlgorithmsResponse>('/v2/config/algorithms')
    .then((res) => {
      if (res.success && res.data) {
        cachedData = res.data as AlgorithmsResponse;
        return cachedData;
      }
      return null;
    })
    .catch(() => null)
    .finally(() => {
      fetchPromise = null;
    });

  return fetchPromise;
}

interface AlgorithmLabelProps {
  /** Which page area to show the algorithm for */
  area: AlgorithmArea;
}

export function AlgorithmLabel({ area }: AlgorithmLabelProps) {
  const [info, setInfo] = useState<AlgorithmInfo | null>(
    cachedData ? cachedData[area] : null
  );

  useEffect(() => {
    if (info) return;
    fetchAlgorithms().then((data) => {
      if (data) setInfo(data[area]);
    });
  }, [area, info]);

  if (!info || DEFAULT_KEYS.has(info.key)) return null;

  return (
    <Tooltip content={info.description} placement="bottom" delay={300}>
      <Chip
        variant="flat"
        size="sm"
        startContent={<Cpu className="w-3 h-3" />}
        className="bg-[var(--surface-elevated)] text-[var(--text-subtle)] border border-[var(--border-default)] cursor-help text-[11px] h-6"
      >
        {info.name}
      </Chip>
    </Tooltip>
  );
}
