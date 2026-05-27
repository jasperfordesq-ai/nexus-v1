// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  BillingCycle,
  communityOnboardingPackages,
  communityTimebankPlans,
  hostingPlans,
  maintenancePlans,
  onboardingPackages,
  oneOffServices,
  ProductLine,
  recurringAddOns,
  supportTiers,
  type CommunityTimebankPlan,
  type HostingPlan,
} from '../data/pricing';

export type QuotePlan = HostingPlan | CommunityTimebankPlan;

export interface QuoteInput {
  productLine?: ProductLine;
  activeMembers: number;
  billingCycle: BillingCycle;
  communityPlanId?: string;
  supportTierId: string;
  maintenancePlanId: string;
  onboardingPackageId: string;
  addOns: Record<string, number>;
  oneOffServices: Record<string, number>;
}

export interface QuoteLineItem {
  id: string;
  label: string;
  amountEur: number;
  quantity: number;
  cadence: 'monthly' | 'one-off';
}

export interface QuoteEstimate {
  productLine: ProductLine;
  productLineLabel: string;
  hostingPlan: QuotePlan;
  billingCycle: BillingCycle;
  pricingMode: 'published' | 'custom';
  monthlyRecurring: number;
  annualRecurring: number;
  annualSavings: number;
  oneOffTotal: number;
  firstYearTotal: number;
  lineItems: QuoteLineItem[];
}

export interface OrderEmailInput {
  contactName: string;
  organisation: string;
  email: string;
  region: string;
  note: string;
  quote: QuoteEstimate;
}

export function recommendHostingPlan(activeMembers: number): HostingPlan {
  const normalisedMembers = Math.max(0, Math.ceil(activeMembers));

  return (
    hostingPlans.find((plan) => plan.activeMemberLimit === null || normalisedMembers <= plan.activeMemberLimit) ??
    hostingPlans[hostingPlans.length - 1]
  );
}

export function recommendCommunityTimebankPlan(activeMembers: number): CommunityTimebankPlan {
  const normalisedMembers = Math.max(0, Math.ceil(activeMembers));

  return (
    communityTimebankPlans.find((plan) => plan.activeMemberLimit !== null && normalisedMembers <= plan.activeMemberLimit) ??
    communityTimebankPlans[communityTimebankPlans.length - 1]
  );
}

export function estimateQuote(input: QuoteInput): QuoteEstimate {
  const productLine = input.productLine ?? 'full-platform';

  if (productLine === 'community-timebanking') {
    return estimateCommunityQuote(input);
  }

  return estimateFullPlatformQuote(input);
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-IE', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatQuoteAmount(quote: QuoteEstimate, amount: number): string {
  return quote.pricingMode === 'custom' ? 'Custom quote' : formatCurrency(amount);
}

