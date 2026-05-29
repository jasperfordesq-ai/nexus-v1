// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Chip } from '@heroui/react';
import { ArrowDown, BadgeEuro, Boxes, CheckCircle2, Layers3, Server, ShieldCheck, Sparkles } from 'lucide-react';
import { useCallback } from 'react';

import {
  communityTimebankPlans,
  competitorBenchmarks,
  hostingPlans,
} from '../data/pricing';
import { formatCurrency } from '../lib/pricingEngine';
import QuoteBuilder from './QuoteBuilder';
import { MetricTile, SectionHeader, SurfaceCard } from './SalesPrimitives';

interface HostingPageProps {
  onNavigate: (href: string) => void;
}

const proofPoints = [
  { label: 'Community entry', value: 'EUR29/mo', icon: BadgeEuro },
  { label: 'Published cap', value: '100k', icon: Boxes },
  { label: 'Deployment', value: 'Shared/dedicated', icon: Server },
  { label: 'Enterprise layer', value: '>100k', icon: Layers3 },
];

const pricingPrinciples = [
  {
    title: 'Win the entry-level comparison',
    body: 'Community Edition is priced as a genuinely affordable entry point, but it achieves that by limiting modules rather than by pretending support and hosting are free.',
  },
  {
    title: 'Protect the professional signal',
    body: 'The price is not a bargain-bin full platform. It is a focused timebanking package with caps, support expectations, backups, upgrades, and a clear upgrade route.',
  },
  {
    title: 'Keep procurement honest',
    body: 'Full NEXUS hosting has published capacity tiers up to 100,000 active members, then moves into bespoke enterprise pricing before high-scale usage can distort the model.',
  },
  {
    title: 'Separate hosting from architecture',
    body: 'Most buyers should stay on the main managed platform for value, while organisations with stronger assurance or performance needs can price a dedicated managed server explicitly.',
  },
];

