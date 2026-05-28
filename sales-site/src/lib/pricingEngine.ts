// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  BillingCycle,
  communityOnboardingPackages,
  communityTimebankPlans,
  deploymentModes,
  hostingPlans,
  maintenancePlans,
  onboardingPackages,
  oneOffServices,
  ProductLine,
  recurringAddOns,
  supportTiers,
  type CommunityTimebankPlan,
  type DeploymentMode,
  type DeploymentModeOption,
  type HostingPlan,
} from '../data/pricing';

export type QuotePlan = HostingPlan | CommunityTimebankPlan;

export interface QuoteInput {
  productLine?: ProductLine;
  activeMembers: number;
  billingCycle: BillingCycle;
  deploymentModeId?: DeploymentMode;
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
  priceLabel?: string;
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

  const deploymentMode = findDeploymentMode(input.deploymentModeId);
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

  if (deploymentMode.requiresCustomQuote) {
    recurringItems.push({
      id: deploymentMode.id,
      label: 'Dedicated managed infrastructure discovery',
      amountEur: 0,
      priceLabel: deploymentMode.startsFromMonthlyEur
        ? `Starts from ${formatCurrency(deploymentMode.startsFromMonthlyEur)}/mo`
        : 'Custom quote',
      quantity: 1,
      cadence: 'monthly',
    });
  } else if (deploymentMode.monthlyEur !== 0) {
    recurringItems.push({
      id: deploymentMode.id,
      label: deploymentMode.label,
      amountEur: deploymentMode.monthlyEur,
      quantity: 1,
      cadence: 'monthly',
    });
  }

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

  if (deploymentMode.setupEur !== 0) {
    oneOffItems.push({
      id: `${deploymentMode.id}-setup`,
      label: deploymentMode.setupLabel,
      amountEur: deploymentMode.setupEur,
      quantity: 1,
      cadence: 'one-off',
    });
  }

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
  const pricingMode = deploymentMode.requiresCustomQuote ? 'custom' : 'published';

  return {
    productLine: 'full-platform',
    productLineLabel: 'Full Platform Hosting',
    hostingPlan,
    billingCycle: input.billingCycle,
    pricingMode,
    monthlyRecurring,
    annualRecurring,
    annualSavings,
    oneOffTotal,
    firstYearTotal: annualRecurring + oneOffTotal,
    lineItems: [...recurringItems, ...oneOffItems].filter((item) => item.amountEur !== 0 || item.priceLabel),
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

function findDeploymentMode(id: DeploymentMode | undefined): DeploymentModeOption {
  return deploymentModes.find((mode) => mode.id === (id ?? 'shared-platform')) ?? deploymentModes[0];
}

function sum(values: number[]): number {
  return values.reduce((total, value) => total + value, 0);
}
