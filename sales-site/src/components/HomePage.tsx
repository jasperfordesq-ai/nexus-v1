// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Chip } from '@heroui/react';
import {
  ArrowRight,
  CalendarDays,
  Globe2,
  HandCoins,
  Layers3,
  Network,
  Rocket,
  ShieldCheck,
  Sparkles,
  UsersRound,
  WalletCards,
} from 'lucide-react';

import { communityTimebankPlans, hostingPlans } from '../data/pricing';
import { formatCurrency } from '../lib/pricingEngine';

interface HomePageProps {
  onNavigate: (href: string) => void;
}

const highlights = [
  { label: 'Community entry', value: 'EUR29/mo', icon: HandCoins },
  { label: 'Production modules', value: '60+', icon: Sparkles },
  { label: 'Languages', value: '11', icon: Globe2 },
  { label: 'Licence', value: 'AGPL', icon: ShieldCheck },
];

const buyingLanes = [
  {
    title: 'Community Timebanking',
    eyebrow: 'Start',
    body: 'A lean managed timebank for offers, requests, hours, members, groups, events, messaging, admin basics, backups, and upgrades.',
    icon: HandCoins,
  },
  {
    title: 'Full Platform Hosting',
    eyebrow: 'Grow',
    body: 'All stable NEXUS modules with capacity tiers, support, maintenance, launch work, migration, and add-ons.',
    icon: Layers3,
  },
  {
    title: 'Network & Federation',
    eyebrow: 'Connect',
    body: 'Multi-community programmes, tenant hierarchy, federation, custom adapters, and managed operations for serious civic infrastructure.',
    icon: Network,
  },
];

const productSystem = [
  { label: 'Time credits', body: 'Equal-time exchange, wallets, logs, reviews, and broker controls.', icon: WalletCards },
  { label: 'Community life', body: 'Members, groups, events, messages, resources, posts, and public pages.', icon: UsersRound },
  { label: 'Participation', body: 'Volunteering, jobs, goals, polls, challenges, achievements, and impact reporting.', icon: CalendarDays },
  { label: 'Operations', body: 'Admin, moderation, localisation, accessibility, backups, search, and deployment.', icon: ShieldCheck },
];

const stackItems = [
  'React 19',
  'TypeScript',
  'HeroUI v3',
  'Tailwind CSS 4',
  'Laravel 12',
  'MariaDB',
  'Redis',
  'Meilisearch',
  'Pusher',
  'Firebase FCM',
];

