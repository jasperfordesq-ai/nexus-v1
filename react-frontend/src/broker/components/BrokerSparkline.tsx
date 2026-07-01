// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerSparkline — a dependency-free inline SVG trend line for stat cards.
 * Recharts stays reserved for the real charts (dashboard, onboarding funnel);
 * pulling it into every stat card would bloat each page chunk for a 20px line.
 * Decorative only: aria-hidden, the numeric value next to it carries meaning.
 */

interface BrokerSparklineProps {
  /** Data points, oldest first. Needs ≥2 points to render. */
  points: number[];
  className?: string;
  width?: number;
  height?: number;
}

export function BrokerSparkline({ points, className = '', width = 72, height = 24 }: BrokerSparklineProps) {
  if (!points || points.length < 2) return null;

  const min = Math.min(...points);
  const max = Math.max(...points);
  const range = max - min || 1;
  const pad = 2;
  const stepX = (width - pad * 2) / (points.length - 1);

  const coords = points.map((p, i) => {
    const x = pad + i * stepX;
    const y = pad + (height - pad * 2) * (1 - (p - min) / range);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });

  const linePoints = coords.join(' ');
  const areaPoints = `${pad},${height - pad} ${linePoints} ${width - pad},${height - pad}`;

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      width={width}
      height={height}
      aria-hidden="true"
      focusable="false"
      className={`overflow-visible ${className}`}
    >
      <polygon points={areaPoints} fill="currentColor" opacity={0.12} />
      <polyline
        points={linePoints}
        fill="none"
        stroke="currentColor"
        strokeWidth={1.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export default BrokerSparkline;
