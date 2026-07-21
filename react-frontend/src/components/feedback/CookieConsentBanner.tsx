// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cookie Consent Banner
 *
 * GDPR-compliant cookie consent banner shown on first visit or
 * when the user re-opens it via "Cookie Settings" in the footer.
 * Three categories matching the Cookie Policy page:
 * - Essential (always on)
 * - Analytics (Sentry error tracking)
 * - Preferences (theme, locale)
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

import Cookie from 'lucide-react/icons/cookie';
import ChevronDown from 'lucide-react/icons/chevron-down';
import ChevronUp from 'lucide-react/icons/chevron-up';
import Shield from 'lucide-react/icons/shield';
import ExternalLink from 'lucide-react/icons/external-link';
import { motion, AnimatePresence } from '@/lib/motion';
import { useCookieConsent } from '@/contexts/CookieConsentContext';
import { useTenant } from '@/contexts/TenantContext';
import { useTranslation } from 'react-i18next';
import { readStoredConsent } from '@/lib/cookieConsentStorage';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { Switch } from '@/components/ui/Switch';

export function CookieConsentBanner() {
  const { showBanner, acceptAll, acceptEssentialOnly, savePreferences } = useCookieConsent();
  const { tenantPath } = useTenant();
  const { t } = useTranslation();
  const [showDetails, setShowDetails] = useState(false);

  // Pre-fill toggles from previous consent (when re-opening via "Cookie Settings")
  const stored = readStoredConsent();
  const [analyticsEnabled, setAnalyticsEnabled] = useState(stored?.analytics ?? false);
  const [preferencesEnabled, setPreferencesEnabled] = useState(stored?.preferences ?? true);

  // Reset toggle state and details panel when banner re-appears
  useEffect(() => {
    if (showBanner) {
      setShowDetails(false);
    }
  }, [showBanner]);

  if (!showBanner) return null;

  const handleSavePreferences = () => {
    savePreferences(analyticsEnabled, preferencesEnabled);
  };

  return (
    <AnimatePresence>
      <motion.div
        initial={{ y: 100, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        exit={{ y: 100, opacity: 0 }}
        transition={{ type: 'spring', damping: 25, stiffness: 200 }}
        className="fixed inset-x-0 bottom-0 z-[700] p-0 sm:p-4"
        role="dialog"
        aria-label={t('cookie_consent.banner_label')}
        aria-modal="false"
        data-mobile-tabbar-cover
        data-nosnippet
      >
        <Card
          className="mx-auto max-h-[calc(100dvh-var(--safe-area-top)-0.5rem)] max-w-3xl overflow-y-auto rounded-t-3xl rounded-b-none border border-[var(--border-strong)] bg-[var(--surface-dropdown)] pb-[var(--safe-area-bottom)] shadow-2xl shadow-black/20 sm:rounded-lg sm:pb-0"
        >
          <div aria-hidden="true" className="flex justify-center pb-1 pt-2 sm:hidden">
            <span className="h-1 w-10 rounded-full bg-[var(--text-subtle)]/40" />
          </div>
          <Card.Content className="p-4 pt-2 sm:p-5">
            {/* Header row */}
            <div className="flex items-start gap-2.5 sm:gap-3">
              <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-amber-400/20 bg-amber-500/15 shadow-sm sm:h-10 sm:w-10 sm:rounded-xl">
                <Cookie className="w-5 h-5 text-amber-500" aria-hidden="true" />
              </div>
              <div className="flex-1 min-w-0">
                <h2 className="text-sm sm:text-base font-semibold text-[var(--text-primary)]">
                  {t('cookie_consent.title')}
                </h2>
                <p className="mt-1 text-sm leading-relaxed text-[var(--text-muted)]">
                  {t('cookie_consent.description')}{' '}
                  <Link
                    to={tenantPath('/cookies')}
                    className="inline-flex items-center gap-1 text-[var(--color-primary)] hover:underline font-medium"
                  >
                    {t('cookie_consent.learn_more')}
                    <ExternalLink className="w-3 h-3" aria-hidden="true" />
                  </Link>
                </p>
              </div>
            </div>

            {/* Expandable details */}
            <AnimatePresence>
              {showDetails && (
                <motion.div
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  transition={{ duration: 0.2 }}
                  className="overflow-hidden"
                >
                  <div className="mt-3 space-y-2.5 border-t border-[var(--border-default)] pt-3 sm:mt-4 sm:space-y-3 sm:pt-4">
                    {/* Essential — always on */}
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] bg-[var(--surface-elevated)] p-2.5 sm:rounded-xl sm:p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Shield className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.essential')}
                          </p>
                          <p className="text-sm text-[var(--text-muted)]">
                            {t('cookie_consent.essential_desc')}
                          </p>
                        </div>
                      </div>
                      <Chip color="success" size="sm" variant="flat" className="shrink-0">
                        {t('cookie_consent.always_on')}
                      </Chip>
                    </div>

                    {/* Analytics */}
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] bg-[var(--surface-elevated)] p-2.5 sm:rounded-xl sm:p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Cookie className="w-4 h-4 text-blue-500 flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.analytics')}
                          </p>
                          <p className="text-sm text-[var(--text-muted)]">
                            {t('cookie_consent.analytics_desc')}
                          </p>
                        </div>
                      </div>
                      <Switch
                        size="sm"
                        isSelected={analyticsEnabled}
                        onValueChange={setAnalyticsEnabled}
                        aria-label={t('cookie_consent.toggle_analytics')}
                      />
                    </div>

                    {/* Preferences */}
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-default)] bg-[var(--surface-elevated)] p-2.5 sm:rounded-xl sm:p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Cookie className="w-4 h-4 text-accent flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.preferences')}
                          </p>
                          <p className="text-sm text-[var(--text-muted)]">
                            {t('cookie_consent.preferences_desc')}
                          </p>
                        </div>
                      </div>
                      <Switch
                        size="sm"
                        isSelected={preferencesEnabled}
                        onValueChange={setPreferencesEnabled}
                        aria-label={t('cookie_consent.toggle_preferences')}
                      />
                    </div>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Action buttons */}
            <div className="mt-3 grid grid-cols-2 items-stretch gap-2 sm:mt-4 sm:flex sm:items-center sm:gap-3">
              <Button
                size="sm"
                variant="light"
                className="col-span-2 min-h-[44px] text-sm text-[var(--text-muted)] sm:order-1 sm:col-span-1"
                onPress={() => setShowDetails((prev) => !prev)}
                endContent={
                  showDetails
                    ? <ChevronUp className="w-3.5 h-3.5" aria-hidden="true" />
                    : <ChevronDown className="w-3.5 h-3.5" aria-hidden="true" />
                }
              >
                {showDetails
                  ? t('cookie_consent.hide_details')
                  : t('cookie_consent.manage')
                }
              </Button>

              <div className="flex-1 hidden sm:block order-2" />

              {showDetails ? (
                <Button
                  size="sm"
                  color="primary"
                  className="col-span-2 min-h-[44px] sm:order-3 sm:col-span-1"
                  onPress={handleSavePreferences}
                >
                  {t('cookie_consent.save')}
                </Button>
              ) : (
                <>
                  <Button
                    size="sm"
                    variant="flat"
                    className="min-h-[44px] bg-[var(--surface-elevated)] text-[var(--text-primary)] sm:order-3"
                    onPress={acceptEssentialOnly}
                  >
                    {t('cookie_consent.essential_only')}
                  </Button>
                  <Button
                    size="sm"
                    color="primary"
                    className="min-h-[44px] sm:order-4"
                    onPress={acceptAll}
                  >
                    {t('cookie_consent.accept_all')}
                  </Button>
                </>
              )}
            </div>
          </Card.Content>
        </Card>
      </motion.div>
    </AnimatePresence>
  );
}

export default CookieConsentBanner;
