// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Global podcast playback: one <audio> element owned by this provider so
// audio keeps playing across route navigation. The full episode player and
// the persistent mini-player are both views over this context.
//
// Ownership rules (load-bearing — see PodcastAudioPlayer/PodcastMiniPlayer):
//  - The element is created exactly once per provider mount and never
//    recreated on re-render; `src` changes only through load().
//  - load() for the already-active episode never reloads — navigating back
//    to the playing episode must not restart it.
//  - play() must be called from user-gesture call stacks (iOS autoplay
//    policy); resume seeks are queued behind `loadedmetadata` because iOS
//    ignores currentTime writes before metadata arrives.
//  - Listen analytics (recordListen on `ended`) live HERE, not in a page
//    component: completion can happen after the episode page unmounts.

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTenant } from './TenantContext';
import { API_BASE, tokenManager } from '@/lib/api';
import { podcastsApi, type PodcastChapter } from '@/lib/api/podcasts';
import { listenSessionId } from '@/lib/podcasts/listenSession';
import {
  clearResumePosition,
  readResumePosition,
  readSpeedPreference,
  saveResumePosition,
  saveSpeedPreference,
} from '@/lib/podcasts/resumeStore';

export interface PlayerTrack {
  episodeId: number;
  showSlug: string;
  episodeSlug: string;
  title: string;
  showTitle?: string | null;
  artworkUrl?: string | null;
  audioUrl: string;
  durationSeconds?: number | null;
  chapters?: PodcastChapter[];
  explicit?: boolean;
}

export type PlayerStatus = 'idle' | 'loading' | 'playing' | 'paused' | 'ended' | 'error';

export interface PodcastPlayerContextValue {
  track: PlayerTrack | null;
  status: PlayerStatus;
  currentTime: number;
  duration: number;
  playbackRate: number;
  volume: number;
  hasError: boolean;
  load: (track: PlayerTrack, opts?: { autoplay?: boolean }) => void;
  play: () => void;
  pause: () => void;
  toggle: () => void;
  seekTo: (seconds: number) => void;
  skip: (delta: number) => void;
  setRate: (rate: number) => void;
  setVolume: (volume: number) => void;
  retry: () => void;
  startOver: () => void;
  close: () => void;
  getResumePosition: (episodeId: number) => number | null;
}

const PodcastPlayerContext = createContext<PodcastPlayerContextValue | null>(null);

/** Positions this close to the start/end are not useful resume points. */
const RESUME_MIN_SECONDS = 10;
const RESUME_END_BUFFER_SECONDS = 10;
/** Minimum gap between throttled resume-position writes during playback. */
const RESUME_WRITE_INTERVAL_MS = 5000;
/** Avoid a request for every media timeupdate while retaining useful partial listens. */
const LISTEN_REPORT_INTERVAL_MS = 15000;

