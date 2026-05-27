// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, NumberField } from '@heroui/react';
import { Calculator, CheckCircle2, Minus, Plus, Server } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
  maintenancePlans,
  onboardingPackages,
  oneOffServices,
  recurringAddOns,
  supportTiers,
  type BillingCycle,
} from '../data/pricing';
import { estimateQuote, formatCurrency, type QuoteEstimate, type QuoteInput } from '../lib/pricingEngine';
import OrderForm from './OrderForm';

interface QuoteBuilderProps {
  onQuoteChange: (quote: QuoteEstimate) => void;
}

const defaultInput: QuoteInput = {
  activeMembers: 1000,
  billingCycle: 'annual',
  supportTierId: 'standard',
  maintenancePlanId: 'track-latest',
  onboardingPackageId: 'quick-start',
  addOns: {
    'compliance-pack': 0,
    'dedicated-staging': 0,
    'extra-storage-100gb': 0,
  },
  oneOffServices: {
    'branding-theme-pack': 0,
    'data-migration': 0,
    'mobile-app-store-submission': 0,
    'federation-onboarding': 0,
  },
};

const capacityPresets = [
  { label: 'Pilot', members: 100, detail: 'Small launch or proof of need' },
  { label: 'Local community', members: 1000, detail: 'Established timebank or programme' },
  { label: 'Regional network', members: 10000, detail: 'County, city, or multi-programme network' },
  { label: 'Large network', members: 30000, detail: 'Public-sector or national programme' },
  { label: 'Federation', members: 100000, detail: 'Multi-platform or national federation' },
];

const supportChoices = [
  {
    id: 'standard',
    title: 'Included support',
    plainEnglish: 'Best when you have someone technical who can wait for normal response times.',
  },
  {
    id: 'priority',
    title: 'Priority helpdesk',
    plainEnglish: 'A better fit when staff need quicker answers during business hours.',
  },
  {
    id: 'managed',
    title: 'Managed operations',
    plainEnglish: 'For organisations that want a named technical lead and regular operational reviews.',
  },
  {
    id: 'mission-critical',
    title: 'Critical incident cover',
    plainEnglish: 'For high-stakes services that need agreed escalation windows and incident follow-up.',
  },
];

const maintenanceChoices = [
  {
    id: 'track-latest',
    title: 'Stay current',
    plainEnglish: 'We keep you on the latest stable NEXUS release each quarter.',
  },
  {
    id: 'pinned-release',
    title: 'Hold a stable release',
    plainEnglish: 'Use this when procurement or training needs a predictable version for up to 12 months.',
  },
  {
    id: 'custom-fork',
    title: 'Maintain a custom fork',
    plainEnglish: 'For customers with bespoke changes that need regular upstream compatibility work.',
  },
  {
    id: 'lts-lock',
    title: 'Long-term version lock',
    plainEnglish: 'For organisations that need a version held for two years with security patches only.',
  },
];

const launchChoices = [
  {
    id: 'none',
    title: 'Provision only',
    plainEnglish: 'We create the tenant and give you the DNS checklist. You handle setup.',
  },
  {
    id: 'quick-start',
    title: 'Quick launch',
    plainEnglish: 'Good for one community that needs branding, admin training, and a guided go-live.',
  },
  {
    id: 'standard-launch',
    title: 'Standard rollout',
    plainEnglish: 'For a small network that needs imports, training, and post-launch help.',
  },
  {
    id: 'enterprise-launch',
    title: 'Enterprise rollout',
    plainEnglish: 'For larger programmes with discovery, migration, communications, soft launch, and go-live.',
  },
];

