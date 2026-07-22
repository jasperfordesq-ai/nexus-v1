// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AlgorithmLabel - Shows the active sorting/ranking algorithm for a page.
 * Fetches from GET /api/v2/config/algorithms and displays a subtle chip.
 */

import { useState, useEffect } from 'react';

import Cpu from 'lucide-react/icons/cpu';
import { api } from '@/lib/api';
import { Chip, Popover, PopoverTrigger, PopoverContent, PopoverHeading } from '@/components/ui';
import { Button } from '@/components/ui/Button';

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

export function useAlgorithmInfo(area: AlgorithmArea) {
  const [info, setInfo] = useState<AlgorithmInfo | null | undefined>(
    cachedData ? cachedData[area] : undefined
  );

  useEffect(() => {
    if (info !== undefined) return;
    fetchAlgorithms().then((data) => {
      setInfo(data ? data[area] : null);
    });
  }, [area, info]);

  return info;
}

interface AlgorithmLabelProps {
  /** Which page area to show the algorithm for */
  area: AlgorithmArea;
}

export function AlgorithmLabel({ area }: AlgorithmLabelProps) {
  const info = useAlgorithmInfo(area);

  if (!info || DEFAULT_KEYS.has(info.key)) return null;

  // Tap/click-opened popover rather than a hover tooltip: the description is
  // the only place the ranking is explained, and hover does not exist on touch.
  return (
    <Popover placement="bottom">
      <PopoverTrigger>
        <Button
          variant="light"
          size="sm"
          className="h-auto min-h-0 min-w-0 p-0 rounded-full"
          aria-label={info.name}
        >
          <Chip
            variant="flat"
            size="sm"
            startContent={<Cpu className="w-3 h-3" aria-hidden="true" />}
            className="bg-[var(--surface-elevated)] text-[var(--text-subtle)] border border-[var(--border-default)] cursor-help text-[11px] h-6"
          >
            {info.name}
          </Chip>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="max-w-[18rem] px-3 py-2 text-xs text-theme-muted">
        <PopoverHeading className="sr-only">{info.name}</PopoverHeading>
        {info.description}
      </PopoverContent>
    </Popover>
  );
}
