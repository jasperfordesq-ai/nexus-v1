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
import { Button, Card, CardBody, Chip, Switch } from '@heroui/react';
import Cookie from 'lucide-react/icons/cookie';
import ChevronDown from 'lucide-react/icons/chevron-down';
import ChevronUp from 'lucide-react/icons/chevron-up';
import Shield from 'lucide-react/icons/shield';
import ExternalLink from 'lucide-react/icons/external-link';
import { motion, AnimatePresence } from 'framer-motion';
import { useCookieConsent } from '@/contexts/CookieConsentContext';
import { useTenant } from '@/contexts';
import { useTranslation } from 'react-i18next';
import { readStoredConsent } from '@/contexts/CookieConsentContext';

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
        className="fixed bottom-0 inset-x-0 z-[700] p-3 sm:p-4"
        style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom, 0px))' }}
        role="dialog"
        aria-label={t('cookie_consent.banner_label')}
        aria-modal="false"
        data-nosnippet
      >
        <Card
          className="mx-auto max-w-3xl border border-[var(--border-strong)] bg-[var(--surface-overlay)] shadow-2xl shadow-black/20 supports-[backdrop-filter]:bg-[var(--glass-bg)]"
          radius="lg"
          style={{
            backdropFilter: 'blur(var(--glass-blur)) saturate(var(--glass-saturate))',
            WebkitBackdropFilter: 'blur(var(--glass-blur)) saturate(var(--glass-saturate))',
          }}
        >
          <CardBody className="p-4 sm:p-5">
            {/* Header row */}
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-amber-400/20 bg-amber-500/15 shadow-sm">
                <Cookie className="w-5 h-5 text-amber-500" aria-hidden="true" />
              </div>
              <div className="flex-1 min-w-0">
                <h2 className="text-sm sm:text-base font-semibold text-[var(--text-primary)]">
                  {t('cookie_consent.title')}
                </h2>
                <p className="text-xs sm:text-sm text-[var(--text-muted)] mt-1 leading-relaxed">
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
                  <div className="mt-4 space-y-3 border-t border-[var(--border-default)] pt-4">
                    {/* Essential — always on */}
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--surface-elevated)] p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Shield className="w-4 h-4 text-emerald-500 flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.essential')}
                          </p>
                          <p className="text-xs text-[var(--text-subtle)]">
                            {t('cookie_consent.essential_desc')}
                          </p>
                        </div>
                      </div>
                      <Chip color="success" size="sm" variant="flat" className="shrink-0">
                        {t('cookie_consent.always_on')}
                      </Chip>
                    </div>

                    {/* Analytics */}
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--surface-elevated)] p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Cookie className="w-4 h-4 text-blue-500 flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.analytics')}
                          </p>
                          <p className="text-xs text-[var(--text-subtle)]">
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
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--surface-elevated)] p-3">
                      <div className="flex items-center gap-2.5 min-w-0">
                        <Cookie className="w-4 h-4 text-purple-500 flex-shrink-0" aria-hidden="true" />
                        <div>
                          <p className="text-sm font-medium text-[var(--text-primary)]">
                            {t('cookie_consent.preferences')}
                          </p>
                          <p className="text-xs text-[var(--text-subtle)]">
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
            <div className="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
              <Button
                size="sm"
                variant="light"
                className="text-[var(--text-muted)] text-xs order-3 sm:order-1"
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
                  className="order-1 sm:order-3"
                  onPress={handleSavePreferences}
                >
                  {t('cookie_consent.save')}
                </Button>
              ) : (
                <>
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-[var(--surface-elevated)] text-[var(--text-primary)] order-2 sm:order-3"
                    onPress={acceptEssentialOnly}
                  >
                    {t('cookie_consent.essential_only')}
                  </Button>
                  <Button
                    size="sm"
                    color="primary"
                    className="order-1 sm:order-4"
                    onPress={acceptAll}
                  >
                    {t('cookie_consent.accept_all')}
                  </Button>
                </>
              )}
            </div>
          </CardBody>
        </Card>
      </motion.div>
    </AnimatePresence>
  );
}

export default CookieConsentBanner;
