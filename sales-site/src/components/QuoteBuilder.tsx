// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Chip, NumberField } from '@heroui/react';
import { Calculator, CheckCircle2, LockKeyhole, Minus, Plus, Server, Sparkles } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
  communityOnboardingPackages,
  communityTimebankPlans,
  maintenancePlans,
  onboardingPackages,
  oneOffServices,
  recurringAddOns,
  supportTiers,
  type BillingCycle,
  type CommunityTimebankPlan,
  type ProductLine,
} from '../data/pricing';
import { estimateQuote, formatCurrency, formatQuoteAmount, type QuoteEstimate, type QuoteInput } from '../lib/pricingEngine';
import OrderForm from './OrderForm';

interface QuoteBuilderProps {
  onQuoteChange: (quote: QuoteEstimate) => void;
}

const defaultInput: QuoteInput = {
  productLine: 'community-timebanking',
  activeMembers: 150,
  billingCycle: 'annual',
  communityPlanId: 'community-edition',
  supportTierId: 'standard',
  maintenancePlanId: 'track-latest',
  onboardingPackageId: 'community-assisted-launch',
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
  { label: 'Pilot', members: 100, displayValue: '100', memberKind: 'active members', detail: 'Small launch or proof of need' },
  { label: 'Local', members: 1000, displayValue: '1k', memberKind: 'active members', detail: 'Established timebank or programme' },
  { label: 'Regional', members: 10000, displayValue: '10k', memberKind: 'active members', detail: 'County, city, or multi-programme network' },
  { label: 'Large', members: 30000, displayValue: '30k', memberKind: 'active members', detail: 'Public-sector or multi-programme network' },
  { label: 'Network', members: 100000, displayValue: 'Up to 100k', memberKind: 'published maximum', detail: 'Largest fixed public tier' },
  { label: 'Enterprise', members: 100001, displayValue: '>100k', memberKind: 'custom pricing', detail: 'Anything over the public cap' },
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

const communityLaunchChoices = [
  {
    id: 'community-self-start',
    title: 'Self-start',
    plainEnglish: 'Lowest cost. We provision the timebank and you configure content.',
  },
  {
    id: 'community-assisted-launch',
    title: 'Assisted launch',
    plainEnglish: 'Best for most new groups: one clinic, branding setup, and a clean launch checklist.',
  },
  {
    id: 'community-import-launch',
    title: 'Import launch',
    plainEnglish: 'Use this when you already have members or starter content to migrate.',
  },
];

export default function QuoteBuilder({ onQuoteChange }: QuoteBuilderProps) {
  const [input, setInput] = useState<QuoteInput>(defaultInput);
  const quote = useMemo(() => estimateQuote(input), [input]);
  const isCommunity = quote.productLine === 'community-timebanking';
  const isCustomQuote = quote.pricingMode === 'custom';
  const communityPlan = isCommunityPlan(quote.hostingPlan) ? quote.hostingPlan : communityTimebankPlans[0];

  useEffect(() => {
    onQuoteChange(quote);
  }, [onQuoteChange, quote]);

  return (
    <section id="quote-builder" className="border-y border-white/10 bg-white/[0.035]">
      <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 xl:grid-cols-[0.88fr_1.12fr]">
        <div>
          <div className="sticky top-24">
            <p className="mb-3 flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">
              <Calculator className="size-4" />
              Quote builder
            </p>
            <h2 className="text-3xl font-black text-white md:text-5xl">Choose the right commercial lane.</h2>
            <p className="mt-5 max-w-xl text-base leading-7 text-white/64">
              The entry offer is deliberately cheaper and deliberately narrower. Full platform hosting still prices by capacity, operations, support, and launch work.
            </p>

            <Card className="mt-8 border border-white/10 bg-white/[0.06] p-5">
              <div className="flex flex-wrap items-center gap-2">
                <Chip color={isCommunity ? 'success' : 'accent'} variant="soft">
                  {quote.productLineLabel}
                </Chip>
                <Chip color="warning" variant="soft">
                  {quote.billingCycle === 'annual' ? 'Annual billing' : 'Monthly billing'}
                </Chip>
              </div>
              <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                  <p className="text-4xl font-black text-white">{quote.hostingPlan.name}</p>
                  <p className="mt-1 text-sm text-white/58">{quote.hostingPlan.activeMemberLabel}</p>
                </div>
                <div className="text-right">
                  <p className="whitespace-nowrap text-2xl font-black text-[var(--color-accent)] md:text-3xl">
                    {formatQuoteAmount(quote, quote.monthlyRecurring)}
                  </p>
                  <p className="text-xs font-semibold text-white/45 uppercase">monthly recurring</p>
                </div>
              </div>
              <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <Metric label="Annual recurring" value={formatQuoteAmount(quote, quote.annualRecurring)} />
                <Metric label="Annual saving" value={isCustomQuote ? 'Discovery' : formatCurrency(quote.annualSavings)} />
                <Metric label="One-off" value={formatQuoteAmount(quote, quote.oneOffTotal)} />
              </div>
              <div className="mt-5 rounded-xl border border-[color:var(--color-accent)]/20 bg-[color:var(--color-accent)]/8 p-4">
                <p className="flex items-center gap-2 text-sm font-bold text-[var(--color-accent)]">
                  {isCommunity ? <LockKeyhole className="size-4" /> : <Server className="size-4" />}
                  {isCommunity
                    ? 'Feature-limited on purpose.'
                    : isCustomQuote
                      ? 'Enterprise scale needs discovery before pricing.'
                      : 'All stable modules are included on full platform hosting.'}
                </p>
                <p className="mt-2 text-sm leading-6 text-white/58">
                  {isCommunity
                    ? 'Community Timebanking is the credible low-price option: core timebanking stays on, expensive platform features stay off.'
                    : isCustomQuote
                      ? 'Published pricing stops at 100,000 active members. Above that, traffic, storage, email, SLA, migration, and tenancy design need a bespoke quote.'
                      : 'The estimate changes with capacity and service level, not with artificial feature gates.'}
                </p>
              </div>
            </Card>
          </div>
        </div>

        <div className="space-y-5">
          <Card className="border border-white/10 bg-white/[0.055] p-5">
            <h3 className="text-xl font-black text-white">1. What are you buying?</h3>
            <p className="mt-1 text-sm leading-6 text-white/55">
              Pick the lean timebanking offer for affordability, or the full platform when you need the whole NEXUS stack.
            </p>
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <ProductLineButton
                active={input.productLine === 'community-timebanking'}
                title="Community Timebanking"
                price="from EUR29/mo"
                detail="Core timebanking only, built for small communities that need a lower-cost managed start."
                onPress={() => switchProductLine(setInput, 'community-timebanking')}
              />
              <ProductLineButton
                active={input.productLine === 'full-platform'}
                title="Full Platform Hosting"
                price="from EUR99/mo"
                detail="All stable NEXUS modules with published tiers up to 100k active members and custom enterprise pricing above that."
                onPress={() => switchProductLine(setInput, 'full-platform')}
              />
            </div>
          </Card>

          {isCommunity ? (
            <>
              <Card className="border border-white/10 bg-white/[0.055] p-5">
                <h3 className="text-xl font-black text-white">2. Pick the Community Timebanking plan.</h3>
                <p className="mt-1 text-sm leading-6 text-white/55">
                  The entry tier is cheaper because the feature set is intentionally smaller.
                </p>
                <div className="mt-5 grid gap-4 lg:grid-cols-3">
                  {communityTimebankPlans.map((plan) => (
                    <CommunityPlanCard
                      key={plan.id}
                      plan={plan}
                      selected={input.communityPlanId === plan.id}
                      onPress={() =>
                        setInput((value) => ({
                          ...value,
                          activeMembers: plan.activeMemberLimit ?? value.activeMembers,
                          communityPlanId: plan.id,
                        }))
                      }
                    />
                  ))}
                </div>
              </Card>

              <Card className="border border-white/10 bg-white/[0.055] p-5">
                <h3 className="text-xl font-black text-white">3. What stays on and what stays off?</h3>
                <div className="mt-5 grid gap-4 lg:grid-cols-2">
                  <FeatureList title="Included" items={communityPlan.included} tone="include" />
                  <FeatureList title="Held back until upgrade" items={communityPlan.heldBack} tone="held-back" />
                </div>
                <div className="mt-5 rounded-xl border border-white/10 bg-black/18 p-4">
                  <p className="text-sm font-black text-white">Fair-use guardrails</p>
                  <div className="mt-3 flex flex-wrap gap-2">
                    {communityPlan.fairUse.map((item) => (
                      <Chip key={item} variant="soft">
                        {item}
                      </Chip>
                    ))}
                  </div>
                  <p className="mt-4 text-sm leading-6 text-white/58">{communityPlan.upgradeTrigger}</p>
                </div>
              </Card>

              <Card className="border border-white/10 bg-white/[0.055] p-5">
                <h3 className="text-xl font-black text-white">4. How would you like to buy it?</h3>
                <p className="mt-1 text-sm leading-6 text-white/55">
                  Annual billing makes the entry price visibly sharper without making the product feel throwaway.
                </p>
                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                  <BillingButton
                    active={input.billingCycle === 'annual'}
                    label="Annual"
                    detail={`${formatCurrency(communityPlan.annualEur)} per year`}
                    onPress={() => updateBilling(setInput, 'annual')}
                  />
                  <BillingButton
                    active={input.billingCycle === 'monthly'}
                    label="Monthly"
                    detail={`${formatCurrency(communityPlan.monthlyEur)} per month`}
                    onPress={() => updateBilling(setInput, 'monthly')}
                  />
                </div>
              </Card>

              <ChoiceCardSection
                title="5. How much launch help do you need?"
                description="Keep it self-start if price is everything, or add a small launch clinic so the first experience feels professional."
                choices={communityLaunchChoices}
                options={communityOnboardingPackages}
                cadence="one-off"
                selectedId={input.onboardingPackageId}
                onSelect={(onboardingPackageId) => setInput((value) => ({ ...value, onboardingPackageId }))}
              />

              <OrderForm quote={quote} />
            </>
          ) : (
            <>
              <Card className="border border-white/10 bg-white/[0.055] p-5">
                <div className="flex flex-wrap items-center justify-between gap-4">
                  <div>
                    <h3 className="text-xl font-black text-white">2. How many active members do you expect?</h3>
                    <p className="text-sm leading-6 text-white/55">
                      Active members means people who sign in during a typical 90-day period.
                    </p>
                  </div>
                  <span className="rounded-full border border-white/12 bg-black/20 px-4 py-2 text-sm font-black text-white">
                    {formatActiveMemberLabel(input.activeMembers)}
                  </span>
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {capacityPresets.map((preset) => (
                    <CapacityPreset
                      key={preset.label}
                      active={input.activeMembers === preset.members}
                      detail={preset.detail}
                      displayValue={preset.displayValue}
                      label={preset.label}
                      memberKind={preset.memberKind}
                      members={preset.members}
                      onPress={() => setInput((value) => ({ ...value, activeMembers: preset.members }))}
                    />
                  ))}
                </div>

                <div className="mt-5 grid gap-3 rounded-2xl border border-white/10 bg-black/18 p-4 md:grid-cols-[1fr_auto] md:items-end">
                  <div>
                    <p className="text-sm font-bold text-white">Use your own estimate</p>
                    <p className="mt-1 text-sm leading-6 text-white/52">
                      Published pricing is capped at 100,000 active members. Larger or unusually busy networks move to enterprise discovery.
                    </p>
                  </div>
                  <NumberField
                    aria-label="Expected active members"
                    className="w-full md:w-64"
                    formatOptions={{ maximumFractionDigits: 0 }}
                    minValue={50}
                    maxValue={1000000}
                    step={1000}
                    value={input.activeMembers}
                    onChange={(nextValue) =>
                      setInput((value) => ({ ...value, activeMembers: Math.max(50, Number(nextValue) || 50) }))
                    }
                  >
                    <NumberField.Group>
                      <NumberField.DecrementButton />
                      <NumberField.Input />
                      <NumberField.IncrementButton />
                    </NumberField.Group>
                  </NumberField>
                </div>
              </Card>

              {isCustomQuote ? (
                <EnterpriseCustomSection />
              ) : (
                <>
                  <Card className="border border-white/10 bg-white/[0.055] p-5">
                    <h3 className="text-xl font-black text-white">3. How would you like to buy it?</h3>
                    <p className="mt-1 text-sm leading-6 text-white/55">Annual billing is usually the cleanest procurement route and includes two months free.</p>
                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                      <BillingButton active={input.billingCycle === 'annual'} label="Annual" detail="Two months free" onPress={() => updateBilling(setInput, 'annual')} />
                      <BillingButton active={input.billingCycle === 'monthly'} label="Monthly" detail="No prepay discount" onPress={() => updateBilling(setInput, 'monthly')} />
                    </div>
                  </Card>

                  <ChoiceCardSection
                    title="4. What support do you want us to provide?"
                    description="Support changes how quickly and closely we help after launch."
                    choices={supportChoices}
                    options={supportTiers}
                    cadence="monthly"
                    selectedId={input.supportTierId}
                    onSelect={(supportTierId) => setInput((value) => ({ ...value, supportTierId }))}
                  />

                  <ChoiceCardSection
                    title="5. How should upgrades be handled?"
                    description="Choose whether you want to stay current, hold a release, or maintain a bespoke fork."
                    choices={maintenanceChoices}
                    options={maintenancePlans}
                    cadence="monthly"
                    selectedId={input.maintenancePlanId}
                    onSelect={(maintenancePlanId) => setInput((value) => ({ ...value, maintenancePlanId }))}
                  />

                  <ChoiceCardSection
                    title="6. How much launch help do you need?"
                    description="This covers setup, migration, training, and go-live support before the service opens."
                    choices={launchChoices}
                    options={onboardingPackages}
                    cadence="one-off"
                    selectedId={input.onboardingPackageId}
                    onSelect={(onboardingPackageId) => setInput((value) => ({ ...value, onboardingPackageId }))}
                  />

                  <OptionCounterSection
                    title="Recurring add-ons"
                    options={recurringAddOns.filter((item) =>
                      ['extra-storage-100gb', 'dedicated-staging', 'compliance-pack', 'additional-sub-tenant', 'extra-email-250k', 'bring-your-own-keys'].includes(item.id),
                    )}
                    selected={input.addOns}
                    cadence="monthly"
                    onChange={(id, quantity) => setInput((value) => ({ ...value, addOns: { ...value.addOns, [id]: quantity } }))}
                  />

                  <OptionCounterSection
                    title="Launch and custom services"
                    options={oneOffServices.filter((item) =>
                      ['branding-theme-pack', 'data-migration', 'mobile-app-store-submission', 'federation-onboarding', 'custom-federation-adapter', 'sso-saml'].includes(item.id),
                    )}
                    selected={input.oneOffServices}
                    cadence="one-off"
                    onChange={(id, quantity) =>
                      setInput((value) => ({ ...value, oneOffServices: { ...value.oneOffServices, [id]: quantity } }))
                    }
                  />
                </>
              )}

              <OrderForm quote={quote} />
            </>
          )}
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

function ProductLineButton({
  active,
  title,
  price,
  detail,
  onPress,
}: {
  active: boolean;
  title: string;
  price: string;
  detail: string;
  onPress: () => void;
}) {
  return (
    <button
      type="button"
      className={`rounded-2xl border p-5 text-left transition ${
        active ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/12 shadow-lg shadow-[var(--glow-accent-subtle)]' : 'border-white/10 bg-black/18 hover:border-white/24'
      }`}
      onClick={onPress}
    >
      <span className="flex items-start gap-3">
        <span
          className={`mt-1 grid size-5 shrink-0 place-items-center rounded-full border ${
            active ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)] text-[var(--text-inverse)]' : 'border-white/20 text-transparent'
          }`}
        >
          <CheckCircle2 className="size-3.5" />
        </span>
        <span>
          <span className="block text-lg font-black text-white">{title}</span>
          <span className="mt-2 block text-2xl font-black text-[var(--color-primary)]">{price}</span>
          <span className="mt-2 block text-sm leading-6 text-white/58">{detail}</span>
        </span>
      </span>
    </button>
  );
}

function CommunityPlanCard({
  plan,
  selected,
  onPress,
}: {
  plan: CommunityTimebankPlan;
  selected: boolean;
  onPress: () => void;
}) {
  return (
    <button
      type="button"
      className={`flex h-full flex-col rounded-2xl border p-4 text-left transition ${
        selected ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/12 shadow-lg shadow-[var(--glow-accent-subtle)]' : 'border-white/10 bg-black/18 hover:border-white/24'
      }`}
      onClick={onPress}
    >
      <span className="flex items-start justify-between gap-3">
        <span>
          <span className="block text-lg font-black text-white">{plan.name}</span>
          <span className="mt-1 block text-xs font-semibold text-white/45 uppercase">{plan.activeMemberLabel}</span>
        </span>
        {selected ? <CheckCircle2 className="size-5 text-[var(--color-accent)]" /> : null}
      </span>
      <span className="mt-4 block text-3xl font-black text-[var(--color-primary)]">{formatCurrency(plan.annualMonthlyEur)}</span>
      <span className="text-xs font-semibold text-white/45 uppercase">per month, billed annually</span>
      <span className="mt-3 block text-sm leading-6 text-white/60">{plan.summary}</span>
      <span className="mt-auto pt-4 text-xs leading-5 text-white/42">{plan.upgradeTrigger}</span>
    </button>
  );
}

function FeatureList({ title, items, tone }: { title: string; items: string[]; tone: 'include' | 'held-back' }) {
  return (
    <div className="rounded-xl border border-white/10 bg-black/18 p-4">
      <p className="mb-4 flex items-center gap-2 text-sm font-black text-white">
        {tone === 'include' ? <CheckCircle2 className="size-4 text-[var(--color-accent)]" /> : <LockKeyhole className="size-4 text-[var(--color-primary)]" />}
        {title}
      </p>
      <ul className="grid gap-3">
        {items.map((item) => (
          <li key={item} className="grid grid-cols-[auto_1fr] gap-2 text-sm leading-6 text-white/58">
            <span className={tone === 'include' ? 'mt-2 size-1.5 rounded-full bg-[color:var(--color-accent)]' : 'mt-2 size-1.5 rounded-full bg-[color:var(--color-primary)]'} />
            <span>{item}</span>
          </li>
        ))}
      </ul>
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
  displayValue,
  memberKind,
  members,
  onPress,
}: {
  active: boolean;
  label: string;
  detail: string;
  displayValue: string;
  memberKind: string;
  members: number;
  onPress: () => void;
}) {
  return (
    <button
      type="button"
      className={`min-h-36 rounded-2xl border p-4 text-left transition ${
        active ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/12 shadow-lg shadow-[var(--glow-accent-subtle)]' : 'border-white/10 bg-black/18 hover:border-white/24'
      }`}
      onClick={onPress}
    >
      <span className="block text-sm font-black text-white">{label}</span>
      <span className={`mt-2 block font-black leading-none text-[var(--color-primary)] ${members === 100000 ? 'text-2xl' : 'text-3xl'}`}>{displayValue}</span>
      <span className="mt-1 block text-xs font-semibold text-white/36 uppercase">{memberKind}</span>
      <span className="mt-2 block text-xs leading-5 text-white/52">{detail}</span>
    </button>
  );
}

function EnterpriseCustomSection() {
  return (
    <section className="rounded-2xl border border-[color:var(--color-accent)]/28 bg-[color:var(--color-accent)]/8 p-5">
      <p className="flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">
        <Sparkles className="size-4" />
        Enterprise custom
      </p>
      <h3 className="mt-2 text-2xl font-black text-white">Published pricing stops at 100,000 active members.</h3>
      <p className="mt-3 max-w-3xl text-sm leading-6 text-white/62">
        Above that point, the sensible commercial model depends on traffic shape, tenant count, storage, outbound email, search volume, integrations, migration, support cover, and SLA. This keeps the site honest for million-user scenarios without hiding the affordable tiers below the cap.
      </p>
      <dl className="mt-5 grid gap-4 sm:grid-cols-2">
        {[
          ['Capacity model', 'Active users, page views, peak concurrency, email volume, storage, and search indexing.'],
          ['Architecture', 'Dedicated cluster, data residency, backup retention, observability, staging, and scaling plan.'],
          ['Service level', 'Support windows, incident response, release cadence, security evidence, and escalation route.'],
          ['Commercial terms', 'Bespoke monthly platform fee, onboarding, migration, and any shared-risk growth model.'],
        ].map(([term, description]) => (
          <div key={term} className="border-l border-[color:var(--color-accent)]/35 pl-4">
            <dt className="font-black text-white">{term}</dt>
            <dd className="mt-1 text-sm leading-6 text-white/56">{description}</dd>
          </div>
        ))}
      </dl>
    </section>
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
                selected ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)]/12 shadow-lg shadow-[var(--glow-accent-subtle)]' : 'border-white/10 bg-black/18 hover:border-white/24'
              }`}
              onClick={() => onSelect(choice.id)}
            >
              <span className="flex gap-3">
                <span
                  className={`mt-1 grid size-5 shrink-0 place-items-center rounded-full border ${
                    selected ? 'border-[color:var(--color-accent)] bg-[color:var(--color-accent)] text-[var(--text-inverse)]' : 'border-white/20 text-transparent'
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
              <span className="rounded-full border border-white/10 bg-black/24 px-3 py-2 text-sm font-black text-[var(--color-primary)]">
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
                <p className="mt-2 text-sm font-bold text-[var(--color-primary)]">
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

function formatActiveMemberLabel(activeMembers: number): string {
  return activeMembers > 100000 ? 'Over 100,000 active members' : `${activeMembers.toLocaleString('en-IE')} active members`;
}

function updateBilling(setInput: React.Dispatch<React.SetStateAction<QuoteInput>>, billingCycle: BillingCycle) {
  setInput((value) => ({ ...value, billingCycle }));
}

function switchProductLine(setInput: React.Dispatch<React.SetStateAction<QuoteInput>>, productLine: ProductLine) {
  setInput((value) => ({
    ...value,
    productLine,
    activeMembers: productLine === 'community-timebanking' ? 150 : 1000,
    billingCycle: 'annual',
    communityPlanId: productLine === 'community-timebanking' ? 'community-edition' : value.communityPlanId,
    onboardingPackageId: productLine === 'community-timebanking' ? 'community-assisted-launch' : 'quick-start',
  }));
}

function isCommunityPlan(plan: QuoteEstimate['hostingPlan']): plan is CommunityTimebankPlan {
  return 'annualMonthlyEur' in plan;
}
