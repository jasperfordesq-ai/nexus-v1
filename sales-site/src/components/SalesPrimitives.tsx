// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Card } from '@heroui/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Accent = 'accent' | 'primary' | 'success' | 'warning';
type SurfaceTone = 'default' | 'raised' | 'accent' | 'subtle';

const accentText: Record<Accent, string> = {
  accent: 'text-[color:var(--color-accent)]',
  primary: 'text-[color:var(--color-primary)]',
  success: 'text-[color:var(--color-success)]',
  warning: 'text-[color:var(--color-warning)]',
};

const surfaceTone: Record<SurfaceTone, string> = {
  default: 'nexus-surface',
  raised: 'nexus-surface nexus-surface--raised',
  accent: 'nexus-surface nexus-surface--accent',
  subtle: 'nexus-surface nexus-surface--subtle',
};

export function cx(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(' ');
}

export function SectionHeader({
  eyebrow,
  title,
  children,
  accent = 'accent',
  icon: Icon,
  compact = false,
  className,
}: {
  eyebrow: string;
  title: string;
  children?: ReactNode;
  accent?: Accent;
  icon?: LucideIcon;
  compact?: boolean;
  className?: string;
}) {
  return (
    <div className={cx(compact ? 'mb-9 max-w-3xl' : 'mb-9 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]', className)}>
      <div>
        <p className={cx('nexus-kicker', accentText[accent])}>
          {Icon ? <Icon className="size-4" /> : null}
          {eyebrow}
        </p>
        <h2 className="mt-3 text-3xl font-black leading-tight text-white md:text-5xl">{title}</h2>
      </div>
      {children ? <p className="text-base leading-8 text-white/64">{children}</p> : null}
    </div>
  );
}

export function SurfaceCard({
  children,
  tone = 'default',
  interactive = false,
  className,
}: {
  children: ReactNode;
  tone?: SurfaceTone;
  interactive?: boolean;
  className?: string;
}) {
  return <Card className={cx(surfaceTone[tone], interactive && 'nexus-surface--interactive nexus-focus-ring', className)}>{children}</Card>;
}

export function MetricTile({
  label,
  value,
  icon: Icon,
  accent = 'primary',
  className,
}: {
  label: string;
  value: string;
  icon: LucideIcon;
  accent?: Accent;
  className?: string;
}) {
  return (
    <div className={cx('nexus-surface nexus-surface--subtle grid grid-cols-[auto_1fr] items-center gap-3 p-4', className)}>
      <Icon className={cx('size-5', accentText[accent])} />
      <div>
        <p className="text-xl font-black text-white">{value}</p>
        <p className="text-xs font-semibold tracking-[0.08em] text-white/52 uppercase">{label}</p>
      </div>
    </div>
  );
}

export function ProcessSteps({ steps }: { steps: string[] }) {
  return (
    <ol className="grid gap-3 md:grid-cols-4">
      {steps.map((stage, index) => (
        <li key={stage} className="nexus-surface nexus-surface--subtle p-3">
          <p className="text-xs font-black text-[color:var(--color-primary)]">{String(index + 1).padStart(2, '0')}</p>
          <p className="mt-1 text-sm font-bold text-white">{stage}</p>
        </li>
      ))}
    </ol>
  );
}
