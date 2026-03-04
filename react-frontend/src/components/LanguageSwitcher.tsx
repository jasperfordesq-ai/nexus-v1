// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Language Switcher Component
 * Shows only the languages supported by the current tenant.
 * Reads tenant language config from TenantContext.
 * Stores preference in localStorage as 'nexus_language'.
 */

import { useTranslation } from 'react-i18next';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import { Globe } from 'lucide-react';
import { api, tokenManager } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTenantLanguages } from '@/contexts/TenantContext';

interface Language {
  code: string;
  label: string;
  /** Short display label shown in the trigger button */
  short: string;
}

/**
 * All languages the platform supports. Only those present in the tenant's
 * supported_languages config will be shown to the user.
 */
const ALL_LANGUAGES: Language[] = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Français', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
  { code: 'pt', label: 'Português', short: 'PT' },
  { code: 'es', label: 'Español', short: 'ES' },
];

interface LanguageSwitcherProps {
  /** Compact mode: show only icon + short code. Default: true */
  compact?: boolean;
}

export function LanguageSwitcher({ compact = true }: LanguageSwitcherProps) {
  const { i18n } = useTranslation();
  const tenantLanguages = useTenantLanguages();

  // Only show languages this tenant supports
  const supportedLanguages = ALL_LANGUAGES.filter(l => tenantLanguages.includes(l.code));

  // If current language isn't in the tenant's list, fall back to the first supported one
  const currentLang = supportedLanguages.find((l) => l.code === i18n.language)
    ?? supportedLanguages[0];

  const handleLanguageChange = (code: string) => {
    i18n.changeLanguage(code);
    // Mark that the user explicitly chose a language (not auto-detected).
    // TenantContext checks this flag to decide whether to apply the tenant default.
    localStorage.setItem('nexus_language_user_chosen', 'true');

    // Persist to user profile if authenticated
    if (tokenManager.hasAccessToken()) {
      api.put('/v2/users/me/language', { language: code }).catch((err) => {
        logError('Failed to persist language preference', err);
      });
    }
  };

  return (
    <Dropdown placement="bottom-end" shouldBlockScroll={false}>
      <DropdownTrigger>
        <Button
          variant="light"
          size="sm"
          className="text-theme-muted hover:text-theme-primary gap-1 min-w-0"
          aria-label={`Language: ${currentLang.label}`}
          startContent={<Globe className="w-4 h-4 shrink-0" aria-hidden="true" />}
        >
          {compact ? (
            <span className="text-xs font-medium">{currentLang.short}</span>
          ) : (
            <span className="text-sm">{currentLang.label}</span>
          )}
        </Button>
      </DropdownTrigger>
      <DropdownMenu
        aria-label="Select language"
        classNames={{
          base: 'bg-[var(--surface-overlay)] border border-[var(--border-default)] shadow-xl min-w-[140px]',
        }}
        selectedKeys={new Set([currentLang.code])}
        selectionMode="single"
        onAction={(key) => handleLanguageChange(String(key))}
      >
        {supportedLanguages.map((lang) => (
          <DropdownItem
            key={lang.code}
            className={lang.code === currentLang.code ? 'bg-theme-active' : ''}
          >
            <span className="font-medium text-xs text-theme-subtle mr-2">{lang.short}</span>
            <span>{lang.label}</span>
          </DropdownItem>
        ))}
      </DropdownMenu>
    </Dropdown>
  );
}

export default LanguageSwitcher;
