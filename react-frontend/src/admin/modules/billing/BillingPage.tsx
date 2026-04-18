// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BillingPage
 * Main billing dashboard showing current subscription, quick actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Spinner,
  Divider,
  Progress,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import { CreditCard, ArrowRight, Receipt, Settings, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { billingApi, type SubscriptionDetails } from '../../api/billingApi';
import { PageHeader } from '../../components';

function statusColor(status: string): 'success' | 'warning' | 'danger' | 'default' {
  switch (status) {
    case 'active':
      return 'success';
    case 'trialing':
      return 'warning';
    case 'past_due':
    case 'incomplete':
      return 'warning';
    case 'cancelled':
    case 'expired':
      return 'danger';
    default:
      return 'default';
  }
}

function statusLabel(status: string, t: TFunction): string {
  switch (status) {
    case 'active':
      return t('billing.status_active', 'Active');
    case 'trialing':
      return t('billing.status_trialing', 'Trial');
    case 'past_due':
      return t('billing.status_past_due', 'Past Due');
    case 'cancelled':
      return t('billing.status_cancelled', 'Cancelled');
    case 'expired':
      return t('billing.status_expired', 'Expired');
    case 'incomplete':
      return t('billing.status_incomplete', 'Incomplete');
    default:
      return status;
  }
}

// Extended subscription type with optional usage fields from the API
interface SubscriptionWithUsage extends SubscriptionDetails {
  user_count?: number;
  plan?: {
    max_users: number | null;
  };
}

export function BillingPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.title', 'Billing'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    if (searchParams.get('cancelled') === '1') {
      toast.warning(t('billing.checkout_cancelled', 'Checkout was cancelled. No charge was made.'));
      navigate(tenantPath('/admin/billing'), { replace: true });
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const { isOpen: isUpgradeOpen, onOpen: onUpgradeOpen, onClose: onUpgradeClose } = useDisclosure();
  const [upgradeMessage, setUpgradeMessage] = useState('');
  const [upgradeSending, setUpgradeSending] = useState(false);

  const [subscription, setSubscription] = useState<SubscriptionWithUsage | null>(null);
  const [loading, setLoading] = useState(true);
  const [portalLoading, setPortalLoading] = useState(false);

  const loadSubscription = useCallback(async () => {
    setLoading(true);
    try {
      const res = await billingApi.getSubscription();
      if (res.success && res.data) {
        setSubscription(res.data as unknown as SubscriptionWithUsage);
      }
    } catch {
      // No subscription found — that's OK
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSubscription();
  }, [loadSubscription]);

  const handleManagePayment = async () => {
    setPortalLoading(true);
    try {
      const res = await billingApi.createPortal();
      if (res.success && res.data) {
        const data = res.data as unknown as { portal_url: string };
        window.open(data.portal_url, '_blank', 'noopener,noreferrer');
      }
    } catch {
      toast.error(t('billing.portal_error', 'Failed to open payment portal'));
    } finally {
      setPortalLoading(false);
    }
  };

  const handleUpgradeRequest = async () => {
    setUpgradeSending(true);
    try {
      // Fire and forget — endpoint may not exist yet; fall back to mailto on failure
      await fetch('/api/v2/admin/billing/upgrade-request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: upgradeMessage }),
      });
      toast.success(t('billing.upgrade_sent', 'Upgrade request sent'));
      onUpgradeClose();
      setUpgradeMessage('');
    } catch {
      // Fallback: open mailto link
      const subject = encodeURIComponent('Plan Upgrade Request');
      const body = encodeURIComponent(upgradeMessage);
      window.location.href = `mailto:jasper@hour-timebank.ie?subject=${subject}&body=${body}`;
    } finally {
      setUpgradeSending(false);
    }
  };

  const hasActiveSubscription =
    subscription &&
    subscription.status !== 'cancelled' &&
    subscription.status !== 'expired';

  // Usage bar values
  const userCount = subscription?.user_count ?? null;
  const maxUsers = subscription?.plan?.max_users ?? null;
  const usagePct =
    userCount !== null && maxUsers !== null && maxUsers > 0
      ? Math.round((userCount / maxUsers) * 100)
      : null;

  return (
    <div>
      <PageHeader
        title={t('billing.title', 'Billing')}
        description={t('billing.description', 'Manage your subscription, payment methods, and invoices')}
      />

      {/* ─── Plan Usage Card ─── */}
      {!loading && subscription && (
        <Card className="mb-6">
          <CardHeader className="flex gap-3">
            <Users className="w-5 h-5 text-primary" />
            <h3 className="text-lg font-semibold">
              {t('billing.your_plan_usage', 'Your Plan Usage')}
            </h3>
          </CardHeader>
          <Divider />
          <CardBody className="gap-4">
            {maxUsers === null ? (
              <p className="text-success font-medium">
                {t('billing.unlimited_users', 'Unlimited users on your plan ✓')}
              </p>
            ) : userCount !== null && usagePct !== null ? (
              <>
                <div className="flex items-center justify-between text-sm mb-1">
                  <span className="text-default-500">
                    {t('billing.user_count_label', '{{count}} / {{max}} users', {
                      count: userCount,
                      max: maxUsers,
                    })}
                  </span>
                  <span className="text-default-500">{usagePct}%</span>
                </div>
                <Progress
                  value={usagePct}
                  color={usagePct >= 100 ? 'danger' : usagePct >= 80 ? 'warning' : 'primary'}
                  className="w-full"
                  aria-label={t('billing.your_plan_usage', 'Your Plan Usage')}
                />
                {usagePct >= 100 && (
                  <p className="text-danger text-sm">
                    {t(
                      'billing.over_limit_warning',
                      'You have exceeded your plan limit. Please contact us to upgrade.'
                    )}
                  </p>
                )}
                {usagePct >= 80 && usagePct < 100 && (
                  <p className="text-warning text-sm">
                    {t('billing.approaching_limit', 'Approaching your plan limit')}
                  </p>
                )}
              </>
            ) : null}

            <Button
              color="primary"
              variant="flat"
              size="sm"
              className="self-start"
              onPress={onUpgradeOpen}
            >
              {t('billing.request_upgrade', 'Request Upgrade')}
            </Button>
          </CardBody>
        </Card>
      )}

      {/* ─── Upgrade Request Modal ─── */}
      <Modal isOpen={isUpgradeOpen} onClose={onUpgradeClose} size="md">
        <ModalContent>
          <ModalHeader>{t('billing.request_upgrade', 'Request Upgrade')}</ModalHeader>
          <ModalBody>
            <p className="text-default-500 text-sm mb-3">
              {t(
                'billing.upgrade_modal_desc',
                "We'll be in touch about upgrading your plan."
              )}
            </p>
            <Textarea
              label={t('billing.upgrade_message', 'Message (optional)')}
              placeholder={t('billing.upgrade_message_placeholder', 'Describe your needs...')}
              value={upgradeMessage}
              onValueChange={setUpgradeMessage}
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onUpgradeClose}>
              {t('billing.cancel', 'Cancel')}
            </Button>
            <Button color="primary" isLoading={upgradeSending} onPress={handleUpgradeRequest}>
              {t('billing.send_request', 'Send Request')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Current Plan Card */}
          <Card className="lg:col-span-2">
            <CardHeader className="flex gap-3">
              <CreditCard className="w-5 h-5 text-primary" />
              <h3 className="text-lg font-semibold">{t('billing.current_plan', 'Current Plan')}</h3>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              {hasActiveSubscription ? (
                <>
                  <div className="flex items-center gap-3 flex-wrap">
                    <h4 className="text-xl font-bold">{subscription.plan_name}</h4>
                    <Chip size="sm" variant="flat" color="primary">
                      {t('billing.tier', 'Tier')} {subscription.plan_tier_level}
                    </Chip>
                    <Chip size="sm" variant="flat" color={statusColor(subscription.status)}>
                      {statusLabel(subscription.status, t)}
                    </Chip>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                    <div>
                      <p className="text-sm text-default-500">
                        {t('billing.billing_interval', 'Billing Interval')}
                      </p>
                      <p className="font-medium capitalize">{subscription.billing_interval}</p>
                    </div>
                    <div>
                      <p className="text-sm text-default-500">
                        {t('billing.next_billing_date', 'Next Billing Date')}
                      </p>
                      <p className="font-medium">
                        {subscription.current_period_end
                          ? new Date(subscription.current_period_end).toLocaleDateString()
                          : '--'}
                      </p>
                    </div>
                    {subscription.trial_ends_at && (
                      <div>
                        <p className="text-sm text-default-500">
                          {t('billing.trial_ends', 'Trial Ends')}
                        </p>
                        <p className="font-medium">
                          {new Date(subscription.trial_ends_at).toLocaleDateString()}
                        </p>
                      </div>
                    )}
                    {subscription.cancel_at_period_end && (
                      <div>
                        <Chip size="sm" color="warning" variant="flat">
                          {t('billing.cancels_at_period_end', 'Cancels at period end')}
                        </Chip>
                      </div>
                    )}
                  </div>
                </>
              ) : (
                <div className="text-center py-6">
                  <CreditCard className="w-12 h-12 text-default-300 mx-auto mb-3" />
                  <p className="text-default-500 mb-4">
                    {t('billing.no_subscription', 'No active subscription')}
                  </p>
                  <Button
                    as={Link}
                    to={tenantPath('/admin/billing/plans')}
                    color="primary"
                    endContent={<ArrowRight className="w-4 h-4" />}
                  >
                    {t('billing.choose_plan', 'Choose a Plan')}
                  </Button>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader>
              <h3 className="text-lg font-semibold">{t('billing.actions', 'Actions')}</h3>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              <Button
                as={Link}
                to={tenantPath('/admin/billing/plans')}
                variant="flat"
                color="primary"
                className="justify-start"
                startContent={<CreditCard className="w-4 h-4" />}
                endContent={<ArrowRight className="w-4 h-4 ml-auto" />}
                fullWidth
              >
                {t('billing.change_plan', 'Change Plan')}
              </Button>

              {hasActiveSubscription && (
                <Button
                  variant="flat"
                  className="justify-start"
                  startContent={<Settings className="w-4 h-4" />}
                  endContent={<ArrowRight className="w-4 h-4 ml-auto" />}
                  isLoading={portalLoading}
                  onPress={handleManagePayment}
                  fullWidth
                >
                  {t('billing.manage_payment', 'Manage Payment Methods')}
                </Button>
              )}

              <Button
                as={Link}
                to={tenantPath('/admin/billing/invoices')}
                variant="flat"
                className="justify-start"
                startContent={<Receipt className="w-4 h-4" />}
                endContent={<ArrowRight className="w-4 h-4 ml-auto" />}
                fullWidth
              >
                {t('billing.view_invoices', 'View Invoices')}
              </Button>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}

export default BillingPage;
