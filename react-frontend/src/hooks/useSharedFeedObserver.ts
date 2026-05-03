// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useSharedFeedObserver — single IntersectionObserver shared across all feed cards.
 *
 * Each feed card previously created its own IntersectionObserver (one for view
 * tracking, one for impression tracking). For a 50-card feed that's 100 observers.
 * This module exposes a process-wide singleton observer per (rootMargin, threshold)
 * combination — cards register a node + callback, the observer fans events out.
 *
 * The hook mirrors the IntersectionObserver API surface area cards need:
 *   const ref = useSharedFeedObserver(callback, { threshold: 0.5 });
 *   // attach `ref` to the element you want to observe
 *
 * The callback fires every time the entry's intersecting state flips, exactly
 * like a per-element IntersectionObserver — but the underlying observer is
 * shared across every consumer using the same (rootMargin, threshold) pair.
 */
import { useCallback, useEffect, useRef } from 'react';

type EntryCallback = (entry: IntersectionObserverEntry) => void;

interface SharedObserver {
  observer: IntersectionObserver;
  callbacks: WeakMap<Element, EntryCallback>;
  /** Track refcount to GC observers when no consumers remain. */
  refs: number;
}

const observers = new Map<string, SharedObserver>();

function keyFor(rootMargin: string, threshold: number | number[]): string {
  return `${rootMargin}|${Array.isArray(threshold) ? threshold.join(',') : threshold}`;
}

function getOrCreate(rootMargin: string, threshold: number | number[]): SharedObserver {
  const key = keyFor(rootMargin, threshold);
  const existing = observers.get(key);
  if (existing) return existing;

  const callbacks = new WeakMap<Element, EntryCallback>();
  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        const cb = callbacks.get(entry.target);
        if (cb) cb(entry);
      }
    },
    { rootMargin, threshold }
  );
  const shared: SharedObserver = { observer, callbacks, refs: 0 };
  observers.set(key, shared);
  return shared;
}

interface UseSharedFeedObserverOptions {
  rootMargin?: string;
  threshold?: number | number[];
  /** When false, the hook skips observing (e.g. consent gate). */
  enabled?: boolean;
}

/**
 * Subscribe to a shared IntersectionObserver. Returns a callback ref —
 * attach it to the element you want to observe.
 */
export function useSharedFeedObserver(
  onEntry: EntryCallback,
  options: UseSharedFeedObserverOptions = {}
): (node: Element | null) => void {
  const { rootMargin = '0px', threshold = 0.5, enabled = true } = options;

  // Stable ref so we don't unsubscribe + resubscribe on every render
  const cbRef = useRef(onEntry);
  cbRef.current = onEntry;

  const observedRef = useRef<Element | null>(null);
  const sharedRef = useRef<SharedObserver | null>(null);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      const node = observedRef.current;
      const shared = sharedRef.current;
      if (node && shared) {
        shared.observer.unobserve(node);
        shared.callbacks.delete(node);
        shared.refs = Math.max(0, shared.refs - 1);
      }
    };
  }, []);

  return useCallback(
    (node: Element | null) => {
      // Detach previous node, if any
      const prevNode = observedRef.current;
      const prevShared = sharedRef.current;
      if (prevNode && prevShared) {
        prevShared.observer.unobserve(prevNode);
        prevShared.callbacks.delete(prevNode);
        prevShared.refs = Math.max(0, prevShared.refs - 1);
      }

      observedRef.current = node;
      sharedRef.current = null;

      if (!node || !enabled) return;

      const shared = getOrCreate(rootMargin, threshold);
      shared.callbacks.set(node, (entry) => cbRef.current(entry));
      shared.observer.observe(node);
      shared.refs += 1;
      sharedRef.current = shared;
    },
    [rootMargin, threshold, enabled]
  );
}
