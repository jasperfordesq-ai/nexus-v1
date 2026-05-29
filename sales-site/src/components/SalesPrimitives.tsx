// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Chip } from '@heroui/react';
import { ArrowRight, CheckCircle2, Clock3, Network, ShieldCheck, UsersRound, WalletCards } from 'lucide-react';
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

export interface PathwayCardProps {
  eyebrow: string;
  title: string;
  description: string;
  price?: string;
  bullets: string[];
  ctaLabel: string;
  onPress: () => void;
  icon: LucideIcon;
  tone?: 'community' | 'network';
}

export interface ProofMetricProps {
  label: string;
  value: string;
  detail: string;
  icon: LucideIcon;
  accent?: Accent;
}

export interface CapabilityBandProps {
  eyebrow: string;
  title: string;
  description: string;
  modules: Array<{ id: string; name: string; description: string }>;
  icon: LucideIcon;
}

export interface PricingPlanSummary {
  id: string;
  name: string;
  price: string;
  meta: string;
  bestFor: string;
  details: string[];
}

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

export function PathwayCard({
  eyebrow,
  title,
  description,
  price,
  bullets,
  ctaLabel,
  onPress,
  icon: Icon,
  tone = 'community',
}: PathwayCardProps) {
  return (
    <SurfaceCard
      tone={tone === 'network' ? 'accent' : 'raised'}
      interactive
      className="pathway-card flex h-full flex-col p-6"
    >
      <div className="flex items-start justify-between gap-4">
        <span className="grid size-12 place-items-center rounded-2xl border border-white/10 bg-white/[0.06]">
          <Icon className={cx('size-6', tone === 'network' ? accentText.accent : accentText.primary)} />
        </span>
        <Chip color={tone === 'network' ? 'accent' : 'success'} variant="soft">
          {eyebrow}
        </Chip>
      </div>
      <h3 className="mt-6 text-2xl font-black text-white">{title}</h3>
      {price ? <p className="mt-2 text-3xl font-black text-[var(--color-primary)]">{price}</p> : null}
      <p className="mt-3 text-sm leading-7 text-white/62">{description}</p>
      <ul className="mt-5 grid gap-2">
        {bullets.map((bullet) => (
          <li key={bullet} className="grid grid-cols-[auto_1fr] gap-2 text-sm leading-6 text-white/66">
            <CheckCircle2 className="mt-1 size-4 text-[var(--color-accent)]" />
            <span>{bullet}</span>
          </li>
        ))}
      </ul>
      <Button className="mt-6 w-full" variant={tone === 'network' ? 'primary' : 'outline'} onPress={onPress}>
        {ctaLabel}
        <ArrowRight className="size-4" />
      </Button>
    </SurfaceCard>
  );
}

export function ProofMetric({ label, value, detail, icon: Icon, accent = 'accent' }: ProofMetricProps) {
  return (
    <div className="proof-metric nexus-surface nexus-surface--subtle p-4">
      <div className="flex items-center justify-between gap-3">
        <Icon className={cx('size-5', accentText[accent])} />
        <p className="text-2xl font-black text-white">{value}</p>
      </div>
      <p className="mt-3 text-xs font-black tracking-[0.12em] text-white/45 uppercase">{label}</p>
      <p className="mt-2 text-sm leading-6 text-white/58">{detail}</p>
    </div>
  );
}

export function ProductCockpit({ compact = false }: { compact?: boolean }) {
  const cockpitRows = [
    ['time credits', '8,420 logged', WalletCards, 'primary'],
    ['active members', '1,284 this quarter', UsersRound, 'success'],
    ['federation', '4 partner communities', Network, 'accent'],
    ['support cover', 'retainer eligible', ShieldCheck, 'warning'],
  ] as const;

  return (
    <SurfaceCard tone="raised" className={cx('product-cockpit p-4 md:p-5', compact && 'product-cockpit--compact')}>
      <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
        <div>
          <p className="text-xs font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">NEXUS cockpit</p>
          <p className="mt-1 text-xl font-black text-white">Example community operating view</p>
        </div>
        <Chip color="accent" variant="soft">
          Demo data
        </Chip>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {cockpitRows.map(([label, value, Icon, accent]) => (
          <div key={label} className="rounded-2xl border border-white/10 bg-black/22 p-4">
            <Icon className={cx('size-5', accentText[accent as Accent])} />
            <p className="mt-4 text-lg font-black text-white">{value}</p>
            <p className="mt-1 text-xs font-semibold tracking-[0.12em] text-white/42 uppercase">{label}</p>
          </div>
        ))}
      </div>
      <div className="mt-4 rounded-2xl border border-[color:var(--color-accent)]/24 bg-[color:var(--color-accent)]/8 p-4">
        <div className="flex items-center gap-3">
          <Clock3 className="size-5 text-[var(--color-accent)]" />
          <div>
            <p className="font-black text-white">Moderation and impact queue</p>
            <p className="text-sm leading-6 text-white/58">
              Offers, requests, volunteer hours, safeguarding reviews, and impact reports in one operating layer.
            </p>
          </div>
        </div>
      </div>
    </SurfaceCard>
  );
}

