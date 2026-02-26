// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Switch, Select, SelectItem } from '@heroui/react';
import type { VisibilityRules } from '@/types/menu';

const ROLE_OPTIONS = [
  { key: '', label: 'Any role' },
  { key: 'user', label: 'Member' },
  { key: 'admin', label: 'Admin' },
  { key: 'tenant_admin', label: 'Tenant Admin' },
  { key: 'super_admin', label: 'Super Admin' },
];

const FEATURE_OPTIONS = [
  { key: '', label: 'No feature requirement' },
  { key: 'events', label: 'Events' },
  { key: 'groups', label: 'Groups' },
  { key: 'gamification', label: 'Gamification' },
  { key: 'goals', label: 'Goals' },
  { key: 'blog', label: 'Blog' },
  { key: 'resources', label: 'Resources' },
  { key: 'volunteering', label: 'Volunteering' },
  { key: 'exchange_workflow', label: 'Exchange Workflow' },
  { key: 'organisations', label: 'Organisations' },
  { key: 'federation', label: 'Federation' },
  { key: 'connections', label: 'Connections' },
  { key: 'reviews', label: 'Reviews' },
  { key: 'polls', label: 'Polls' },
  { key: 'direct_messaging', label: 'Direct Messaging' },
  { key: 'group_exchanges', label: 'Group Exchanges' },
  { key: 'search', label: 'Search' },
];

interface VisibilityRulesEditorProps {
  value: VisibilityRules | null;
  onChange: (rules: VisibilityRules | null) => void;
}

export function VisibilityRulesEditor({ value, onChange }: VisibilityRulesEditorProps) {
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
      <p className="text-sm font-medium text-theme-primary">Visibility Rules</p>

      <Switch
        isSelected={rules.requires_auth ?? false}
        onValueChange={(checked) => updateRule('requires_auth', checked || undefined)}
        size="sm"
      >
        <span className="text-sm">Requires authentication</span>
      </Switch>

      <Select
        label="Minimum role"
        selectedKeys={rules.min_role ? [rules.min_role] : []}
        onSelectionChange={(keys) => {
          const selected = Array.from(keys)[0] as string;
          updateRule('min_role', (selected || undefined) as VisibilityRules['min_role']);
        }}
        size="sm"
        className="max-w-xs"
      >
        {ROLE_OPTIONS.map((opt) => (
          <SelectItem key={opt.key}>{opt.label}</SelectItem>
        ))}
      </Select>

      <Select
        label="Requires feature"
        selectedKeys={rules.requires_feature ? [rules.requires_feature] : []}
        onSelectionChange={(keys) => {
          const selected = Array.from(keys)[0] as string;
          updateRule('requires_feature', selected || undefined);
        }}
        size="sm"
        className="max-w-xs"
      >
        {FEATURE_OPTIONS.map((opt) => (
          <SelectItem key={opt.key}>{opt.label}</SelectItem>
        ))}
      </Select>
    </div>
  );
}