export default function HomePage({ onNavigate }: HomePageProps) {
  const entryPlan = communityTimebankPlans[0];
  const plusPlan = communityTimebankPlans[1];
  const fullPlan = hostingPlans[0];

  return (
    <>
      <section className="sales-hero border-b border-white/10">
        <img
          src="/images/nexus-logo.png"
          alt=""
          aria-hidden="true"
          className="sales-hero__image"
        />
        <div className="relative z-10 mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[minmax(0,0.95fr)_minmax(24rem,0.72fr)] lg:items-end lg:py-20">
          <div className="max-w-4xl">
            <div className="mb-6 flex flex-wrap gap-2">
              <Chip color="accent" variant="soft">
                Open source
              </Chip>
              <Chip color="success" variant="soft">
                Managed hosting
              </Chip>
              <Chip color="warning" variant="soft">
                Community Edition from EUR29/mo
              </Chip>
            </div>
            <h1 className="max-w-5xl text-4xl font-black leading-[1.02] tracking-normal text-white sm:text-5xl md:text-7xl">
              Launch a timebank now. Grow into the full civic platform.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/72">
              Project NEXUS brings time credits, community exchange, volunteering, events, content, messaging, governance, federation, and managed hosting into one open-source platform.
            </p>
            <div className="mt-9 grid gap-3 sm:flex sm:flex-wrap">
              <Button className="w-full sm:w-auto" size="lg" onPress={() => onNavigate('/hosting')}>
                Build a hosting quote
                <ArrowRight className="size-5" />
              </Button>
              <Button className="w-full sm:w-auto" size="lg" variant="outline" onPress={() => onNavigate('/features')}>
                Explore the platform
              </Button>
              <Button className="w-full sm:w-auto" size="lg" variant="outline" onPress={() => window.location.assign('https://hour-timebank.ie')}>
                See live community
              </Button>
            </div>
          </div>

          <div className="rounded-[1.25rem] border border-white/10 bg-[var(--surface-base)]/72 p-5 shadow-2xl shadow-black/30 backdrop-blur-xl">
            <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
              <div>
                <p className="text-xs font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">Pricing snapshot</p>
                <p className="mt-1 text-2xl font-black text-white">Pick the right size first.</p>
              </div>
              <Rocket className="size-8 text-[var(--color-primary)]" />
            </div>
            <div className="mt-4 grid gap-3">
              <HeroPriceRow label={entryPlan.name} value={`${formatCurrency(entryPlan.annualMonthlyEur)}/mo`} note="lean timebanking" />
              <HeroPriceRow label={plusPlan.name} value={`${formatCurrency(plusPlan.annualMonthlyEur)}/mo`} note="reports and donations" />
              <HeroPriceRow label={`${fullPlan.name} full platform`} value={`${formatCurrency(fullPlan.monthlyEur)}/mo`} note="all stable modules" />
            </div>
          </div>
        </div>
      </section>

      <section className="border-b border-white/10 bg-white/[0.025]">
        <div className="mx-auto grid max-w-7xl metric-grid gap-3 px-5 py-5">
          {highlights.map((item) => {
            const Icon = item.icon;
            return (
              <div key={item.label} className="grid grid-cols-[auto_1fr] items-center gap-3 rounded-xl border border-white/10 bg-black/20 p-4">
                <Icon className="size-5 text-[var(--color-primary)]" />
                <div>
                  <p className="text-xl font-black text-white">{item.value}</p>
                  <p className="text-xs font-semibold text-white/52 uppercase">{item.label}</p>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="mb-9 grid gap-6 lg:grid-cols-[0.78fr_1.22fr]">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">Commercial shape</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">A grown-up price ladder for communities at different stages.</h2>
          </div>
          <p className="text-base leading-8 text-white/64">
            The entry plan stays affordable because it is focused. The full platform stays credible because serious infrastructure, support, migration, and operations are priced properly.
          </p>
        </div>
        <div className="grid gap-5 lg:grid-cols-3">
          {buyingLanes.map((lane) => {
            const Icon = lane.icon;
            return (
              <Card key={lane.title} className="min-h-[19rem] border border-white/10 bg-white/[0.055] p-6">
                <div className="flex items-start justify-between gap-4">
                  <Icon className="size-8 text-[var(--color-accent)]" />
                  <span className="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-black text-[var(--color-primary)] uppercase">
                    {lane.eyebrow}
                  </span>
                </div>
                <Card.Header className="px-0">
                  <Card.Title className="text-2xl font-black text-white">{lane.title}</Card.Title>
                  <Card.Description className="text-base leading-7 text-white/62">{lane.body}</Card.Description>
                </Card.Header>
              </Card>
            );
          })}
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.035]">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <div className="mb-9 grid gap-6 lg:grid-cols-[0.78fr_1.22fr]">
            <div>
              <p className="text-sm font-bold tracking-[0.16em] text-[var(--color-primary)] uppercase">Product system</p>
              <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">The front door now matches the actual platform underneath.</h2>
            </div>
            <p className="text-base leading-8 text-white/64">
              NEXUS is not a landing-page promise. It is a production React and Laravel system with real communities, real modules, and a quote flow that can grow into proper ordering.
            </p>
          </div>
          <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
            <div className="overflow-hidden rounded-[1.25rem] border border-white/10 bg-black/20">
              <img
                src="/images/nexus-banner.png"
                alt="Project NEXUS platform map showing timebanking, members, events, jobs, marketplace, volunteering, polls, goals, messaging, and community tools"
                className="aspect-[16/9] w-full object-cover"
              />
            </div>
            <div className="grid gap-3">
              {productSystem.map((item) => {
                const Icon = item.icon;
                return (
                  <article key={item.label} className="grid grid-cols-[auto_1fr] gap-4 rounded-2xl border border-white/10 bg-black/18 p-4">
                    <span className="grid size-11 place-items-center rounded-xl border border-white/10 bg-white/[0.05]">
                      <Icon className="size-5 text-[var(--color-accent)]" />
                    </span>
                    <div>
                      <h3 className="font-black text-white">{item.label}</h3>
                      <p className="mt-1 text-sm leading-6 text-white/58">{item.body}</p>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">Professional stack</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">Modern stack, open code, commercial hosting.</h2>
            <p className="mt-5 max-w-2xl text-base leading-8 text-white/64">
              Buyers can self-host under AGPL, or pay for the managed service: hosting, uptime, upgrades, backups, support, migration, compliance support, and custom delivery.
            </p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Button onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}>
                View source
              </Button>
              <Button variant="outline" onPress={() => onNavigate('/hosting')}>
                Open pricing
                <ArrowRight className="size-4" />
              </Button>
            </div>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {stackItems.map((item) => (
              <div key={item} className="rounded-xl border border-white/10 bg-white/[0.045] px-4 py-3 text-sm font-bold text-white/76">
                {item}
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}

function HeroPriceRow({ label, value, note }: { label: string; value: string; note: string }) {
  return (
    <div className="grid grid-cols-[1fr_auto] gap-4 rounded-xl border border-white/10 bg-white/[0.045] p-3">
      <div>
        <p className="font-bold text-white">{label}</p>
        <p className="text-xs font-semibold text-white/45 uppercase">{note}</p>
      </div>
      <p className="text-right text-lg font-black text-[var(--color-primary)]">{value}</p>
    </div>
  );
}
