// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card } from '@heroui/react';
import {
  ArrowRight,
  BadgeCheck,
  BookOpen,
  CheckCircle2,
  ExternalLink,
  Globe2,
  HandCoins,
  Layers3,
  Network,
  ShieldCheck,
  Sparkles,
} from 'lucide-react';

import { nexusModuleGroups, nexusModules } from '../data/modules';

interface FeaturesPageProps {
  onNavigate: (href: string) => void;
}

const platformStats = [
  { label: 'Production modules', value: '60+', icon: Layers3 },
  { label: 'API endpoints', value: '100+', icon: Network },
  { label: 'Languages', value: '11', icon: Globe2 },
  { label: 'Licence', value: 'AGPL', icon: ShieldCheck },
];

const pillars = [
  {
    title: 'Timebanking Engine',
    body: 'Full credit exchange system with wallet, transactions, and broker controls. Every hour is worth exactly one credit.',
    icon: HandCoins,
  },
  {
    title: 'Multi-Tenant Platform',
    body: 'Host unlimited communities on a single platform. Each gets branding, configuration, feature toggles, and parent-child hierarchy.',
    icon: Layers3,
  },
  {
    title: 'Global Federation',
    body: 'Connect communities into a network for cross-community exchange, shared listings, events, reputation, and messaging.',
    icon: Network,
  },
];

const moduleGroupDescriptions: Record<string, string> = {
  'Core Platform': 'The foundation: tenant structure, time credits, matching, messaging, mobile delivery, and federation.',
  'Member Experience': 'The everyday tools members use to exchange, participate, organise, volunteer, and build trust.',
  'Content & Communication': 'Publishing, help, knowledge, newsletter, legal, and AI guidance tools for running a living community.',
  'AI & Recommendations': 'Search, matching, ranking, and recommendation systems that make large communities easier to navigate.',
  'Operations & Trust': 'Admin, safety, compliance, security, accessibility, localisation, deployment, and governance controls.',
};

const federationItems = [
  ['Cross-Platform Discovery', 'Find members and services on partner timebanking platforms globally.'],
  ['Interoperable Credit Exchange', 'Trade time credits between different timebanking systems.'],
  ['Federation Neighborhoods', 'Geographically grouped clusters of communities for regional coordination.'],
  ['Credit Agreements', 'Negotiated exchange terms between federated communities.'],
  ['External Federation Partnerships', 'Any timebanking platform worldwide can connect through standardized API endpoints.'],
  ['Federated Reputation', 'Trust and review systems can travel with members across platforms.'],
  ['Open Standard', 'No single platform controls the global network; the protocol belongs to everyone.'],
];

const techStack = [
  'React 19',
  'TypeScript',
  'HeroUI v3',
  'Tailwind CSS 4',
  'Laravel 12',
  'PHP 8.2+',
  'MariaDB 10.11',
  'Redis 7+',
  'Meilisearch',
  'OpenAI Embeddings',
  'Pusher WebSockets',
  'Firebase FCM',
  'Docker',
];

const communities = [
  {
    name: 'hOUR Timebank',
    url: 'https://hour-timebank.ie',
    body: 'Ireland-based production community demonstrating the core member, exchange, and timebanking experience.',
  },
  {
    name: 'Timebank Global',
    url: 'https://timebank.global',
    body: 'International timebanking network and federation hub for connecting communities across borders.',
  },
  {
    name: 'NexusCivic',
    url: 'https://nexuscivic.ie',
    body: 'Civic engagement deployment track for community participation, organisations, and local programmes.',
  },
];

const buyerFeatureMap = [
  {
    buyerQuestion: 'I just need a timebank.',
    communityEdition: 'Yes: offers, requests, time credits, members, groups, simple events, messaging, and admin basics.',
    fullPlatform: 'Adds federation, volunteering programmes, analytics, AI discovery, and multi-community governance.',
  },
  {
    buyerQuestion: 'I need Made Open-style community modules.',
    communityEdition: 'Partly: the entry plan covers the timebanking core, not every civic engagement module.',
    fullPlatform: 'Yes: listings, volunteering, events, groups, resources, blogs, goals, matches, reviews, search, leaderboards, achievements, and help.',
  },
  {
    buyerQuestion: 'I need a network, not one group.',
    communityEdition: 'No: one timebank tenant only, so the entry price stays believable.',
    fullPlatform: 'Yes: multi-tenant hierarchy, tenant domains, federation, shared discovery, and regional or national operating models.',
  },
  {
    buyerQuestion: 'I need custom procurement or integrations.',
    communityEdition: 'Not the right lane: keep it lean or upgrade.',
    fullPlatform: 'Yes: SSO, data migration, compliance packs, custom federation adapters, and managed support options.',
  },
];