export function buildOrderEmail(input: OrderEmailInput): string {
  const formatLineAmount = (item: QuoteLineItem) =>
    input.quote.pricingMode === 'custom' && item.amountEur === 0 ? 'Custom quote' : formatCurrency(item.amountEur);
  const monthlyLines = input.quote.lineItems
    .filter((item) => item.cadence === 'monthly')
    .map((item) => `- ${item.label}: ${formatLineAmount(item)}${input.quote.pricingMode === 'custom' ? '' : '/mo'}${item.quantity > 1 ? ` x${item.quantity}` : ''}`)
    .join('\n');

  const oneOffLines = input.quote.lineItems
    .filter((item) => item.cadence === 'one-off')
    .map((item) => `- ${item.label}: ${formatLineAmount(item)}${item.quantity > 1 ? ` x${item.quantity}` : ''}`)
    .join('\n');

  const subject = `Project NEXUS hosting order enquiry - ${input.organisation || input.quote.hostingPlan.name}`;
  const body = [
    'Hello Project NEXUS,',
    '',
    'I would like to discuss this managed hosting order.',
    '',
    `Contact: ${input.contactName}`,
    `Organisation: ${input.organisation}`,
    `Email: ${input.email}`,
    `Region: ${input.region}`,
    '',
    `Product line: ${input.quote.productLineLabel}`,
    `Recommended plan: ${input.quote.hostingPlan.name}`,
    `Pricing mode: ${input.quote.pricingMode === 'custom' ? 'Bespoke enterprise discovery required' : 'Published calculator estimate'}`,
    `Billing preference: ${input.quote.billingCycle}`,
    `Estimated monthly recurring: ${formatQuoteAmount(input.quote, input.quote.monthlyRecurring)}`,
    `Estimated annual recurring: ${formatQuoteAmount(input.quote, input.quote.annualRecurring)}`,
    `Estimated one-off total: ${formatQuoteAmount(input.quote, input.quote.oneOffTotal)}`,
    `Estimated first-year total: ${formatQuoteAmount(input.quote, input.quote.firstYearTotal)}`,
    '',
    'Recurring items:',
    monthlyLines || '- None selected',
    '',
    'One-off items:',
    oneOffLines || '- None selected',
    '',
    'Notes:',
    input.note || 'No extra notes added.',
  ].join('\n');

  return `mailto:jasper@hour-timebank.ie?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

function estimateCommunityQuote(input: QuoteInput): QuoteEstimate {
  const plan =
    communityTimebankPlans.find((item) => item.id === input.communityPlanId) ?? recommendCommunityTimebankPlan(input.activeMembers);
  const onboarding = findById(communityOnboardingPackages, input.onboardingPackageId);
  const monthlyPlanAmount = input.billingCycle === 'annual' ? plan.annualMonthlyEur : plan.monthlyEur;

  const recurringItems: QuoteLineItem[] = [
    {
      id: plan.id,
      label: `${plan.name} timebanking`,
      amountEur: monthlyPlanAmount,
      quantity: 1,
      cadence: 'monthly',
    },
  ];

  const oneOffItems: QuoteLineItem[] = [
    {
      id: `${plan.id}-setup`,
      label: `${plan.name} setup`,
      amountEur: plan.setupEur,
      quantity: 1,
      cadence: 'one-off',
    },
    {
      id: onboarding.id,
      label: onboarding.label,
      amountEur: onboarding.fixedEur,
      quantity: 1,
      cadence: 'one-off',
    },
  ];

  const monthlyRecurring = sum(recurringItems.map((item) => item.amountEur));
  const annualWithoutDiscount = plan.monthlyEur * 12;
  const annualRecurring = input.billingCycle === 'annual' ? plan.annualEur : annualWithoutDiscount;
  const annualSavings = annualWithoutDiscount - annualRecurring;
  const oneOffTotal = sum(oneOffItems.map((item) => item.amountEur));

  return {
    productLine: 'community-timebanking',
    productLineLabel: 'Community Timebanking',
    hostingPlan: plan,
    billingCycle: input.billingCycle,
    pricingMode: 'published',
    monthlyRecurring,
    annualRecurring,
    annualSavings,
    oneOffTotal,
    firstYearTotal: annualRecurring + oneOffTotal,
    lineItems: [...recurringItems, ...oneOffItems].filter((item) => item.amountEur !== 0),
  };
}

function estimateFullPlatformQuote(input: QuoteInput): QuoteEstimate {
  const hostingPlan = recommendHostingPlan(input.activeMembers);

  if (hostingPlan.isCustom) {
    return estimateEnterpriseCustomQuote(input, hostingPlan);
  }

  const support = findById(supportTiers, input.supportTierId);
  const maintenance = findById(maintenancePlans, input.maintenancePlanId);
  const onboarding = findById(onboardingPackages, input.onboardingPackageId);

  const recurringItems: QuoteLineItem[] = [
    {
      id: hostingPlan.id,
      label: `${hostingPlan.name} hosting`,
      amountEur: hostingPlan.monthlyEur,
      quantity: 1,
      cadence: 'monthly',
    },
    {
      id: support.id,
      label: support.label,
      amountEur: support.monthlyEur,
      quantity: 1,
      cadence: 'monthly',
    },
    {
      id: maintenance.id,
      label: maintenance.label,
      amountEur: maintenance.monthlyEur,
      quantity: 1,
      cadence: 'monthly',
    },
  ];

  Object.entries(input.addOns).forEach(([id, quantity]) => {
    const option = recurringAddOns.find((item) => item.id === id);
    const safeQuantity = Math.max(0, Math.floor(quantity));

    if (!option || safeQuantity === 0) {
      return;
    }

    recurringItems.push({
      id,
      label: option.label,
      amountEur: option.monthlyEur * safeQuantity,
      quantity: safeQuantity,
      cadence: 'monthly',
    });
  });

  const oneOffItems: QuoteLineItem[] = [
    {
      id: `${hostingPlan.id}-setup`,
      label: `${hostingPlan.name} setup`,
      amountEur: hostingPlan.setupEur,
      quantity: 1,
      cadence: 'one-off',
    },
    {
      id: onboarding.id,
      label: onboarding.label,
      amountEur: onboarding.fixedEur,
      quantity: 1,
      cadence: 'one-off',
    },
  ];

  Object.entries(input.oneOffServices).forEach(([id, quantity]) => {
    const option = oneOffServices.find((item) => item.id === id);
    const safeQuantity = Math.max(0, Math.floor(quantity));

    if (!option || safeQuantity === 0) {
      return;
    }

    oneOffItems.push({
      id,
      label: option.label,
      amountEur: option.fixedEur * safeQuantity,
      quantity: safeQuantity,
      cadence: 'one-off',
    });
  });

  const monthlyRecurring = sum(recurringItems.map((item) => item.amountEur));
  const annualWithoutDiscount = monthlyRecurring * 12;
  const annualRecurring = input.billingCycle === 'annual' ? monthlyRecurring * 10 : annualWithoutDiscount;
  const annualSavings = annualWithoutDiscount - annualRecurring;
  const oneOffTotal = sum(oneOffItems.map((item) => item.amountEur));

  return {
    productLine: 'full-platform',
    productLineLabel: 'Full Platform Hosting',
    hostingPlan,
    billingCycle: input.billingCycle,
    pricingMode: 'published',
    monthlyRecurring,
    annualRecurring,
    annualSavings,
    oneOffTotal,
    firstYearTotal: annualRecurring + oneOffTotal,
    lineItems: [...recurringItems, ...oneOffItems].filter((item) => item.amountEur !== 0),
  };
}

function estimateEnterpriseCustomQuote(input: QuoteInput, hostingPlan: HostingPlan): QuoteEstimate {
  return {
    productLine: 'full-platform',
    productLineLabel: 'Full Platform Hosting',
    hostingPlan,
    billingCycle: input.billingCycle,
    pricingMode: 'custom',
    monthlyRecurring: 0,
    annualRecurring: 0,
    annualSavings: 0,
    oneOffTotal: 0,
    firstYearTotal: 0,
    lineItems: [
      {
        id: hostingPlan.id,
        label: 'Enterprise Custom hosting, capacity, and traffic discovery',
        amountEur: 0,
        quantity: 1,
        cadence: 'monthly',
      },
      {
        id: 'enterprise-custom-commercials',
        label: 'Bespoke architecture, SLA, support, migration, and commercial terms',
        amountEur: 0,
        quantity: 1,
        cadence: 'one-off',
      },
    ],
  };
}

function findById<T extends { id: string }>(items: T[], id: string): T {
  const item = items.find((entry) => entry.id === id);

  if (!item) {
    throw new Error(`Unknown pricing option: ${id}`);
  }

  return item;
}

function sum(values: number[]): number {
  return values.reduce((total, value) => total + value, 0);
}
