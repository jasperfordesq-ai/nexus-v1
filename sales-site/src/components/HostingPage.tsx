// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@heroui/react';
import { ArrowDown, BadgeEuro, Boxes, Scale, ShieldCheck } from 'lucide-react';
import { useCallback } from 'react';

import { hostingPlans } from '../data/pricing';
import QuoteBuilder from './QuoteBuilder';

interface HostingPageProps {
  onNavigate: (href: string) => void;
}

const proofPoints = [
  { label: 'Starting hosted price', value: '€99/mo', icon: BadgeEuro },
  { label: 'Feature gating', value: 'None', icon: Boxes },
  { label: 'Licence', value: 'AGPL', icon: Scale },
  { label: 'Support model', value: 'Tiered', icon: ShieldCheck },
];

export default function HostingPage({ onNavigate }: HostingPageProps) {
  const handleQuoteChange = useCallback(() => undefined, []);

  return (
    <>
      <section className="overflow-hidden border-b border-white/10">
        <div className="mx-auto grid max-w-7xl gap-12 px-5 py-16 lg:grid-cols-[1fr_0.95fr] lg:py-24">
          <div className="flex flex-col justify-center">
            <p className="mb-5 w-fit rounded-full border border-[#55d6be]/30 bg-[#55d6be]/10 px-4 py-2 text-xs font-black tracking-[0.16em] text-[#bffbf2] uppercase">
              Enterprise hosting and order workbench
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.05] tracking-normal text-white sm:text-5xl md:text-7xl">
              Community platform hosting with transparent order estimates.
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
              Build a managed NEXUS hosting estimate for serious civic and community platforms: choose capacity, support, maintenance, launch services, add-ons, and custom delivery, then send a structured order enquiry.
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
                  <p className="text-2xl font-black text-white">All modules included. Capacity changes, features do not.</p>
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
                {hostingPlans.slice(0, 5).map((plan) => (
                  <div key={plan.id} className="grid grid-cols-[1fr_auto] rounded-xl border border-white/10 bg-white/[0.035] p-3 text-sm">
                    <span className="font-bold text-white">{plan.name}</span>
                    <span className="text-white/58">€{plan.monthlyEur.toLocaleString('en-IE')}/mo</span>
                  </div>
                ))}
              </div>
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