export default function HostingPage({ onNavigate }: HostingPageProps) {
  const handleQuoteChange = useCallback(() => undefined, []);

  return (
    <>
      <section className="overflow-hidden border-b border-white/10">
        <div className="mx-auto grid max-w-7xl gap-12 px-5 py-16 lg:grid-cols-[1fr_0.95fr] lg:py-24">
          <div className="flex flex-col justify-center">
            <p className="mb-5 w-fit rounded-full border border-[color:var(--color-accent)]/30 bg-[color:var(--color-accent)]/10 px-4 py-2 text-xs font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">
              Partner pricing and order workbench
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
              A cheaper way in, without cheapening the platform.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
              Project NEXUS now has two commercial lanes: a lean Community Timebanking offer from EUR29/month when billed annually, and a full managed platform offer for serious civic networks.
            </p>
            <SurfaceCard tone="accent" className="mt-7 max-w-3xl p-5 shadow-[inset_4px_0_0_var(--color-accent)]">
              <p className="text-sm font-black tracking-[0.16em] text-[color:var(--color-accent)] uppercase">Launch pricing note</p>
              <p className="mt-3 text-base font-semibold leading-7 text-white">
                These are early published prices for a new managed hosting service, and they may change as the offer matures.
              </p>
              <p className="mt-2 text-sm leading-7 text-white/64">
                We are continuing market research, infrastructure modelling, and support-cost analysis so Project NEXUS can stay genuinely competitive without underpricing the reliability, maintenance, backups, security, and professional support that serious community platforms need. Accepted written quotes are handled through their own order terms.
              </p>
            </SurfaceCard>
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
            <SurfaceCard tone="raised" className="w-full p-5">
              <div className="flex items-center gap-3 border-b border-white/10 pb-5">
                <ShieldCheck className="size-9 text-[var(--color-accent)]" />
                <div>
                  <p className="text-sm font-bold tracking-[0.14em] text-white/45 uppercase">NEXUS managed hosting</p>
                  <p className="text-2xl font-black text-white">Feature-limited entry. Full-stack upgrade path.</p>
                </div>
              </div>
              <div className="mt-5 grid metric-grid gap-3">
                {proofPoints.map((point) => (
                  <MetricTile key={point.label} icon={point.icon} label={point.label} value={point.value} />
                ))}
              </div>
              <div className="mt-5 grid gap-2">
                {communityTimebankPlans.map((plan) => (
                  <div key={plan.id} className="nexus-surface nexus-surface--subtle grid grid-cols-[1fr_auto] p-3 text-sm">
                    <span className="font-bold text-white">{plan.name}</span>
                    <span className="text-white/58">{formatCurrency(plan.annualMonthlyEur)}/mo annual</span>
                  </div>
                ))}
                {hostingPlans.map((plan) => (
                  <div key={plan.id} className="nexus-surface nexus-surface--subtle grid grid-cols-[1fr_auto] p-3 text-sm">
                    <span className="font-bold text-white">{plan.name} full platform</span>
                    <span className="text-right text-white/58">{plan.isCustom ? 'Bespoke quote' : `${formatCurrency(plan.monthlyEur)}/mo`}</span>
                  </div>
                ))}
              </div>
            </SurfaceCard>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <SectionHeader accent="primary" eyebrow="Full platform capacity" title="Published pricing has a hard ceiling.">
          The public calculator prices realistic managed hosting up to 100,000 active members. Above that, NEXUS switches to Enterprise Custom so a high-growth or million-user platform is priced against real traffic, support, storage, and architecture.
        </SectionHeader>
        <div className="grid gap-3">
          {hostingPlans.map((plan) => (
            <SurfaceCard
              key={plan.id}
              tone={plan.isCustom ? 'accent' : 'subtle'}
              className="grid gap-4 p-5 md:grid-cols-[0.75fr_1fr_auto] md:items-center"
            >
              <div>
                <p className="text-xl font-black text-white">{plan.name}</p>
                <p className="mt-1 text-sm font-semibold text-white/45 uppercase">{plan.activeMemberLabel}</p>
              </div>
              <p className="text-sm leading-6 text-white/58">{plan.bestFor}</p>
              <p className="text-left text-2xl font-black text-[var(--color-primary)] md:text-right">
                {plan.isCustom ? 'Bespoke quote' : `${formatCurrency(plan.monthlyEur)}/mo`}
              </p>
            </SurfaceCard>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <SectionHeader accent="primary" eyebrow="Pricing position" title="The entry plan is cheaper because it is narrower.">
          The right entry offer is a dedicated timebanking package, not the whole civic platform squeezed into an unrealistic price. NEXUS starts smaller, then gives buyers a clean upgrade path when the work becomes bigger.
        </SectionHeader>
        <div className="grid gap-5 lg:grid-cols-4">
          {pricingPrinciples.map((principle) => (
            <SurfaceCard key={principle.title} interactive className="p-6">
              <Sparkles className="size-7 text-[var(--color-accent)]" />
              <h3 className="mt-6 text-2xl font-black text-white">{principle.title}</h3>
              <p className="mt-3 text-base leading-7 text-white/62">{principle.body}</p>
            </SurfaceCard>
          ))}
        </div>
      </section>

      <section className="nexus-section-shell">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <SectionHeader eyebrow="Market position" title="Cheap-to-middle by category; strongest value on breadth.">
            Against free timebanking tools, NEXUS is not the cheapest possible route. Against managed community platforms, volunteer systems, and B2B community suites, the published pricing is cheap-to-middle because most full-platform features are not locked behind custom enterprise-only pricing.
          </SectionHeader>
          <div className="grid gap-4 lg:grid-cols-2">
            {competitorBenchmarks.map((benchmark) => (
              <SurfaceCard key={benchmark.segment} tone="subtle" className="p-5">
                <div className="flex flex-wrap items-center gap-3">
                  <Chip color="accent" variant="soft">
                    {benchmark.segment}
                  </Chip>
                  <span className="text-xs font-semibold text-white/42 uppercase">{benchmark.typicalPricing}</span>
                </div>
                <p className="mt-4 text-sm font-black text-white">{benchmark.examples}</p>
                <p className="mt-3 text-sm leading-6 text-white/56">{benchmark.featurePattern}</p>
                <p className="mt-4 rounded-xl border border-[color:var(--color-accent)]/20 bg-[color:var(--color-accent)]/8 p-4 text-sm font-semibold leading-6 text-white/74">
                  {benchmark.nexusPosition}
                </p>
              </SurfaceCard>
            ))}
          </div>
        </div>
      </section>

      <section className="nexus-section-shell">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <SectionHeader eyebrow="Community Edition details" title="A real timebanking package with the expensive extras switched off.">
            This gives small groups a way to start professionally while keeping federation, AI, multi-tenant networks, SSO, custom development, dedicated infrastructure, and heavy support in the paid upgrade path.
          </SectionHeader>
          <div className="grid gap-5 lg:grid-cols-3">
            {communityTimebankPlans.map((plan) => (
              <SurfaceCard key={plan.id} interactive className="p-6">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <Chip color={plan.id === 'community-edition' ? 'success' : 'accent'} variant="soft">
                      {plan.activeMemberLabel}
                    </Chip>
                    <h3 className="mt-6 text-2xl font-black text-white">{plan.name}</h3>
                    <p className="mt-3 text-base leading-7 text-white/62">{plan.bestFor}</p>
                  </div>
                </div>
                <p className="text-4xl font-black text-[var(--color-primary)]">{formatCurrency(plan.annualMonthlyEur)}</p>
                <p className="text-xs font-semibold text-white/45 uppercase">per month, billed annually</p>
                <div className="mt-5 grid gap-2 text-sm leading-6 text-white/58">
                  {plan.included.slice(0, 4).map((item) => (
                    <p key={item} className="grid grid-cols-[auto_1fr] gap-2">
                      <CheckCircle2 className="mt-1 size-4 text-[var(--color-accent)]" />
                      <span>{item}</span>
                    </p>
                  ))}
                </div>
              </SurfaceCard>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <SectionHeader accent="primary" eyebrow="Why the pricing holds up" title="Affordable does not mean vague.">
          The entry price is protected by clear product boundaries: one tenant, fair-use caps, standard infrastructure, limited modules, and a defined support queue. Larger needs move into the full platform lane.
        </SectionHeader>
        <div className="grid gap-5 lg:grid-cols-4">
          {[
            ['One tenant', 'Community Edition is for one timebank, not a multi-community network.'],
            ['Core modules', 'Offers, requests, credits, members, groups, events, messaging, and admin basics stay on.'],
            ['Upgrade gates', 'Federation, AI, SSO, custom reports, payments, and bespoke modules stay in higher lanes.'],
            ['Service limits', 'Storage, email, support response, and launch help are explicit before anyone enquires.'],
          ].map(([title, body]) => (
            <SurfaceCard key={title} tone="subtle" className="p-5">
              <p className="text-lg font-black text-white">{title}</p>
              <p className="mt-3 text-sm leading-6 text-white/58">{body}</p>
            </SurfaceCard>
          ))}
        </div>
      </section>

      <section className="nexus-section-shell">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <SectionHeader eyebrow="Support model" title="Support commitments should be commercially funded.">
            Solo-led by default means standard support is deliberately modest: async help, clear upgrade paths, and realistic response targets. Faster or broader cover is a paid operating model, not a casual promise.
          </SectionHeader>
          <div className="grid gap-5 lg:grid-cols-3">
            <SurfaceCard tone="subtle" className="p-5">
              <p className="text-lg font-black text-white">Standard support</p>
              <p className="mt-3 text-sm leading-6 text-white/58">
                Included support is best-effort and async. It suits small teams that can tolerate normal response times and do not need formal incident cover.
              </p>
            </SurfaceCard>
            <SurfaceCard tone="subtle" className="p-5">
              <p className="text-lg font-black text-white">Retained support</p>
              <p className="mt-3 text-sm leading-6 text-white/58">
                Priority and managed plans buy more attention, a clearer route into the queue, operational reviews, and contract-funded support cover where the client size justifies it.
              </p>
            </SurfaceCard>
            <SurfaceCard tone="accent" className="p-5">
              <p className="text-lg font-black text-white">Major-client support retainer</p>
              <p className="mt-3 text-sm leading-6 text-white/64">
                Critical services need agreed cover windows, escalation terms, and budget for an external incident partner when the contract requires capacity beyond a solo developer.
              </p>
            </SurfaceCard>
          </div>
        </div>
      </section>

      <QuoteBuilder onQuoteChange={handleQuoteChange} />

      <section className="nexus-section-shell border-b-0">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">Still want the open-source path?</p>
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
