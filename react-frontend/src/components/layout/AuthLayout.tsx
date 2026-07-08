// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ChangeEvent } from 'react';
import { Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Github from 'lucide-react/icons/github';
import ExternalLink from 'lucide-react/icons/external-link';
import Globe from 'lucide-react/icons/globe';
import { useTenantLanguages } from '@/contexts/TenantContext';
import { safeLocalStorageSet } from '@/lib/safeStorage';

interface AuthLanguage {
  code: string;
  label: string;
  short: string;
}

const PROJECT_NEXUS_REPO_URL = 'https://github.com/jasperfordesq-ai/nexus-v1';

const AUTH_LANGUAGES: AuthLanguage[] = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Francais', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
  { code: 'pt', label: 'Portugues', short: 'PT' },
  { code: 'es', label: 'Espanol', short: 'ES' },
  { code: 'nl', label: 'Nederlands', short: 'NL' },
  { code: 'pl', label: 'Polski', short: 'PL' },
  { code: 'ja', label: 'Japanese', short: 'JA' },
  { code: 'ar', label: 'Arabic', short: 'AR' },
];
const DEFAULT_AUTH_LANGUAGE: AuthLanguage = { code: 'en', label: 'English', short: 'EN' };

/**
 * Auth Layout - simplified layout for auth pages (no navbar/footer).
 *
 * Kept in a separate module from the full app Layout so login/register startup
 * does not import the navbar, mobile drawer, footer, podcast player, push
 * notifications, session modals, or other authenticated-shell code.
 */
export function AuthLayout() {
  const { t, i18n } = useTranslation('common');
  const tenantLanguages = useTenantLanguages();
  const year = new Date().getFullYear();
  const supportedLanguages = AUTH_LANGUAGES.filter((language) => tenantLanguages.includes(language.code));
  const languageOptions = supportedLanguages.length > 0 ? supportedLanguages : [DEFAULT_AUTH_LANGUAGE];
  const currentLanguage = languageOptions.find((language) => language.code === i18n.language)
    ?? languageOptions[0]
    ?? DEFAULT_AUTH_LANGUAGE;

  const handleLanguageChange = (event: ChangeEvent<HTMLSelectElement>) => {
    const nextLanguage = event.target.value;
    void i18n.changeLanguage(nextLanguage);
    safeLocalStorageSet('nexus_language_user_chosen', 'true');
  };

  return (
    <div className="min-h-screen max-w-[100vw] flex flex-col overflow-x-clip">
      <a
        href="#main-content"
        className="sr-only focus-visible:not-sr-only focus-visible:absolute focus-visible:top-4 focus-visible:left-4 focus-visible:z-[9999] focus-visible:px-4 focus-visible:py-2 focus-visible:bg-[var(--color-primary)] focus-visible:text-white focus-visible:rounded-lg focus-visible:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
      >
        {t('accessibility.skip_to_content')}
      </a>

      <div className="absolute top-[calc(var(--safe-area-top)+1rem)] right-[calc(var(--safe-area-right)+1rem)] z-20">
        <label className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-2.5 py-2 text-xs font-semibold text-white shadow-sm backdrop-blur">
          <Globe className="h-4 w-4" aria-hidden="true" />
          <span className="sr-only">{t('aria.select_language')}</span>
          <select
            className="bg-transparent text-xs font-semibold text-white outline-none"
            value={currentLanguage.code}
            aria-label={t('aria.current_language', { language: currentLanguage.label })}
            onChange={handleLanguageChange}
          >
            {languageOptions.map((language) => (
              <option key={language.code} value={language.code} className="text-slate-950">
                {language.short}
              </option>
            ))}
          </select>
        </label>
      </div>

      <main id="main-content" className="relative z-10 flex-1">
        <Outlet />
      </main>

      <footer className="relative z-10 px-4 py-4 pb-[calc(var(--safe-area-bottom)+1rem)] text-center" data-nosnippet>
        <div className="flex flex-col items-center justify-center gap-2">
          <a
            href={PROJECT_NEXUS_REPO_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="group inline-flex min-h-[44px] max-w-[18rem] items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-white no-underline shadow-sm transition-colors hover:border-white/40 hover:bg-white/15"
            aria-label={t('footer.source_repo_aria')}
          >
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-white text-slate-950 shadow-sm">
              <Github className="h-4 w-4" aria-hidden="true" />
            </span>
            <span className="flex min-w-0 flex-col items-start leading-tight">
              <span className="max-w-full truncate text-sm font-semibold">
                {t('footer.project_nexus')}
              </span>
              <span className="max-w-full truncate text-[12px] font-medium text-white/70">
                {t('footer.source_repo')}
              </span>
            </span>
            <ExternalLink className="h-3.5 w-3.5 shrink-0 opacity-70 transition-opacity group-hover:opacity-100" aria-hidden="true" />
          </a>
          <p className="text-xs text-white/55">
            <span className="font-medium text-white/75">{t('footer.project_nexus')}</span>
            <span aria-hidden="true"> &middot; </span>
            <span>{t('footer.agpl_notice', { year })}</span>
          </p>
        </div>
      </footer>
    </div>
  );
}

export default AuthLayout;