export default function QuoteBuilder({ onQuoteChange }: QuoteBuilderProps) {
  const [input, setInput] = useState<QuoteInput>(defaultInput);
  const quote = useMemo(() => estimateQuote(input), [input]);

  useEffect(() => {
    onQuoteChange(quote);
  }, [onQuoteChange, quote]);

  return (
    <section id="quote-builder" className="border-y border-white/10 bg-white/[0.035]">
      <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 xl:grid-cols-[0.88fr_1.12fr]">
        <div>
          <div className="sticky top-24">
            <p className="mb-3 flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">
              <Calculator className="size-4" />
              Quote builder
            </p>
            <h2 className="text-3xl font-black text-white md:text-5xl">Build a real managed hosting order.</h2>
            <p className="mt-5 max-w-xl text-base leading-7 text-white/64">
              This estimates the hosted service, support, maintenance, launch work, and selected add-ons. It is deliberately built around capacity and after-sales delivery, not artificial feature gates.
            </p>

            <Card className="mt-8 border border-white/10 bg-white/[0.06] p-5">
              <p className="text-sm font-bold text-white/58 uppercase">Recommended plan</p>
              <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                  <p className="text-4xl font-black text-white">{quote.hostingPlan.name}</p>
                  <p className="mt-1 text-sm text-white/58">{quote.hostingPlan.activeMemberLabel}</p>
                </div>
                <div className="text-right">
                  <p className="text-3xl font-black text-[#55d6be]">{formatCurrency(quote.monthlyRecurring)}</p>
                  <p className="text-xs font-semibold text-white/45 uppercase">monthly recurring</p>
                </div>
              </div>
              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <Metric label="Annual recurring" value={formatCurrency(quote.annualRecurring)} />
                <Metric label="Annual saving" value={formatCurrency(quote.annualSavings)} />
                <Metric label="One-off" value={formatCurrency(quote.oneOffTotal)} />
              </div>
              <div className="mt-5 rounded-xl border border-[#55d6be]/20 bg-[#55d6be]/8 p-4">
                <p className="flex items-center gap-2 text-sm font-bold text-[#bffbf2]">
                  <Server className="size-4" />
                  All stable modules are included on every hosted tier.
                </p>
                <p className="mt-2 text-sm leading-6 text-white/58">
                  The estimate changes with capacity and service level, not with artificial feature gates.
                </p>
              </div>
            </Card>
          </div>
        </div>

        <div className="space-y-5">
          <Card className="border border-white/10 bg-white/[0.055] p-5">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <h3 className="text-xl font-black text-white">1. How many active members do you expect?</h3>
                <p className="text-sm leading-6 text-white/55">
                  Pick a starting point, then adjust the number. Active members means people who sign in during a typical 90-day period.
                </p>
              </div>
              <span className="rounded-full border border-white/12 bg-black/20 px-4 py-2 text-sm font-black text-white">
                {input.activeMembers.toLocaleString('en-IE')} active members
              </span>
            </div>

            <div className="mt-5 grid gap-3 md:grid-cols-5">
              {capacityPresets.map((preset) => (
                <CapacityPreset
                  key={preset.label}
                  active={input.activeMembers === preset.members}
                  detail={preset.detail}
                  label={preset.label}
                  members={preset.members}
                  onPress={() => setInput((value) => ({ ...value, activeMembers: preset.members }))}
                />
              ))}
            </div>

            <div className="mt-5 grid gap-3 rounded-2xl border border-white/10 bg-black/18 p-4 md:grid-cols-[1fr_auto] md:items-end">
              <div>
                <p className="text-sm font-bold text-white">Use your own estimate</p>
                <p className="mt-1 text-sm leading-6 text-white/52">Type a number or use the stepper for fine control.</p>
              </div>
              <NumberField
                aria-label="Expected active members"
                className="w-full md:w-64"
                formatOptions={{ maximumFractionDigits: 0 }}
                minValue={50}
                maxValue={250000}
                step={250}
                value={input.activeMembers}
                onChange={(nextValue) => setInput((value) => ({ ...value, activeMembers: Math.max(50, Number(nextValue) || 50) }))}
              >
                <NumberField.Group>
                  <NumberField.DecrementButton />
                  <NumberField.Input />
                  <NumberField.IncrementButton />
                </NumberField.Group>
              </NumberField>
            </div>
          </Card>

          <Card className="border border-white/10 bg-white/[0.055] p-5">
            <h3 className="text-xl font-black text-white">2. How would you like to buy it?</h3>
            <p className="mt-1 text-sm leading-6 text-white/55">Annual billing is usually the cleanest procurement route and includes two months free.</p>
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <BillingButton
                active={input.billingCycle === 'annual'}
                label="Annual"
                detail="Two months free"
                onPress={() => updateBilling(setInput, 'annual')}
              />
              <BillingButton
                active={input.billingCycle === 'monthly'}
                label="Monthly"
                detail="No prepay discount"
                onPress={() => updateBilling(setInput, 'monthly')}
              />
            </div>
          </Card>

          <ChoiceCardSection
            title="3. What support do you want us to provide?"
            description="Support changes how quickly and closely we help after launch."
            choices={supportChoices}
            options={supportTiers}
            cadence="monthly"
            selectedId={input.supportTierId}
            onSelect={(supportTierId) => setInput((value) => ({ ...value, supportTierId }))}
          />

          <ChoiceCardSection
            title="4. How should upgrades be handled?"
            description="Choose whether you want to stay current, hold a release, or maintain a bespoke fork."
            choices={maintenanceChoices}
            options={maintenancePlans}
            cadence="monthly"
            selectedId={input.maintenancePlanId}
            onSelect={(maintenancePlanId) => setInput((value) => ({ ...value, maintenancePlanId }))}
          />

          <ChoiceCardSection
            title="5. How much launch help do you need?"
            description="This covers setup, migration, training, and go-live support before the service opens."
            choices={launchChoices}
            options={onboardingPackages}
            cadence="one-off"
            selectedId={input.onboardingPackageId}
            onSelect={(onboardingPackageId) => setInput((value) => ({ ...value, onboardingPackageId }))}
          />

          <OptionCounterSection
            title="Recurring add-ons"
            options={recurringAddOns.filter((item) => ['extra-storage-100gb', 'dedicated-staging', 'compliance-pack', 'additional-sub-tenant', 'extra-email-250k', 'bring-your-own-keys'].includes(item.id))}
            selected={input.addOns}
            cadence="monthly"
            onChange={(id, quantity) => setInput((value) => ({ ...value, addOns: { ...value.addOns, [id]: quantity } }))}
          />

          <OptionCounterSection
            title="Launch and custom services"
            options={oneOffServices.filter((item) => ['branding-theme-pack', 'data-migration', 'mobile-app-store-submission', 'federation-onboarding', 'custom-federation-adapter', 'sso-saml'].includes(item.id))}
            selected={input.oneOffServices}
            cadence="one-off"
            onChange={(id, quantity) =>
              setInput((value) => ({ ...value, oneOffServices: { ...value.oneOffServices, [id]: quantity } }))
            }
          />

          <OrderForm quote={quote} />
        </div>
      </div>
    </section>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-white/10 bg-black/20 p-3">
      <p className="text-lg font-black text-white">{value}</p>
      <p className="text-xs font-semibold text-white/45 uppercase">{label}</p>
    </div>
  );
}

