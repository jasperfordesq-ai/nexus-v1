// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PlanSelector
 * Plan selection grid with monthly/yearly toggle and Stripe checkout.
 */

import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  CardFooter,
  Button,
  Chip,
  Spinner,
  Divider,
  ButtonGroup,
} from '@heroui/react';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { billingApi, type Plan, type SubscriptionDetails } from '../../api/billingApi';
import { PageHeader } from '../../components';

export function PlanSelector() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.choose_plan', 'Choose a Plan'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [plans, setPlans] = useState<Plan[]>([]);
  const [subscription, setSubscription] = useState<SubscriptionDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const [billingInterval, setBillingInterval] = useState<'monthly' | 'yearly'>('monthly');
  const [checkoutLoading, setCheckoutLoading] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [plansRes, subRes] = await Promise.all([
        billingApi.getPlans(),
        billingApi.getSubscription().catch(() => null),
      ]);
      if (plansRes.success && plansRes.data) {
        const planData = plansRes.data as unknown;
        if (Array.isArray(planData)) {
          setPlans(planData);
        }
      }
      if (subRes?.success && subRes.data) {
        setSubscription(subRes.data as unknown as SubscriptionDetails);
      }
    } catch {
      toast.error(t('billing.load_error', 'Failed to load plans'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSubscribe = async (planId: number) => {
    setCheckoutLoading(planId);
    try {
      const res = await billingApi.createCheckout({
        plan_id: planId,
        billing_interval: billingInterval,
      });
      if (res.success && res.data) {
        const data = res.data as unknown as { checkout_url: string | null; activated?: boolean };
        if (data.activated) {
          toast.success(t('billing.free_plan_activated', 'Plan activated successfully'));
          navigate(tenantPath('/admin/billing'));
        } else if (data.checkout_url) {
          window.location.href = data.checkout_url;
        } else {
          toast.error(t('billing.checkout_error', 'Failed to start checkout'));
        }
      } else {
        toast.error(t('billing.checkout_error', 'Failed to start checkout'));
      }
    } catch {
      toast.error(t('billing.checkout_error', 'Failed to start checkout'));
    } finally {
      setCheckoutLoading(null);
    }
  };

  const isCurrentPlan = (plan: Plan) =>
    subscription &&
    subscription.plan_id === plan.id &&
    subscription.status !== 'cancelled' &&
    subscription.status !== 'expired';

  const isActivePaidSubscription =
    !!subscription &&
    subscription.status !== 'cancelled' &&
    subscription.status !== 'expired' &&
    subscription.plan_tier_level > 0;

  const isFreePlan = (plan: Plan) =>
    plan.price_monthly === 0 && plan.price_yearly === 0;

  const isDowngrade = (plan: Plan) =>
    isActivePaidSubscription && isFreePlan(plan) && !isCurrentPlan(plan);

  const formatPrice = (amount: number) => {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  return (
    <div>
      <PageHeader
        title={t('billing.choose_plan', 'Choose a Plan')}
        description={t('billing.plans_description', 'Select the plan that best fits your community')}
      />

      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : (
        <>
          {/* Billing interval toggle */}
          <div className="flex justify-center mb-8">
            <ButtonGroup>
              <Button
                color={billingInterval === 'monthly' ? 'primary' : 'default'}
                variant={billingInterval === 'monthly' ? 'solid' : 'bordered'}
                onPress={() => setBillingInterval('monthly')}
              >
                {t('billing.monthly', 'Monthly')}
              </Button>
              <Button
                color={billingInterval === 'yearly' ? 'primary' : 'default'}
                variant={billingInterval === 'yearly' ? 'solid' : 'bordered'}
                onPress={() => setBillingInterval('yearly')}
              >
                {t('billing.yearly', 'Yearly')}
                <Chip size="sm" color="success" variant="flat" className="ml-2">
                  {t('billing.save_percent', 'Save ~17%')}
                </Chip>
              </Button>
            </ButtonGroup>
          </div>

          {/* Plan cards grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
            {plans.map((plan) => {
              const isCurrent = isCurrentPlan(plan);
              const isDowngradeBlocked = isDowngrade(plan);
              const price =
                billingInterval === 'monthly' ? plan.price_monthly : plan.price_yearly;

              return (
                <Card
                  key={plan.id}
                  className={`relative ${isCurrent ? 'border-2 border-primary' : ''} ${isDowngradeBlocked ? 'opacity-60' : ''}`}
                >
                  {isCurrent && (
                    <Chip
                      size="sm"
                      color="primary"
                      variant="solid"
                      className="absolute top-3 right-3 z-10"
                    >
                      {t('billing.current', 'Current')}
                    </Chip>
                  )}
                  {isDowngradeBlocked && (
                    <Chip
                      size="sm"
                      color="default"
                      variant="flat"
                      className="absolute top-3 right-3 z-10"
                    >
                      {t('billing.not_available', 'Not available')}
                    </Chip>
                  )}
                  <CardHeader className="flex-col items-start gap-1 pb-0">
                    <h3 className="text-xl font-bold">{plan.name}</h3>
                    <p className="text-sm text-default-500">{plan.description}</p>
                  </CardHeader>
                  <CardBody className="gap-4">
                    <div>
                      <span className="text-3xl font-bold">{formatPrice(price)}</span>
                      <span className="text-default-500 text-sm ml-1">
                        /{billingInterval === 'monthly'
                          ? t('billing.per_month', 'mo')
                          : t('billing.per_year', 'yr')}
                      </span>
                    </div>

                    <Divider />

                    <ul className="space-y-2">
                      {(Array.isArray(plan.features) ? plan.features : []).map((feature, idx) => (
                        <li key={idx} className="flex items-start gap-2 text-sm">
                          <Check className="w-4 h-4 text-success mt-0.5 shrink-0" />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                  </CardBody>
                  <CardFooter>
                    <Button
                      color={isCurrent || isDowngradeBlocked ? 'default' : 'primary'}
                      variant={isCurrent || isDowngradeBlocked ? 'flat' : 'solid'}
                      isDisabled={!!isCurrent || isDowngradeBlocked}
                      isLoading={checkoutLoading === plan.id}
                      onPress={() => handleSubscribe(plan.id)}
                      fullWidth
                    >
                      {isCurrent
                        ? t('billing.current', 'Current')
                        : isDowngradeBlocked
                          ? t('billing.contact_support', 'Contact support')
                          : t('billing.subscribe', 'Subscribe')}
                    </Button>
                  </CardFooter>
                </Card>
              );
            })}
          </div>

          {plans.length === 0 && (
            <div className="text-center py-12 text-default-500">
              {t('billing.no_plans', 'No plans available at this time')}
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default PlanSelector;
