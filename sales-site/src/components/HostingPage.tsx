// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Chip } from '@heroui/react';
import { ArrowDown, BadgeEuro, Boxes, CheckCircle2, Layers3, Scale, ShieldCheck, Sparkles } from 'lucide-react';
import { useCallback } from 'react';

import {
  communityTimebankPlans,
  hostingPlans,
  madeOpenCheckedOn,
  madeOpenCommunityBenchmarks,
  madeOpenPlatformBenchmarks,
} from '../data/pricing';
import { formatCurrency } from '../lib/pricingEngine';
import QuoteBuilder from './QuoteBuilder';

interface HostingPageProps {
  onNavigate: (href: string) => void;
}

const proofPoints = [
  { label: 'Community entry', value: 'EUR29/mo', icon: BadgeEuro },
  { label: 'Timebanking lane', value: 'Limited', icon: Boxes },
  { label: 'Full platform lane', value: 'All modules', icon: Layers3 },
  { label: 'Licence', value: 'AGPL', icon: Scale },
];

const pricingPrinciples = [
  {
    title: 'Win the entry-level comparison',
    body: 'Community Edition comes in below the public Made Open Community Timebanks entry price, but does it by limiting modules rather than by pretending support and hosting are free.',
  },
  {
    title: 'Protect the professional signal',
    body: 'The price is not a bargain-bin full platform. It is a focused timebanking package with caps, support expectations, backups, upgrades, and a clear upgrade route.',
  },
  {
    title: 'Keep procurement honest',
    body: 'Full NEXUS hosting remains priced around capacity, infrastructure, support, maintenance, onboarding, data migration, and custom delivery.',
  },
];

