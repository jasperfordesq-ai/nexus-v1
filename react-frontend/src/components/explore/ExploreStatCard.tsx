// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef, useState } from 'react';
import { type LucideIcon } from 'lucide-react';

interface ExploreStatCardProps {
  icon: LucideIcon;
  label: string;
  value: number;
  suffix?: string;
}

/**
 * Animated counter card for community stats.
 * Counts up when scrolled into view using IntersectionObserver.
 */
export function ExploreStatCard({ icon: Icon, label, value, suffix = '' }: ExploreStatCardProps) {
  const [displayValue, setDisplayValue] = useState(0);
  const [hasAnimated, setHasAnimated] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    const element = ref.current;
    if (!element) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting && !hasAnimated) {
          setHasAnimated(true);
          animateCount(value);
        }
      },
      { threshold: 0.3 }
    );

    observer.observe(element);
    return () => {
      observer.disconnect();
      if (timerRef.current) {
        clearInterval(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [value, hasAnimated]);

  function animateCount(target: number) {
    const duration = 1200;
    const steps = 40;
    const stepTime = duration / steps;
    let current = 0;
    const increment = target / steps;

    timerRef.current = setInterval(() => {
      current += increment;
      if (current >= target) {
        setDisplayValue(target);
        if (timerRef.current) {
          clearInterval(timerRef.current);
          timerRef.current = null;
        }
      } else {
        setDisplayValue(Math.floor(current));
      }
    }, stepTime);
  }

  const formattedValue = Number.isInteger(displayValue)
    ? displayValue.toLocaleString()
    : displayValue.toFixed(1);

  return (
    <div
      ref={ref}
      className="flex flex-col items-center gap-2 p-4 sm:p-5 min-w-[140px]"
    >
      <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-[var(--color-primary)]/10">
        <Icon className="w-5 h-5 text-[var(--color-primary)]" />
      </div>
      <span className="text-2xl sm:text-3xl font-bold text-[var(--text-primary)] tabular-nums">
        {formattedValue}{suffix}
      </span>
      <span className="text-xs sm:text-sm text-[var(--text-muted)] text-center">
        {label}
      </span>
    </div>
  );
}