export function CapabilityBand({ eyebrow, title, description, modules, icon: Icon }: CapabilityBandProps) {
  return (
    <SurfaceCard className="capability-band p-0">
      <div className="grid gap-4 border-b border-white/10 bg-black/18 p-5 lg:grid-cols-[18rem_1fr] lg:items-center">
        <div>
          <p className="flex items-center gap-2 text-xs font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">
            <Icon className="size-4" />
            {eyebrow}
          </p>
          <h3 className="mt-2 text-2xl font-black text-white">{title}</h3>
        </div>
        <p className="max-w-4xl text-sm leading-6 text-white/58">{description}</p>
      </div>
      <div className="divide-y divide-white/10">
        {modules.map((module) => (
          <article key={module.id} className="grid gap-2 px-5 py-4 md:grid-cols-[minmax(12rem,18rem)_1fr] md:gap-6">
            <p className="font-bold text-white">{module.name}</p>
            <p className="text-sm leading-6 text-white/58">{module.description}</p>
          </article>
        ))}
      </div>
    </SurfaceCard>
  );
}

export function PricingLadder({ plans }: { plans: PricingPlanSummary[] }) {
  return (
    <div className="pricing-ladder grid gap-3">
      {plans.map((plan) => (
        <SurfaceCard key={plan.id} tone="subtle" className="grid gap-4 p-5 md:grid-cols-[0.72fr_0.7fr_1fr] md:items-center">
          <div>
            <p className="text-xl font-black text-white">{plan.name}</p>
            <p className="mt-1 text-xs font-semibold tracking-[0.12em] text-white/42 uppercase">{plan.meta}</p>
          </div>
          <p className="text-2xl font-black text-[var(--color-primary)]">{plan.price}</p>
          <div>
            <p className="text-sm leading-6 text-white/60">{plan.bestFor}</p>
            <p className="mt-2 text-xs leading-5 text-white/42">{plan.details.join(' / ')}</p>
          </div>
        </SurfaceCard>
      ))}
    </div>
  );
}

export function SupportModelSection() {
  return (
    <section className="nexus-section-shell">
      <div className="mx-auto max-w-7xl px-5 py-16">
        <SectionHeader eyebrow="Support model" title="Support commitments should be commercially funded.">
          Solo-led by default means standard support is deliberately modest: async help, clear upgrade paths, and realistic response targets. Faster
          or broader cover is a paid operating model, not a casual promise.
        </SectionHeader>
        <div className="grid gap-5 lg:grid-cols-3">
          {[
            [
              'Standard support',
              'Included support is best-effort and async. It suits small teams that can tolerate normal response times and do not need formal incident cover.',
            ],
            [
              'Retained support',
              'Priority and managed plans buy more attention, a clearer route into the queue, operational reviews, and contract-funded support cover where the client size justifies it.',
            ],
            [
              'Major-client support retainer',
              'Critical services need agreed cover windows, escalation terms, and budget for an external incident partner when the contract requires capacity beyond a solo developer.',
            ],
          ].map(([title, body], index) => (
            <SurfaceCard key={title} tone={index === 2 ? 'accent' : 'subtle'} className="p-5">
              <p className="text-lg font-black text-white">{title}</p>
              <p className="mt-3 text-sm leading-6 text-white/58">{body}</p>
            </SurfaceCard>
          ))}
        </div>
      </div>
    </section>
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