export default function FeaturesPage({ onNavigate }: FeaturesPageProps) {
  return (
    <>
      <section className="overflow-hidden border-b border-white/10">
        <div className="mx-auto max-w-7xl px-5 py-16 lg:py-24">
          <div className="max-w-5xl">
            <p className="mb-5 w-fit rounded-full border border-[#55d6be]/30 bg-[#55d6be]/10 px-4 py-2 text-xs font-black tracking-[0.16em] text-[#bffbf2] uppercase">
              Project NEXUS V1.5 features
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
              Everything inside the platform.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
              The full feature catalogue from the original Project NEXUS sales page: timebanking, federation, AI matching, real-time messaging, Stripe payments, identity verification, accessible frontend, multilingual support, mobile foundations, and the operational tooling behind it.
            </p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Button size="lg" onPress={() => document.getElementById('module-catalogue')?.scrollIntoView({ behavior: 'smooth' })}>
                Browse modules
                <ArrowRight className="size-5" />
              </Button>
              <Button size="lg" variant="outline" onPress={() => onNavigate('/hosting')}>
                Compare hosting
              </Button>
            </div>
          </div>

          <div className="mt-12 grid metric-grid gap-3">
            {platformStats.map((stat) => {
              const Icon = stat.icon;
              return (
                <div key={stat.label} className="rounded-xl border border-white/10 bg-white/[0.055] p-5">
                  <Icon className="mb-3 size-5 text-[#f5c86a]" />
                  <p className="text-2xl font-black text-white">{stat.value}</p>
                  <p className="text-xs font-semibold text-white/45 uppercase">{stat.label}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="mb-9 max-w-3xl">
          <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">What is Project NEXUS?</p>
          <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">A complete ecosystem for connected communities.</h2>
          <p className="mt-5 text-base leading-7 text-white/62">
            The platform combines member exchange, civic participation, content, safety, analytics, AI, and network federation in one auditable open-source codebase.
          </p>
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
        <div className="mx-auto max-w-7xl px-5 py-16">
          <div className="mb-10 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Buyer feature map</p>
              <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">Community Edition is not the whole product.</h2>
            </div>
            <p className="text-base leading-8 text-white/64">
              That is the point. The cheaper entry plan is for timebanking basics. The full platform is where the broader community, civic, federation, AI, and operational modules belong.
            </p>
          </div>
          <div className="grid gap-4">
            {buyerFeatureMap.map((row) => (
              <article key={row.buyerQuestion} className="grid gap-4 rounded-2xl border border-white/10 bg-black/18 p-5 lg:grid-cols-[0.7fr_1fr_1fr]">
                <div>
                  <p className="text-xs font-black tracking-[0.16em] text-[#9edbd2] uppercase">Buyer question</p>
                  <h3 className="mt-2 text-xl font-black text-white">{row.buyerQuestion}</h3>
                </div>
                <div className="rounded-xl border border-white/10 bg-white/[0.04] p-4">
                  <p className="mb-2 text-sm font-black text-[#f5c86a]">Community Edition</p>
                  <p className="text-sm leading-6 text-white/58">{row.communityEdition}</p>
                </div>
                <div className="rounded-xl border border-[#55d6be]/20 bg-[#55d6be]/8 p-4">
                  <p className="mb-2 text-sm font-black text-[#bffbf2]">Full platform</p>
                  <p className="text-sm leading-6 text-white/62">{row.fullPlatform}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section id="module-catalogue" className="border-y border-white/10 bg-white/[0.03]">
        <div className="mx-auto max-w-7xl px-5 py-16">
          <div className="mb-10 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
              <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Everything Inside V1.5</p>
              <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">60+ production-ready modules.</h2>
            </div>
            <p className="text-base leading-8 text-white/64">
              All open source. All Dockerized. Full platform hosted plans change capacity, uptime, support, and infrastructure; the entry Community Edition is a narrower timebanking package.
            </p>
          </div>

          <div className="module-category-stack grid gap-5">
            {nexusModuleGroups.map((group) => {
              const modules = nexusModules.filter((module) => module.group === group);

              return (
                <section key={group} className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.045]">
                  <div className="grid gap-4 border-b border-white/10 bg-black/18 p-5 lg:grid-cols-[18rem_1fr] lg:items-center">
                    <div>
                      <p className="text-xs font-black tracking-[0.16em] text-[#f5c86a] uppercase">{modules.length} modules</p>
                      <h3 className="mt-2 text-2xl font-black text-white">{group}</h3>
                    </div>
                    <p className="max-w-4xl text-sm leading-6 text-white/58">
                      {moduleGroupDescriptions[group]}
                    </p>
                  </div>
                  <div className="module-row-list divide-y divide-white/10">
                    {modules.map((module) => (
                      <article key={module.id} className="grid gap-2 px-5 py-4 transition hover:bg-white/[0.035] md:grid-cols-[minmax(12rem,18rem)_1fr] md:gap-6 md:py-5">
                        <div className="flex items-start gap-3">
                          <CheckCircle2 className="mt-1 size-4 shrink-0 text-[#55d6be]" />
                          <p className="font-bold text-white">{module.name}</p>
                        </div>
                        <p className="text-sm leading-6 text-white/58">{module.description}</p>
                      </article>
                    ))}
                  </div>
                </section>
              );
            })}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="glass-panel rounded-[1.25rem] p-6 md:p-8">
          <p className="mb-3 flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">
            <Network className="size-4" />
            Live in V1.5
          </p>
          <h2 className="max-w-4xl text-3xl font-black text-white md:text-5xl">Global Federation: connecting timebanks worldwide.</h2>
          <p className="mt-5 max-w-5xl text-base leading-8 text-white/64">
            V1.5 ships with a working Federation API, native protocol adapters for Nexus, Komunitin, Credit Commons / CEN, and TimeOverflow, two-way sync across nine entity types, event-driven push, reputation portability, and integration test coverage.
          </p>
          <div className="mt-8 grid comparison-grid gap-4">
            {federationItems.map(([title, body]) => (
              <div key={title} className="rounded-xl border border-white/10 bg-black/18 p-4">
                <p className="font-black text-white">{title}</p>
                <p className="mt-2 text-sm leading-6 text-white/56">{body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="border-y border-white/10 bg-white/[0.035]">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-16 lg:grid-cols-[0.82fr_1.18fr]">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Live communities</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-5xl">Real deployments, not just roadmap slides.</h2>
          </div>
          <div className="grid gap-4">
            {communities.map((community) => (
              <a key={community.url} href={community.url} target="_blank" rel="noopener noreferrer" className="glass-panel rounded-2xl p-5 transition hover:border-[#55d6be]/60">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-xl font-black text-white">{community.name}</p>
                    <p className="mt-2 text-sm leading-6 text-white/58">{community.body}</p>
                  </div>
                  <ExternalLink className="size-4 shrink-0 text-[#55d6be]" />
                </div>
              </a>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-5 py-16">
        <div className="grid gap-8 lg:grid-cols-[1fr_1fr]">
          <div className="glass-panel rounded-2xl p-6">
            <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">Modern tech stack</p>
            <h2 className="mt-3 text-3xl font-black text-white">Production-ready from day one.</h2>
            <div className="mt-6 flex flex-wrap gap-2">
              {techStack.map((tech) => (
                <span key={tech} className="rounded-full border border-white/10 bg-black/22 px-3 py-2 text-sm font-bold text-white/72">
                  {tech}
                </span>
              ))}
            </div>
          </div>

          <div className="glass-panel rounded-2xl p-6">
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Why open source?</p>
            <h2 className="mt-3 text-3xl font-black text-white">Free forever, transparent, auditable.</h2>
            <div className="mt-5 grid gap-4">
              <Value title="Free Forever" body="No vendor lock-in and no surprise feature ransom. AGPL-3.0 keeps the platform open." icon={Sparkles} />
              <Value title="Transparent & Auditable" body="Every line of code is visible so communities can verify security and data handling." icon={BookOpen} />
              <Value title="Community-Driven" body="Roadmap, features, bug fixes, and federation protocol work can be shaped in public." icon={BadgeCheck} />
            </div>
          </div>
        </div>
      </section>

      <section className="border-t border-white/10 bg-white/[0.035]">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <p className="text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">Get started today</p>
            <h2 className="mt-3 text-3xl font-black text-white">Clone it, deploy it, improve it, or let us host it.</h2>
            <p className="mt-4 max-w-3xl text-base leading-7 text-white/62">
              The source is public, the managed hosting offer is transparent, and the feature catalogue is here to inspect before any procurement conversation.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Button size="lg" onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}>
              View repository
            </Button>
            <Button size="lg" variant="outline" onPress={() => onNavigate('/hosting')}>
              Compare hosting
            </Button>
          </div>
        </div>
      </section>
    </>
  );
}

function Value({
  title,
  body,
  icon: Icon,
}: {
  title: string;
  body: string;
  icon: typeof Sparkles;
}) {
  return (
    <div className="grid grid-cols-[auto_1fr] gap-3">
      <Icon className="mt-1 size-5 text-[#55d6be]" />
      <div>
        <p className="font-black text-white">{title}</p>
        <p className="mt-1 text-sm leading-6 text-white/56">{body}</p>
      </div>
    </div>
  );
}