function BillingButton({ active, label, detail, onPress }: { active: boolean; label: string; detail: string; onPress: () => void }) {
  return (
    <Button variant={active ? 'primary' : 'outline'} fullWidth onPress={onPress}>
      <CheckCircle2 className="size-4" />
      <span className="flex flex-col items-start">
        <span>{label}</span>
        <span className="text-xs opacity-70">{detail}</span>
      </span>
    </Button>
  );
}

function CapacityPreset({
  active,
  label,
  detail,
  members,
  onPress,
}: {
  active: boolean;
  label: string;
  detail: string;
  members: number;
  onPress: () => void;
}) {
  return (
    <button
      type="button"
      className={`rounded-2xl border p-4 text-left transition ${
        active ? 'border-[#55d6be] bg-[#55d6be]/12 shadow-lg shadow-[#55d6be]/10' : 'border-white/10 bg-black/18 hover:border-white/24'
      }`}
      onClick={onPress}
    >
      <span className="block text-sm font-black text-white">{label}</span>
      <span className="mt-1 block text-2xl font-black text-[#f5c86a]">{members.toLocaleString('en-IE')}</span>
      <span className="mt-2 block text-xs leading-5 text-white/52">{detail}</span>
    </button>
  );
}

function ChoiceCardSection({
  title,
  description,
  choices,
  options,
  cadence,
  selectedId,
  onSelect,
}: {
  title: string;
  description: string;
  choices: { id: string; title: string; plainEnglish: string }[];
  options: { id: string; label: string; description: string; monthlyEur?: number; fixedEur?: number }[];
  cadence: 'monthly' | 'one-off';
  selectedId: string;
  onSelect: (id: string) => void;
}) {
  return (
    <Card className="border border-white/10 bg-white/[0.055] p-5">
      <h3 className="text-xl font-black text-white">{title}</h3>
      <p className="mt-1 text-sm leading-6 text-white/55">{description}</p>
      <div className="mt-5 grid gap-3">
        {choices.map((choice) => {
          const option = options.find((item) => item.id === choice.id);
          const amount = cadence === 'monthly' ? option?.monthlyEur ?? 0 : option?.fixedEur ?? 0;
          const selected = selectedId === choice.id;

          return (
            <button
              key={choice.id}
              type="button"
              className={`grid gap-4 rounded-2xl border p-4 text-left transition md:grid-cols-[1fr_auto] md:items-center ${
                selected ? 'border-[#55d6be] bg-[#55d6be]/12 shadow-lg shadow-[#55d6be]/10' : 'border-white/10 bg-black/18 hover:border-white/24'
              }`}
              onClick={() => onSelect(choice.id)}
            >
              <span className="flex gap-3">
                <span
                  className={`mt-1 grid size-5 shrink-0 place-items-center rounded-full border ${
                    selected ? 'border-[#55d6be] bg-[#55d6be] text-black' : 'border-white/20 text-transparent'
                  }`}
                >
                  <CheckCircle2 className="size-3.5" />
                </span>
                <span>
                  <span className="block font-black text-white">{choice.title}</span>
                  <span className="mt-1 block text-sm leading-6 text-white/58">{choice.plainEnglish}</span>
                  {option ? <span className="mt-2 block text-xs leading-5 text-white/40">{option.description}</span> : null}
                </span>
              </span>
              <span className="rounded-full border border-white/10 bg-black/24 px-3 py-2 text-sm font-black text-[#f5c86a]">
                {amount === 0 ? 'Included' : `${formatCurrency(amount)}${cadence === 'monthly' ? '/mo' : ''}`}
              </span>
            </button>
          );
        })}
      </div>
    </Card>
  );
}

