// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Language Switcher Component
 * Toggles between English (en) and Irish/Gaeilge (ga)
 * Stores preference in localStorage as 'nexus_language'
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

interface Language {
  code: string;
  label: string;
  /** Short display label shown in the trigger button */
  short: string;
}

const SUPPORTED_LANGUAGES: Language[] = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
];

interface LanguageSwitcherProps {
  /** Compact mode: show only icon + short code. Default: true */
  compact?: boolean;
}

export function LanguageSwitcher({ compact = true }: LanguageSwitcherProps) {
  const { i18n } = useTranslation();
  const currentLang = SUPPORTED_LANGUAGES.find((l) => l.code === i18n.language)
    ?? SUPPORTED_LANGUAGES[0];

  const handleLanguageChange = (code: string) => {
    i18n.changeLanguage(code);
    // localStorage is handled by i18next-browser-languagedetector via caches config

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
        {SUPPORTED_LANGUAGES.map((lang) => (
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
