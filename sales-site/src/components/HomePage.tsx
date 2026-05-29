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
  ['Time becomes visible', 'Members can log, exchange, and review hours in a transparent shared ledger.'],
  [
    'Programmes can grow',
    'Volunteering, organisations, groups, events, and resources sit beside timebanking instead of in separate tools.',
  ],
  ['Networks can connect', 'Federation and tenant hierarchy let local communities become regional or national infrastructure.'],
] as const;

export default function HomePage({ onNavigate }: HomePageProps) {
  const entryPlan = communityTimebankPlans[0];
  const fullPlan = hostingPlans[0];

  return (
    <>
      <section className="sales-hero sales-hero--product border-b border-white/10">
        <div className="relative z-10 mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[minmax(0,0.92fr)_minmax(24rem,0.78fr)] lg:items-center lg:py-20">
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
              Project NEXUS brings time credits, volunteering, events, messaging, governance, federation, accessibility, and managed hosting into one serious platform for communities that need to grow.
            </p>
            <div className="mt-9 grid gap-4 lg:grid-cols-2">
              <PathwayCard
                eyebrow="Start"
                title="Start a timebank"
                description="A focused managed timebanking lane for local groups, pilots, mutual aid projects, and small community organisations."
                price={`from ${formatCurrency(entryPlan.annualMonthlyEur)}/mo`}
                bullets={['Offers and requests', 'Time credit wallet', 'Members, groups, events, and messaging']}
                ctaLabel="Price community lane"
                icon={HandCoins}
                onPress={() => onNavigate('/hosting')}
              />
              <PathwayCard
                eyebrow="Scale"
                title="Run a civic network"
                description="The full NEXUS platform for public-sector programmes, funded networks, and multi-community civic infrastructure."
                price={`from ${formatCurrency(fullPlan.monthlyEur)}/mo`}
                bullets={['Multi-tenancy and federation', 'Volunteering and governance', 'Accessibility, support, and scale']}
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
        <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr] lg:items-stretch">
          <ProductCockpit compact />
          <SurfaceCard tone="raised" className="p-6">
            <p className="text-sm font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">Community outcomes</p>
            <h3 className="mt-3 text-3xl font-black text-white">Built for trust, participation, and visible local impact.</h3>
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
          <SectionHeader accent="primary" eyebrow="Product system" title="One platform for community exchange, participation, and operations.">
            NEXUS is not a landing-page promise. It is a production React and Laravel system with real modules, live communities, and a commercial hosting path.
          </SectionHeader>
          <div className="grid gap-4 lg:grid-cols-4">
            {platformPillars.map(([title, body, Icon]) => (
              <SurfaceCard key={title} tone="subtle" className="p-5">
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
        <SurfaceCard tone="accent" className="grid gap-8 p-6 md:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <p className="nexus-kicker text-[color:var(--color-primary)]">Choose your path</p>
            <h2 className="mt-3 text-3xl font-black leading-tight text-white md:text-5xl">
              Start small or procure the full platform.
            </h2>
            <p className="mt-4 max-w-3xl text-base leading-8 text-white/64">
              The same open-source foundation supports both the grassroots entry lane and serious civic networks.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Button onPress={() => onNavigate('/hosting')}>
              Start with pricing
              <ArrowRight className="size-4" />
            </Button>
            <Button variant="outline" onPress={() => onNavigate('/features')}>
              Explore features
            </Button>
            <Button variant="outline" onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}>
              <GitBranch className="size-4" />
              View source
            </Button>
          </div>
        </SurfaceCard>
      </section>
    </>
  );
}