function OptionCounterSection({
  title,
  options,
  selected,
  cadence,
  onChange,
}: {
  title: string;
  options: { id: string; label: string; description: string; monthlyEur?: number; fixedEur?: number }[];
  selected: Record<string, number>;
  cadence: 'monthly' | 'one-off';
  onChange: (id: string, quantity: number) => void;
}) {
  return (
    <section className="glass-panel rounded-2xl p-5">
      <h3 className="text-xl font-black text-white">{title}</h3>
      <div className="mt-5 grid gap-3">
        {options.map((option) => {
          const quantity = selected[option.id] ?? 0;
          const amount = cadence === 'monthly' ? option.monthlyEur ?? 0 : option.fixedEur ?? 0;

          return (
            <div key={option.id} className="grid gap-4 rounded-xl border border-white/10 bg-black/18 p-4 md:grid-cols-[1fr_auto] md:items-center">
              <div>
                <p className="font-bold text-white">{option.label}</p>
                <p className="mt-1 text-sm leading-6 text-white/55">{option.description}</p>
                <p className="mt-2 text-sm font-bold text-[#f5c86a]">
                  {formatCurrency(amount)}
                  {cadence === 'monthly' ? '/mo' : ''}
                </p>
              </div>
              <div className="flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/5 p-1">
                <Button isIconOnly size="sm" variant="ghost" onPress={() => onChange(option.id, Math.max(0, quantity - 1))}>
                  <Minus className="size-4" />
                </Button>
                <span className="w-8 text-center text-sm font-black text-white">{quantity}</span>
                <Button isIconOnly size="sm" variant="ghost" onPress={() => onChange(option.id, quantity + 1)}>
                  <Plus className="size-4" />
                </Button>
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}

function updateBilling(setInput: React.Dispatch<React.SetStateAction<QuoteInput>>, billingCycle: BillingCycle) {
  setInput((value) => ({ ...value, billingCycle }));
}
