// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import { Switch, Select, SelectItem } from '@heroui/react';
import type { VisibilityRules } from '@/types/menu';

const ROLE_KEYS = ['', 'user', 'admin', 'tenant_admin', 'super_admin'];

const FEATURE_KEYS = [
  '', 'events', 'groups', 'gamification', 'goals', 'blog', 'resources',
  'volunteering', 'exchange_workflow', 'organisations', 'federation',
  'connections', 'reviews', 'polls', 'direct_messaging', 'group_exchanges', 'search',
];

interface VisibilityRulesEditorProps {
  value: VisibilityRules | null;
  onChange: (rules: VisibilityRules | null) => void;
}

export function VisibilityRulesEditor({ value, onChange }: VisibilityRulesEditorProps) {
  const { t } = useTranslation('admin');
  const rules = value || {};

  const updateRule = <K extends keyof VisibilityRules>(key: K, val: VisibilityRules[K]) => {
    const updated = { ...rules, [key]: val };

    // Clean up empty/falsy values
    if (!updated.requires_auth) delete updated.requires_auth;
    if (!updated.min_role) delete updated.min_role;
    if (!updated.requires_feature) delete updated.requires_feature;
    if (!updated.exclude_roles || updated.exclude_roles.length === 0) delete updated.exclude_roles;

    // If all rules removed, set to null
    const hasRules = Object.keys(updated).length > 0;
    onChange(hasRules ? updated : null);
  };

  return (
    <div className="space-y-4">
      <p className="text-sm font-medium text-theme-primary">{t('visibility_rules.title')}</p>

      <Switch
        isSelected={rules.requires_auth ?? false}
        onValueChange={(checked) => updateRule('requires_auth', checked || undefined)}
        size="sm"
      >
        <span className="text-sm">{t('visibility_rules.requires_auth')}</span>
      </Switch>

      <Select
        label={t('visibility_rules.min_role')}
        selectedKeys={rules.min_role ? [rules.min_role] : []}
        onSelectionChange={(keys) => {
          const selected = Array.from(keys)[0] as string;
          updateRule('min_role', (selected || undefined) as VisibilityRules['min_role']);
        }}
        size="sm"
        className="max-w-xs"
      >
        {ROLE_KEYS.map((key) => (
          <SelectItem key={key}>{t(`visibility_rules.roles.${key || 'any'}`)}</SelectItem>
        ))}
      </Select>

      <Select
        label={t('visibility_rules.requires_feature')}
        selectedKeys={rules.requires_feature ? [rules.requires_feature] : []}
        onSelectionChange={(keys) => {
          const selected = Array.from(keys)[0] as string;
          updateRule('requires_feature', selected || undefined);
        }}
        size="sm"
        className="max-w-xs"
      >
        {FEATURE_KEYS.map((key) => (
          <SelectItem key={key}>{t(`visibility_rules.features.${key || 'none'}`)}</SelectItem>
        ))}
      </Select>
    </div>
  );
}
