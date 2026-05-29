// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Chip } from '@heroui/react';
import {
  ArrowRight,
  Building2,
  GitBranch,
  Globe2,
  HandCoins,
  Network,
  ShieldCheck,
  Sparkles,
  UsersRound,
} from 'lucide-react';

import { communityTimebankPlans, hostingPlans } from '../data/pricing';
import { formatCurrency } from '../lib/pricingEngine';
import { PathwayCard, ProductCockpit, ProofMetric, SectionHeader, SurfaceCard } from './SalesPrimitives';

interface HomePageProps {
  onNavigate: (href: string) => void;
}

const proofMetrics = [
  {
    label: 'Entry lane',
    value: 'EUR29/mo',
    detail: 'Managed Community Timebanking for small groups.',
    icon: HandCoins,
    accent: 'primary' as const,
  },
  {
    label: 'Production modules',
    value: '60+',
    detail: 'Community, participation, operations, and federation tools.',
    icon: Sparkles,
    accent: 'accent' as const,
  },
  {
    label: 'Languages',
    value: '11',
    detail: 'Multilingual foundation including Arabic RTL.',
    icon: Globe2,
    accent: 'success' as const,
  },
  {
    label: 'Licence',
    value: 'AGPL',
    detail: 'Auditable open-source code with managed hosting available.',
    icon: ShieldCheck,
    accent: 'warning' as const,
  },
];

const platformPillars = [
  ['Time credits', 'Equal-time exchange, wallets, exchange logs, reviews, and broker controls.', HandCoins],
  ['Community life', 'Members, groups, events, messages, resources, public pages, and social participation.', UsersRound],
  ['Civic programmes', 'Volunteering, organisations, jobs, goals, polls, challenges, and impact reporting.', Building2],
  ['Network infrastructure', 'Multi-tenancy, federation, tenant hierarchy, governance, accessibility, and operational tooling.', Network],
] as const;

const communityOutcomes = [
  ['Time becomes visible', 'People can see the hours, care, coordination, and practical help already moving through a community.'],
  ['Programmes can grow', 'Small timebanks can mature into funded volunteering, participation, and civic delivery without changing systems.'],
  ['Networks can connect', 'Separate groups can keep local identity while sharing infrastructure, governance, data, and federation paths.'],
] as const;

export default function HomePage({ onNavigate }: HomePageProps) {
  const entryPlan = communityTimebankPlans[0];
  const fullPlan = hostingPlans[0];

  return (
    <>
      <section className="sales-hero sales-hero--product border-b border-white/10">
        <div className="relative z-10 mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[minmax(0,0.95fr)_minmax(24rem,0.72fr)] lg:items-center lg:py-20">
          <div className="max-w-4xl">
            <div className="mb-6 flex flex-wrap gap-2">
              <Chip color="accent" variant="soft">
                Premium civic SaaS
              </Chip>
              <Chip color="success" variant="soft">
                Community rooted
              </Chip>
              <Chip color="warning" variant="soft">
                Open source
              </Chip>
            </div>
            <h1 className="max-w-5xl text-4xl font-black leading-[1.02] tracking-normal text-white sm:text-5xl md:text-7xl">
              Community infrastructure, from local timebanks to civic networks.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/72">
              Project NEXUS brings time credits, volunteering, events, messaging, governance, federation, accessibility, and managed hosting into one open-source platform for real community work.
            </p>
            <div className="mt-9 grid gap-4 lg:grid-cols-2">
              <PathwayCard
                eyebrow="Community lane"
                title="Start a timebank"
                description="A managed entry path for local exchange, offers, requests, member coordination, and day-one operations."
                price={`from ${formatCurrency(entryPlan.annualMonthlyEur)}/mo`}
                bullets={['Time credits and member basics', 'Groups, events, and messaging', 'Managed backups and upgrades']}
                ctaLabel="Price community lane"
                icon={HandCoins}
                onPress={() => onNavigate('/hosting')}
              />
              <PathwayCard
                eyebrow="Network lane"
                title="Run a civic network"
                description="The full platform for multi-module community programmes, tenant operations, federation, and growth."
                price={`from ${formatCurrency(fullPlan.monthlyEur)}/mo`}
                bullets={['60+ production modules', 'Governance and operational tooling', 'Room for federation and scale']}
                ctaLabel="Explore full platform"
                icon={Network}
                tone="network"
                onPress={() => onNavigate('/features')}
              />
            </div>
          </div>

          <ProductCockpit />
        </div>
      </section>

      <section className="nexus-section-shell">
        <div className="mx-auto grid max-w-7xl metric-grid gap-3 px-5 py-5">
          {proofMetrics.map((item) => (
            <ProofMetric key={item.label} {...item} />
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <SectionHeader eyebrow="Hybrid proof" title="A serious platform with a human reason to exist.">
          Product UI proves the platform is real. Community outcomes prove why it matters.
        </SectionHeader>
        <div className="grid gap-5 lg:grid-cols-[0.95fr_1.05fr] lg:items-stretch">
          <ProductCockpit compact />
          <SurfaceCard tone="raised" className="p-6">
            <p className="nexus-kicker text-[color:var(--color-accent)]">Community outcomes</p>
            <h3 className="mt-3 text-3xl font-black text-white">Infrastructure should make mutual support easier to organise.</h3>
            <div className="mt-6 grid gap-3">
              {communityOutcomes.map(([title, body]) => (
                <div key={title} className="rounded-2xl border border-white/10 bg-white/[0.045] p-4">
                  <p className="font-black text-white">{title}</p>
                  <p className="mt-2 text-sm leading-6 text-white/58">{body}</p>
                </div>
              ))}
            </div>
          </SurfaceCard>
        </div>
      </section>

      <section className="nexus-section-shell">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <SectionHeader eyebrow="Product system" title="One platform for community exchange, participation, and operations.">
            NEXUS combines the practical mechanics of timebanking with the governance, programme, accessibility, and federation layers needed for larger civic networks.
          </SectionHeader>
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            {platformPillars.map(([title, body, Icon]) => (
              <SurfaceCard key={title} interactive className="p-5">
                <span className="grid size-12 place-items-center rounded-2xl border border-white/10 bg-white/[0.06]">
                  <Icon className="size-6 text-[var(--color-accent)]" />
                </span>
                <h3 className="mt-6 text-xl font-black text-white">{title}</h3>
                <p className="mt-3 text-sm leading-6 text-white/60">{body}</p>
              </SurfaceCard>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <SurfaceCard tone="accent" className="p-6 md:p-8">
          <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <p className="nexus-kicker text-[color:var(--color-primary)]">Procurement path</p>
              <h2 className="mt-3 text-3xl font-black leading-tight text-white md:text-5xl">
                Start small or procure the full platform.
              </h2>
              <p className="mt-4 max-w-3xl text-base leading-8 text-white/64">
                Choose the managed community lane for a focused timebank, or explore the full hosting ladder for serious civic infrastructure.
              </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-3 lg:min-w-[33rem]">
              <Button className="w-full" onPress={() => onNavigate('/hosting')}>
                Start with pricing
                <ArrowRight className="size-4" />
              </Button>
              <Button className="w-full" variant="outline" onPress={() => onNavigate('/features')}>
                Explore features
              </Button>
              <Button
                className="w-full"
                variant="outline"
                onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}
              >
                <GitBranch className="size-4" />
                View source
              </Button>
            </div>
          </div>
        </SurfaceCard>
      </section>
    </>
  );
}
