// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card } from '@heroui/react';
import { ArrowRight, BadgeCheck, Globe2, HandCoins, Network, ShieldCheck, Sparkles } from 'lucide-react';

interface HomePageProps {
  onNavigate: (href: string) => void;
}

const highlights = [
  { label: '60+ modules', value: 'included', icon: Sparkles },
  { label: '11 languages', value: 'RTL ready', icon: Globe2 },
  { label: 'Federation', value: '4 protocols', icon: Network },
  { label: 'Licence', value: 'AGPL-3.0', icon: ShieldCheck },
];

const pillars = [
  {
    title: 'Timebanking at the centre',
    body: 'A real exchange engine with wallets, broker controls, volunteering, group exchanges, reviews, and equal-time economics.',
    icon: HandCoins,
  },
  {
    title: 'Enterprise operations without lock-in',
    body: 'Managed hosting, support, maintenance, migration, compliance packs, and custom development on top of open-source software.',
    icon: BadgeCheck,
  },
  {
    title: 'Built for networks',
    body: 'Multi-tenant hierarchies, federation, tenant domains, accessible frontend delivery, and the ability to connect beyond one platform.',
    icon: Network,
  },
];

export default function HomePage({ onNavigate }: HomePageProps) {
  return (
    <>
      <section className="border-b border-white/10">
        <div className="mx-auto flex w-full max-w-7xl flex-col px-5 py-16 lg:py-24">
          <div className="max-w-4xl">
            <p className="mb-5 w-fit rounded-full border border-white/14 bg-white/6 px-4 py-2 text-xs font-bold tracking-[0.16em] text-[#9edbd2] uppercase">
              Open source community infrastructure
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
              Timebanking, community, and civic operations in one platform.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
              Project NEXUS is an AGPL-licensed community platform with time credits, federation, AI matching, real-time messaging, volunteering, governance, and managed hosting that buyers can actually compare.
            </p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Button size="lg" variant="outline" onPress={() => onNavigate('/features')}>
                Explore features
              </Button>
              <Button size="lg" onPress={() => onNavigate('/hosting')}>
                Compare hosting
                <ArrowRight className="size-5" />
              </Button>
              <Button size="lg" variant="outline" onPress={() => window.location.assign('https://hour-timebank.ie')}>
                See live platform
              </Button>
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
            <p className="text-sm font-bold tracking-[0.16em] text-[#f5c86a] uppercase">Transparent hosting</p>
            <h2 className="mt-3 text-3xl font-black text-white md:text-4xl">Built to be cheaper than the civic enterprise incumbents.</h2>
          </div>
          <div className="text-lg leading-8 text-white/66">
            <p>
              Competitors often hide pricing, gate features, or price every implementation as a bespoke project. NEXUS keeps the code open and charges for the work customers actually need: hosting, uptime, support, maintenance, migration, compliance, and custom development.
            </p>
            <Button className="mt-7" onPress={() => onNavigate('/hosting')}>
              Open the comparison workbench
              <ArrowRight className="size-5" />
            </Button>
          </div>
        </div>
      </section>
    </>
  );
}