export default function HostingPage({ onNavigate }: HostingPageProps) {
  const handleQuoteChange = useCallback(() => undefined, []);

  return (
    <>
      <section className="overflow-hidden border-b border-white/10">
        <div className="mx-auto grid max-w-7xl gap-12 px-5 py-16 lg:grid-cols-[1fr_0.95fr] lg:py-24">
          <div className="flex flex-col justify-center">
            <p className="mb-5 w-fit rounded-full border border-[#55d6be]/30 bg-[#55d6be]/10 px-4 py-2 text-xs font-black tracking-[0.16em] text-[#bffbf2] uppercase">
              Partner pricing and order workbench
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
              A cheaper way in, without cheapening the platform.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
              Project NEXUS now has two commercial lanes: a lean Community Timebanking offer from EUR29/month when billed annually, and a full managed platform offer for serious civic networks.
            </p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Button size="lg" onPress={() => document.getElementById('quote-builder')?.scrollIntoView({ behavior: 'smooth' })}>
                Build quote
                <ArrowDown className="size-5" />
              </Button>
              <Button size="lg" variant="outline" onPress={() => onNavigate('/features')}>
                Review features
              </Button>
            </div>
          </div>

          <div className="flex items-center">
            <div className="glass-panel w-full rounded-[1.25rem] p-5">
              <div className="flex items-center gap-3 border-b border-white/10 pb-5">
                <ShieldCheck className="size-9 text-[#55d6be]" />
                <div>
                  <p className="text-sm font-bold tracking-[0.14em] text-white/45 uppercase">NEXUS managed hosting</p>
                  <p className="text-2xl font-black text-white">Feature-limited entry. Full-stack upgrade path.</p>
                </div>
              </div>
              <div className="mt-5 grid metric-grid gap-3">
                {proofPoints.map((point) => {
                  const Icon = point.icon;
                  return (
                    <div key={point.label} className="rounded-xl border border-white/10 bg-black/20 p-4">
                      <Icon className="mb-3 size-5 text-[#f5c86a]" />
                      <p className="text-2xl font-black text-white">{point.value}</p>
                      <p className="text-xs font-semibold text-white/45 uppercase">{point.label}</p>
                    </div>
                  );
                })}
              </div>
              <div className="mt-5 grid gap-2">
                {communityTimebankPlans.map((plan) => (
                  <div key={plan.id} className="grid grid-cols-[1fr_auto] rounded-xl border border-white/10 bg-white/[0.035] p-3 text-sm">
                    <span className="font-bold text-white">{plan.name}</span>
                    <span className="text-white/58">{formatCurrency(plan.annualMonthlyEur)}/mo annual</span>
                  </div>
                ))}
                {hostingPlans.slice(0, 3).map((plan) => (
                  <div key={plan.id} className="grid grid-cols-[1fr_auto] rounded-xl border border-white/10 bg-black/14 p-3 text-sm">
                    <span className="font-bold text-white">{plan.name} full platform</span>
                    <span className="text-white/58">{formatCurrency(plan.monthlyEur)}/mo</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="mb-9 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Pricing position</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">The entry plan is cheaper because it is narrower.</h2>
          </div>
          <p className="text-base leading-8 text-white/64">
            Made Open's public Community Timebanks plans are a useful reference point for a dedicated timebanking package. NEXUS should not undercut by selling the whole civic platform for pocket money; it should undercut by offering the right smaller product first.
          </p>
        </div>
        <div className="grid gap-5 lg:grid-cols-3">
          {pricingPrinciples.map((principle) => (
            <Card key={principle.title} className="border border-white/10 bg-white/[0.055] p-6">
              <Sparkles className="size-7 text-[#55d6be]" />
              <Card.Header className="px-0">
                <Card.Title className="text-2xl font-black text-white">{principle.title}</Card.Title>
                <Card.Description className="text-base leading-7 text-white/62">{principle.body}</Card.Description>
              </Card.Header>
            </Card>
          ))}
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.035]">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <div className="mb-10 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">Community Edition details</p>
              <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">A real timebanking package with the expensive extras switched off.</h2>
            </div>
            <p className="text-base leading-8 text-white/64">
              This gives small groups a way to start professionally while keeping federation, AI, multi-tenant networks, SSO, custom development, dedicated infrastructure, and heavy support in the paid upgrade path.
            </p>
          </div>
          <div className="grid gap-5 lg:grid-cols-3">
            {communityTimebankPlans.map((plan) => (
              <Card key={plan.id} className="border border-white/10 bg-black/18 p-6">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <Chip color={plan.id === 'community-edition' ? 'success' : 'accent'} variant="soft">
                      {plan.activeMemberLabel}
                    </Chip>
                    <Card.Header className="px-0">
                      <Card.Title className="text-2xl font-black text-white">{plan.name}</Card.Title>
                      <Card.Description className="text-base leading-7 text-white/62">{plan.bestFor}</Card.Description>
                    </Card.Header>
                  </div>
                </div>
                <p className="text-4xl font-black text-[#f5c86a]">{formatCurrency(plan.annualMonthlyEur)}</p>
                <p className="text-xs font-semibold text-white/45 uppercase">per month, billed annually</p>
                <div className="mt-5 grid gap-2 text-sm leading-6 text-white/58">
                  {plan.included.slice(0, 4).map((item) => (
                    <p key={item} className="grid grid-cols-[auto_1fr] gap-2">
                      <CheckCircle2 className="mt-1 size-4 text-[#55d6be]" />
                      <span>{item}</span>
                    </p>
                  ))}
                </div>
              </Card>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="mb-9 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Source-backed benchmark</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">The comparison is useful, but only after crossing the feature list.</h2>
          </div>
          <p className="text-base leading-8 text-white/64">
            Public Made Open pricing checked {madeOpenCheckedOn}. Their Community Timebanks lane is the closest match for the new NEXUS entry offer; their main platform plans are a different, higher-budget procurement lane.
          </p>
        </div>
        <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="glass-panel rounded-2xl p-5">
            <h3 className="text-xl font-black text-white">Community Timebanks benchmark</h3>
            <div className="mt-5 grid gap-3">
              {madeOpenCommunityBenchmarks.map((plan) => (
                <div key={plan.name} className="rounded-xl border border-white/10 bg-black/18 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="font-black text-white">{plan.name}</p>
                      <p className="mt-1 text-sm text-white/52">GBP{plan.monthlyGbp}/mo or GBP{plan.annualGbp}/yr</p>
                    </div>
                    <Chip variant="soft">{plan.features.length} public features</Chip>
                  </div>
                  <p className="mt-3 text-sm leading-6 text-white/56">{plan.features.join(', ')}.</p>
                </div>
              ))}
            </div>
          </div>
          <div className="glass-panel rounded-2xl p-5">
            <h3 className="text-xl font-black text-white">Main platform benchmark</h3>
            <div className="mt-5 grid gap-3">
              {madeOpenPlatformBenchmarks.map((plan) => (
                <div key={plan.name} className="rounded-xl border border-white/10 bg-black/18 p-4">
                  <p className="font-black text-white">{plan.name}</p>
                  <p className="mt-1 text-sm font-bold text-[#f5c86a]">GBP{plan.annualGbp.toLocaleString('en-IE')}/year</p>
                  <p className="mt-3 text-sm leading-6 text-white/56">{plan.detail}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <QuoteBuilder onQuoteChange={handleQuoteChange} />

      <section className="border-t border-white/10 bg-white/[0.035]">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">Still want the open-source path?</p>
            <h2 className="mt-3 text-3xl font-black text-white">The code remains free. Managed hosting buys reliability.</h2>
            <p className="mt-4 max-w-3xl text-base leading-7 text-white/62">
              Organisations can self-host under AGPL. The commercial offer is for teams who want uptime, maintenance, upgrades, backups, support, migration, and custom delivery handled properly.
            </p>
          </div>
          <Button size="lg" variant="outline" onPress={() => onNavigate('/')}>
            Back to platform overview
          </Button>
        </div>
      </section>
    </>
  );
}
