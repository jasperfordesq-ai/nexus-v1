// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Chip } from '@heroui/react';
import { ArrowRight, BadgeCheck, Globe2, HandCoins, Network, ShieldCheck, Sparkles } from 'lucide-react';

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

const pillars = [
  {
    title: 'Start small without looking small',
    body: 'Community Edition gives new timebanks a credible managed package with offers, requests, time credits, members, groups, events, messaging, admin basics, backups, and upgrades.',
    icon: HandCoins,
  },
  {
    title: 'Upgrade into the full platform',
    body: 'When the project needs federation, AI, volunteering, multi-tenant networks, payments, SSO, custom development, or managed operations, the higher platform tiers are ready.',
    icon: BadgeCheck,
  },
  {
    title: 'Built with the real product stack',
    body: 'The sales site now mirrors the confidence of the React frontend: React 19, TypeScript, HeroUI v3, Tailwind CSS 4, Laravel, MariaDB, Redis, and Meilisearch.',
    icon: Network,
  },
];

const stackItems = ['React 19', 'TypeScript', 'HeroUI v3', 'Tailwind CSS 4', 'Laravel 12', 'MariaDB', 'Redis', 'Meilisearch'];

export default function HomePage({ onNavigate }: HomePageProps) {
  const entryPlan = communityTimebankPlans[0];
  const fullPlan = hostingPlans[0];

  return (
    <>
      <section className="border-b border-white/10">
        <div className="mx-auto flex w-full max-w-7xl flex-col px-5 py-16 lg:py-24">
          <div className="grid gap-10 lg:grid-cols-[1fr_26rem] lg:items-end">
            <div className="max-w-4xl">
              <p className="mb-5 w-fit rounded-full border border-white/14 bg-white/6 px-4 py-2 text-xs font-bold tracking-[0.16em] text-[#9edbd2] uppercase">
                Open-source community infrastructure
              </p>
              <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
                Timebanking first. Full civic platform when you are ready.
              </h1>
              <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
                Project NEXUS is an AGPL-licensed platform for time credits, community exchange, federation, volunteering, content, events, governance, AI-assisted discovery, and managed hosting.
              </p>
              <div className="mt-9 flex flex-wrap gap-3">
                <Button size="lg" onPress={() => onNavigate('/hosting')}>
                  Build a hosting quote
                  <ArrowRight className="size-5" />
                </Button>
                <Button size="lg" variant="outline" onPress={() => onNavigate('/features')}>
                  Explore features
                </Button>
                <Button size="lg" variant="outline" onPress={() => window.location.assign('https://hour-timebank.ie')}>
                  See live platform
                </Button>
              </div>
            </div>

            <div className="glass-panel rounded-[1.25rem] p-5">
              <p className="text-sm font-bold tracking-[0.14em] text-white/45 uppercase">Commercial lanes</p>
              <div className="mt-4 grid gap-3">
                <div className="rounded-xl border border-[#55d6be]/25 bg-[#55d6be]/10 p-4">
                  <Chip color="success" variant="soft">
                    Entry
                  </Chip>
                  <p className="mt-3 text-2xl font-black text-white">{entryPlan.name}</p>
                  <p className="mt-1 text-3xl font-black text-[#f5c86a]">{formatCurrency(entryPlan.annualMonthlyEur)}/mo</p>
                  <p className="mt-2 text-sm leading-6 text-white/58">Feature-limited timebanking for new and small communities.</p>
                </div>
                <div className="rounded-xl border border-white/10 bg-black/20 p-4">
                  <Chip color="accent" variant="soft">
                    Full platform
                  </Chip>
                  <p className="mt-3 text-2xl font-black text-white">{fullPlan.name}</p>
                  <p className="mt-1 text-3xl font-black text-[#f5c86a]">{formatCurrency(fullPlan.monthlyEur)}/mo</p>
                  <p className="mt-2 text-sm leading-6 text-white/58">All stable modules, capacity tiers, and managed service options.</p>
                </div>
              </div>
            </div>
          </div>

          <div className="mt-14 max-w-6xl">
            <div className="overflow-hidden rounded-[1.25rem] border border-white/10 bg-white/[0.035] shadow-2xl shadow-black/35">
              <img
                src="/images/nexus-banner.png"
                alt="Project NEXUS - time banking and everything your community needs"
                className="aspect-[16/9] w-full object-contain"
              />
            </div>
            <div className="mt-4 grid metric-grid gap-3">
              {highlights.map((item) => {
                const Icon = item.icon;
                return (
                  <div key={item.label} className="rounded-xl border border-white/12 bg-black/24 p-4">
                    <Icon className="mb-3 size-5 text-[#f5c86a]" />
                    <p className="text-xl font-black text-white">{item.value}</p>
                    <p className="text-xs font-semibold text-white/52 uppercase">{item.label}</p>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="mb-9 max-w-3xl">
          <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">Why NEXUS</p>
          <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">A serious alternative to closed community SaaS.</h2>
        </div>
        <div className="grid gap-5 lg:grid-cols-3">
          {pillars.map((pillar) => {
            const Icon = pillar.icon;
            return (
              <Card key={pillar.title} className="min-h-[18rem] border border-white/10 bg-white/[0.055] p-6">
                <Icon className="size-8 text-[#55d6be]" />
                <Card.Header className="px-0">
                  <Card.Title className="text-2xl font-black text-white">{pillar.title}</Card.Title>
                  <Card.Description className="text-base leading-7 text-white/62">{pillar.body}</Card.Description>
                </Card.Header>
              </Card>
            );
          })}
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.035]">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[0.9fr_1.1fr]">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Professional stack</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-4xl">The sales site now points at the same modern product story as the app.</h2>
          </div>
          <div>
            <p className="text-lg leading-8 text-white/66">
              The buyer journey is set up for a future proper ordering flow: product line selection, plan rules, launch services, quote summary, and structured enquiry data.
            </p>
            <div className="mt-6 flex flex-wrap gap-2">
              {stackItems.map((item) => (
                <span key={item} className="rounded-full border border-white/10 bg-black/22 px-3 py-2 text-sm font-bold text-white/72">
                  {item}
                </span>
              ))}
            </div>
            <Button className="mt-7" onPress={() => onNavigate('/hosting')}>
              Open the order workbench
              <ArrowRight className="size-5" />
            </Button>
          </div>
        </div>
      </section>
    </>
  );
}
