// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Persistent playback state for the podcast player: per-episode resume
// positions and the listener's preferred playback speed, keyed by tenant.
// All writes go through safeStorage so a full/blocked localStorage can
// never crash playback.

import { safeLocalStorageGetJSON, safeLocalStorageRemove, safeLocalStorageSetJSON } from '@/lib/safeStorage';

interface ResumeEntry {
  pos: number;
  updatedAt: number;
}

type ResumeMap = Record<string, ResumeEntry>;

const MAX_RESUME_ENTRIES = 50;

function resumeKey(tenantId: number): string {
  return `nexus:podcasts:resume:${tenantId}`;
}

function speedKey(tenantId: number): string {
  return `nexus:podcasts:speed:${tenantId}`;
}

export function readResumePosition(tenantId: number, episodeId: number): number | null {
  const map = safeLocalStorageGetJSON<ResumeMap>(resumeKey(tenantId), {});
  const entry = map[String(episodeId)];
  return entry && Number.isFinite(entry.pos) && entry.pos > 0 ? entry.pos : null;
}

export function saveResumePosition(tenantId: number, episodeId: number, pos: number): void {
  if (!Number.isFinite(pos) || pos <= 0) return;
  const map = safeLocalStorageGetJSON<ResumeMap>(resumeKey(tenantId), {});
  map[String(episodeId)] = { pos: Math.floor(pos), updatedAt: Date.now() };

  // Keep only the most recently updated entries so the map can't grow forever.
  const keys = Object.keys(map);
  if (keys.length > MAX_RESUME_ENTRIES) {
    keys
      .sort((a, b) => (map[a]?.updatedAt ?? 0) - (map[b]?.updatedAt ?? 0))
      .slice(0, keys.length - MAX_RESUME_ENTRIES)
      .forEach((key) => delete map[key]);
  }

  safeLocalStorageSetJSON(resumeKey(tenantId), map);
}

export function clearResumePosition(tenantId: number, episodeId: number): void {
  const map = safeLocalStorageGetJSON<ResumeMap>(resumeKey(tenantId), {});
  if (!(String(episodeId) in map)) return;
  delete map[String(episodeId)];
  if (Object.keys(map).length === 0) {
    safeLocalStorageRemove(resumeKey(tenantId));
  } else {
    safeLocalStorageSetJSON(resumeKey(tenantId), map);
  }
}

export function readSpeedPreference(tenantId: number): number {
  const rate = safeLocalStorageGetJSON<number>(speedKey(tenantId), 1);
  return Number.isFinite(rate) && rate >= 0.5 && rate <= 3 ? rate : 1;
}

export function saveSpeedPreference(tenantId: number, rate: number): void {
  if (!Number.isFinite(rate) || rate < 0.5 || rate > 3) return;
  safeLocalStorageSetJSON(speedKey(tenantId), rate);
}
