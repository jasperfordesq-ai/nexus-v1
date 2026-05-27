// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  BillingCycle,
  hostingPlans,
  maintenancePlans,
  onboardingPackages,
  oneOffServices,
  recurringAddOns,
  supportTiers,
  type HostingPlan,
} from '../data/pricing';

export interface QuoteInput {
  activeMembers: number;
  billingCycle: BillingCycle;
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
  hostingPlan: HostingPlan;
  billingCycle: BillingCycle;
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

export function estimateQuote(input: QuoteInput): QuoteEstimate {
  const hostingPlan = recommendHostingPlan(input.activeMembers);
  const support = findById(supportTiers, input.supportTierId);
  const maintenance = findById(maintenancePlans, input.maintenancePlanId);
  const onboarding = findById(onboardingPackages, input.onboardingPackageId);

  const lineItems: QuoteLineItem[] = [
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

    lineItems.push({
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

  const allLineItems = [...lineItems, ...oneOffItems];
  const monthlyRecurring = sum(lineItems.map((item) => item.amountEur));
  const annualWithoutDiscount = monthlyRecurring * 12;
  const annualRecurring = input.billingCycle === 'annual' ? monthlyRecurring * 10 : annualWithoutDiscount;
  const annualSavings = annualWithoutDiscount - annualRecurring;
  const oneOffTotal = sum(oneOffItems.map((item) => item.amountEur));

  return {
    hostingPlan,
    billingCycle: input.billingCycle,
    monthlyRecurring,
    annualRecurring,
    annualSavings,
    oneOffTotal,
    firstYearTotal: annualRecurring + oneOffTotal,
    lineItems: allLineItems.filter((item) => item.amountEur !== 0),
  };
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-IE', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
  }).format(amount);
}

export function buildOrderEmail(input: OrderEmailInput): string {
  const monthlyLines = input.quote.lineItems
    .filter((item) => item.cadence === 'monthly')
    .map((item) => `- ${item.label}: ${formatCurrency(item.amountEur)}/mo${item.quantity > 1 ? ` x${item.quantity}` : ''}`)
    .join('\n');

  const oneOffLines = input.quote.lineItems
    .filter((item) => item.cadence === 'one-off')
    .map((item) => `- ${item.label}: ${formatCurrency(item.amountEur)}${item.quantity > 1 ? ` x${item.quantity}` : ''}`)
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
    `Recommended plan: ${input.quote.hostingPlan.name}`,
    `Billing preference: ${input.quote.billingCycle}`,
    `Estimated monthly recurring: ${formatCurrency(input.quote.monthlyRecurring)}`,
    `Estimated annual recurring: ${formatCurrency(input.quote.annualRecurring)}`,
    `Estimated one-off total: ${formatCurrency(input.quote.oneOffTotal)}`,
    `Estimated first-year total: ${formatCurrency(input.quote.firstYearTotal)}`,
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