export function PodcastPlayerProvider({ children }: { children: ReactNode }) {
  const { tenant } = useTenant();
  const tenantIdRef = useRef(0);
  tenantIdRef.current = tenant?.id ?? 0;

  const audioRef = useRef<HTMLAudioElement | null>(null);
  const trackRef = useRef<PlayerTrack | null>(null);
  const completedRef = useRef(false);
  const pendingSeekRef = useRef<number | null>(null);
  const lastResumeWriteRef = useRef(0);
  const lastListenReportRef = useRef(0);
  const lastReportedSecondsRef = useRef(-1);
  const rateRef = useRef(1);

  const [track, setTrack] = useState<PlayerTrack | null>(null);
  const [status, setStatus] = useState<PlayerStatus>('idle');
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [playbackRate, setPlaybackRateState] = useState(1);
  const [volume, setVolumeState] = useState(1);

  const persistPosition = useCallback((force = false) => {
    const audio = audioRef.current;
    const current = trackRef.current;
    if (!audio || !current) return;

    const now = Date.now();
    if (!force && now - lastResumeWriteRef.current < RESUME_WRITE_INTERVAL_MS) return;
    lastResumeWriteRef.current = now;

    const pos = audio.currentTime;
    const dur = Number.isFinite(audio.duration) && audio.duration > 0
      ? audio.duration
      : (current.durationSeconds ?? 0);

    if (dur > 0 && pos > dur - RESUME_END_BUFFER_SECONDS) {
      clearResumePosition(tenantIdRef.current, current.episodeId);
      return;
    }
    if (pos < RESUME_MIN_SECONDS) return;
    saveResumePosition(tenantIdRef.current, current.episodeId, pos);
  }, []);

  const reportListen = useCallback((force = false, completed = false, secondsOverride?: number) => {
    const audio = audioRef.current;
    const current = trackRef.current;
    if (!audio || !current) return;

    const listenedSeconds = Math.max(0, Math.round(secondsOverride ?? audio.currentTime));
    const now = Date.now();
    if (!force && now - lastListenReportRef.current < LISTEN_REPORT_INTERVAL_MS) return;
    if (!completed && listenedSeconds === lastReportedSecondsRef.current) return;

    lastListenReportRef.current = now;
    lastReportedSecondsRef.current = listenedSeconds;
    void podcastsApi.recordListen(current.episodeId, {
      listened_seconds: listenedSeconds,
      completed,
      session_id: listenSessionId(current.episodeId),
    });
  }, []);

  const reportListenKeepalive = useCallback(() => {
    const audio = audioRef.current;
    const current = trackRef.current;
    if (!audio || !current || audio.currentTime <= 0) return;

    const headers = new Headers({ 'Content-Type': 'application/json', Accept: 'application/json' });
    const token = tokenManager.getAccessToken();
    const tenantId = tokenManager.getTenantId();
    if (token) headers.set('Authorization', `Bearer ${token}`);
    if (tenantId) headers.set('X-Tenant-ID', String(tenantId));

    try {
      void fetch(`${API_BASE}/v2/podcasts/episodes/${current.episodeId}/listen`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          listened_seconds: Math.max(0, Math.round(audio.currentTime)),
          completed: false,
          session_id: listenSessionId(current.episodeId),
        }),
        credentials: 'include',
        keepalive: true,
      }).catch(() => undefined);
    } catch {
      // Navigation must never be blocked by best-effort analytics delivery.
    }
  }, []);

  // Create the single audio element once per provider mount. It lives in the
  // DOM (hidden) so tests can query it; playback does not require visibility.
  useEffect(() => {
    const audio = document.createElement('audio');
    audio.preload = 'metadata';
    audio.setAttribute('data-podcast-player', 'true');
    audio.setAttribute('aria-hidden', 'true');
    audio.style.display = 'none';

    const onLoadedMetadata = () => {
      const dur = Number.isFinite(audio.duration) && audio.duration > 0
        ? audio.duration
        : (trackRef.current?.durationSeconds ?? 0);
      setDuration(dur || 0);
      // iOS ignores currentTime writes before metadata — apply queued seek now.
      if (pendingSeekRef.current !== null) {
        const upper = dur > 0 ? Math.max(0, dur - 1) : pendingSeekRef.current;
        const target = Math.min(Math.max(pendingSeekRef.current, 0), upper);
        pendingSeekRef.current = null;
        try {
          audio.currentTime = target;
        } catch {
          /* seek not possible yet — playback simply starts at 0 */
        }
        setCurrentTime(target);
      }
      audio.playbackRate = rateRef.current;
    };
    // iOS resets playbackRate on new loads — re-apply on every loadstart.
    const onLoadStart = () => {
      audio.playbackRate = rateRef.current;
    };
    const onTimeUpdate = () => {
      setCurrentTime(audio.currentTime);
      persistPosition();
      reportListen();
    };
    const onPlay = () => {
      setStatus('playing');
      reportListen(true);
    };
    const onPlaying = () => setStatus('playing');
    const onWaiting = () => setStatus('loading');
    const onPause = () => {
      setStatus((prev) => (prev === 'ended' || prev === 'error' ? prev : 'paused'));
      persistPosition(true);
      reportListen(true);
    };
    const onEnded = () => {
      setStatus('ended');
      const current = trackRef.current;
      if (!current || completedRef.current) return;
      completedRef.current = true;
      clearResumePosition(tenantIdRef.current, current.episodeId);
      const dur = Number.isFinite(audio.duration) && audio.duration > 0
        ? audio.duration
        : (current.durationSeconds ?? 0);
      // Use the media duration for a completed listen. The server independently
      // derives completion against its trusted duration and ignores this hint.
      reportListen(true, true, dur || audio.currentTime);
    };
    const onError = () => {
      // close() clears src, which fires a spurious error — only real tracks count.
      if (!trackRef.current) return;
      setStatus('error');
    };
    const onVolumeChange = () => setVolumeState(audio.volume);
    const onRateChange = () => {
      rateRef.current = audio.playbackRate;
      setPlaybackRateState(audio.playbackRate);
    };

    audio.addEventListener('loadedmetadata', onLoadedMetadata);
    audio.addEventListener('loadstart', onLoadStart);
    audio.addEventListener('timeupdate', onTimeUpdate);
    audio.addEventListener('play', onPlay);
    audio.addEventListener('playing', onPlaying);
    audio.addEventListener('waiting', onWaiting);
    audio.addEventListener('pause', onPause);
    audio.addEventListener('ended', onEnded);
    audio.addEventListener('error', onError);
    audio.addEventListener('volumechange', onVolumeChange);
    audio.addEventListener('ratechange', onRateChange);

    document.body.appendChild(audio);
    audioRef.current = audio;

    const onBeforeUnload = () => {
      persistPosition(true);
      reportListenKeepalive();
    };
    const onPageHide = () => {
      persistPosition(true);
      reportListenKeepalive();
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    window.addEventListener('pagehide', onPageHide);

    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
      window.removeEventListener('pagehide', onPageHide);
      persistPosition(true);
      reportListen(true);
      try {
        audio.pause();
        audio.removeAttribute('src');
      } catch {
        /* teardown must never throw (jsdom lacks media playback) */
      }
      audio.remove();
      audioRef.current = null;
    };
  }, [persistPosition, reportListen, reportListenKeepalive]);

  const load = useCallback((next: PlayerTrack, opts?: { autoplay?: boolean }) => {
    const audio = audioRef.current;
    if (!audio) return;

    const current = trackRef.current;
    if (current && current.episodeId === next.episodeId) {
      // Already the active track — never reload (back-navigation must not
      // restart playback). Just honour the play intent.
      if (opts?.autoplay) void audio.play().catch(() => undefined);
      return;
    }

    if (current) {
      persistPosition(true);
      reportListen(true);
    }

    completedRef.current = false;
    lastListenReportRef.current = 0;
    lastReportedSecondsRef.current = -1;
    pendingSeekRef.current = null;
    trackRef.current = next;
    setTrack(next);
    setStatus('loading');
    setCurrentTime(0);
    setDuration(next.durationSeconds && next.durationSeconds > 0 ? next.durationSeconds : 0);

    const savedRate = readSpeedPreference(tenantIdRef.current);
    rateRef.current = savedRate;
    setPlaybackRateState(savedRate);

    const resume = readResumePosition(tenantIdRef.current, next.episodeId);
    if (resume !== null && resume > RESUME_MIN_SECONDS) {
      pendingSeekRef.current = resume;
      setCurrentTime(resume);
    }

    audio.src = next.audioUrl;
    audio.playbackRate = savedRate;
    audio.load();
    if (opts?.autoplay) void audio.play().catch(() => undefined);
  }, [persistPosition, reportListen]);

  const play = useCallback(() => {
    void audioRef.current?.play().catch(() => undefined);
  }, []);

  const pause = useCallback(() => {
    audioRef.current?.pause();
  }, []);

  const toggle = useCallback(() => {
    const audio = audioRef.current;
    if (!audio) return;
    if (audio.paused) {
      void audio.play().catch(() => undefined);
    } else {
      audio.pause();
    }
  }, []);

  const seekTo = useCallback((seconds: number) => {
    const audio = audioRef.current;
    if (!audio) return;
    const dur = Number.isFinite(audio.duration) && audio.duration > 0
      ? audio.duration
      : (trackRef.current?.durationSeconds ?? 0);
    const upper = dur > 0 ? dur : seconds;
    const next = Math.min(Math.max(seconds, 0), upper);
    if (audio.readyState === 0) {
      // Metadata not loaded yet — queue the seek. Replacing any pending
      // resume seek is intentional: an explicit user seek wins.
      pendingSeekRef.current = next;
    } else {
      try {
        audio.currentTime = next;
      } catch {
        pendingSeekRef.current = next;
      }
    }
    setCurrentTime(next);
  }, []);

  const skip = useCallback((delta: number) => {
    const audio = audioRef.current;
    if (!audio) return;
    seekTo(audio.currentTime + delta);
  }, [seekTo]);

  const setRate = useCallback((rate: number) => {
    rateRef.current = rate;
    setPlaybackRateState(rate);
    if (audioRef.current) audioRef.current.playbackRate = rate;
    saveSpeedPreference(tenantIdRef.current, rate);
  }, []);

  const setVolume = useCallback((next: number) => {
    const audio = audioRef.current;
    const clamped = Math.min(Math.max(next, 0), 1);
    if (audio) audio.volume = clamped;
    setVolumeState(clamped);
  }, []);

  const retry = useCallback(() => {
    const audio = audioRef.current;
    const current = trackRef.current;
    if (!audio || !current) return;
    // Resume from where the failure happened once metadata is back.
    pendingSeekRef.current = currentTime > 1 ? currentTime : null;
    setStatus('loading');
    audio.src = current.audioUrl;
    audio.load();
    void audio.play().catch(() => undefined);
  }, [currentTime]);

  const startOver = useCallback(() => {
    const current = trackRef.current;
    if (current) clearResumePosition(tenantIdRef.current, current.episodeId);
    pendingSeekRef.current = null;
    seekTo(0);
  }, [seekTo]);

  const close = useCallback(() => {
    const audio = audioRef.current;
    persistPosition(true);
    reportListen(true);
    // Clear the track BEFORE touching src so the spurious error event fired
    // by clearing src is ignored by onError.
    trackRef.current = null;
    setTrack(null);
    setStatus('idle');
    setCurrentTime(0);
    setDuration(0);
    pendingSeekRef.current = null;
    completedRef.current = false;
    if (audio) {
      audio.pause();
      audio.removeAttribute('src');
      try {
        audio.load();
      } catch {
        /* nothing to reset */
      }
    }
  }, [persistPosition, reportListen]);

  const getResumePosition = useCallback(
    (episodeId: number) => readResumePosition(tenantIdRef.current, episodeId),
    []
  );

  const value = useMemo<PodcastPlayerContextValue>(() => ({
    track,
    status,
    currentTime,
    duration,
    playbackRate,
    volume,
    hasError: status === 'error',
    load,
    play,
    pause,
    toggle,
    seekTo,
    skip,
    setRate,
    setVolume,
    retry,
    startOver,
    close,
    getResumePosition,
  }), [track, status, currentTime, duration, playbackRate, volume, load, play, pause, toggle, seekTo, skip, setRate, setVolume, retry, startOver, close, getResumePosition]);

  return <PodcastPlayerContext.Provider value={value}>{children}</PodcastPlayerContext.Provider>;
}

export function usePodcastPlayer(): PodcastPlayerContextValue {
  const context = useContext(PodcastPlayerContext);
  if (!context) {
    throw new Error('usePodcastPlayer must be used within a PodcastPlayerProvider');
  }
  return context;
}

/** Null-safe variant for components that may render outside the provider. */
export function usePodcastPlayerOptional(): PodcastPlayerContextValue | null {
  return useContext(PodcastPlayerContext);
}
