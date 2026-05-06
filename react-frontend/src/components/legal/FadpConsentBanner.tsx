// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FadpConsentBanner
 *
 * Sticky bottom banner for Swiss FADP / nDSG deployments.
 * Shown when the tenant has the `fadp_compliance` feature enabled and the
 * member has not yet made an explicit profiling-consent decision this session.
 *
 * Consent decisions (grant or withdrawal) are recorded via
 * POST /v2/me/fadp/consent so they land in the consent ledger.
 */

import { useState } from 'react';
import { Card, CardBody, Button } from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldOff from 'lucide-react/icons/shield-off';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import api from '@/lib/api';

const STORAGE_KEY = 'fadp_consented';

export function FadpConsentBanner() {
  const { t } = useTranslation('legal');
  const { hasFeature } = useTenant();

  const [dismissed, setDismissed] = useState<boolean>(
    () => localStorage.getItem(STORAGE_KEY) === 'true'
  );
  const [loading, setLoading] = useState<'accept' | 'decline' | null>(null);

  // Only render for tenants with FADP compliance feature
  if (!hasFeature('fadp_compliance')) return null;
  if (dismissed) return null;

  const dismiss = () => {
    localStorage.setItem(STORAGE_KEY, 'true');
    setDismissed(true);
  };

  const handleConsent = async (action: 'granted' | 'withdrawn') => {
    setLoading(action === 'granted' ? 'accept' : 'decline');
    try {
      await api.post('/v2/me/fadp/consent', {
        consent_type: 'profiling',
        action,
      });
    } catch {
      // Non-blocking — the banner can still be dismissed even if the request fails
    } finally {
      dismiss();
    }
  };

  return (
    <div
      className="fixed bottom-0 left-0 right-0 z-50 px-4 pb-4 pt-2 pointer-events-none"
      role="region"
      aria-label={t('fadp.fadp_banner_title')}
    >
      <div className="max-w-3xl mx-auto pointer-events-auto">
        <Card
          shadow="lg"
          className="border border-primary-200 bg-[var(--color-surface)]"
        >
          <CardBody className="py-4 px-5">
            <div className="flex items-start gap-4">
              {/* Swiss flag-adjacent icon */}
              <div className="flex-shrink-0 w-9 h-9 rounded-full bg-danger-100 flex items-center justify-center mt-0.5">
                <ShieldCheck size={18} className="text-danger-600" />
              </div>

              {/* Text content */}
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-[var(--color-text)] mb-1">
                  {t('fadp.fadp_banner_title')}
                </p>
                <p className="text-xs text-[var(--color-text-muted)] leading-relaxed">
                  {t('fadp.fadp_banner_body')}
                </p>
              </div>

              {/* Dismiss (no preference recorded) */}
              <button
                onClick={dismiss}
                aria-label={t('common:common.dismiss')}
                className="flex-shrink-0 text-default-400 hover:text-default-600 transition-colors mt-0.5"
              >
                <X size={16} />
              </button>
            </div>

            {/* Action buttons */}
            <div className="flex flex-wrap gap-2 mt-4 pl-13">
              <Button
                size="sm"
                color="primary"
                startContent={<ShieldCheck size={14} />}
                isLoading={loading === 'accept'}
                isDisabled={loading !== null}
                onPress={() => handleConsent('granted')}
              >
                {t('fadp.fadp_accept')}
              </Button>
              <Button
                size="sm"
                variant="bordered"
                startContent={<ShieldOff size={14} />}
                isLoading={loading === 'decline'}
                isDisabled={loading !== null}
                onPress={() => handleConsent('withdrawn')}
              >
                {t('fadp.fadp_decline')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default FadpConsentBanner;
