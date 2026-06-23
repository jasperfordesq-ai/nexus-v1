// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(), post: vi.fn(), put: vi.fn(),
    patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub HeroUI Select / Switch (jsdom can't render React Aria collection) ──
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    // Switch: render a native checkbox so we can test onValueChange
    Switch: ({
      children,
      isSelected,
      onValueChange,
      size: _size,
    }: {
      children?: React.ReactNode;
      isSelected?: boolean;
      onValueChange?: (v: boolean) => void;
      size?: string;
    }) => (
      <label>
        <input
          type="checkbox"
          checked={!!isSelected}
          onChange={(e) => onValueChange?.(e.target.checked)}
          data-testid="switch-requires-auth"
        />
        {children}
      </label>
    ),
    // Select: render a native <select> so we can test onSelectionChange
    Select: ({
      children,
      label,
      selectedKeys,
      onSelectionChange,
      size: _size,
      className: _className,
    }: {
      children?: React.ReactNode;
      label?: string;
      selectedKeys?: Iterable<string>;
      onSelectionChange?: (keys: Set<string>) => void;
      size?: string;
      className?: string;
    }) => {
      const current = selectedKeys ? Array.from(selectedKeys)[0] ?? '' : '';
      return (
        <div>
          {label && <label>{label}</label>}
          <select
            data-testid={`select-${(label ?? '').toLowerCase().replace(/\s+/g, '-')}`}
            value={current}
            onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}
          >
            {children}
          </select>
        </div>
      );
    },
    // SelectItem: render a native <option>
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id ?? ''}>{children}</option>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
import type { VisibilityRules } from '@/types/menu';

// ─────────────────────────────────────────────────────────────────────────────
describe('VisibilityRulesEditor', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the "Visibility Rules" section title', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Visibility Rules')).toBeInTheDocument();
  });

  it('renders the Requires Auth switch', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={vi.fn()} />);
    expect(screen.getByTestId('switch-requires-auth')).toBeInTheDocument();
    expect(screen.getByText('Requires Auth')).toBeInTheDocument();
  });

  it('switch is unchecked when value is null', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={vi.fn()} />);
    const sw = screen.getByTestId('switch-requires-auth') as HTMLInputElement;
    expect(sw.checked).toBe(false);
  });

  it('switch is checked when requires_auth is true', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={{ requires_auth: true }} onChange={vi.fn()} />);
    const sw = screen.getByTestId('switch-requires-auth') as HTMLInputElement;
    expect(sw.checked).toBe(true);
  });

  it('calls onChange with requires_auth:true when switch is turned on', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={onChange} />);
    const sw = screen.getByTestId('switch-requires-auth');
    fireEvent.click(sw);
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ requires_auth: true }));
  });

  it('calls onChange with null when all rules are cleared', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    // Start with only requires_auth set; toggling it off should produce null (no rules)
    render(<VisibilityRulesEditor value={{ requires_auth: true }} onChange={onChange} />);
    const sw = screen.getByTestId('switch-requires-auth');
    fireEvent.click(sw);
    // requires_auth becomes undefined → empty object → onChange(null)
    expect(onChange).toHaveBeenCalledWith(null);
  });

  it('renders the Min Role select with all role options', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Min Role')).toBeInTheDocument();
    expect(screen.getByText('Any')).toBeInTheDocument();
    expect(screen.getByText('User')).toBeInTheDocument();
    expect(screen.getByText('Admin')).toBeInTheDocument();
    expect(screen.getByText('Tenant Admin')).toBeInTheDocument();
    expect(screen.getByText('Super Admin')).toBeInTheDocument();
  });

  it('renders the Requires Feature select with all feature options', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Requires Feature')).toBeInTheDocument();
    expect(screen.getByText('None')).toBeInTheDocument();
    expect(screen.getByText('Events')).toBeInTheDocument();
    expect(screen.getByText('Groups')).toBeInTheDocument();
    expect(screen.getByText('Federation')).toBeInTheDocument();
  });

  it('calls onChange with min_role when a role is selected', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={onChange} />);
    const roleSelect = screen.getByTestId('select-min-role');
    fireEvent.change(roleSelect, { target: { value: 'admin' } });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ min_role: 'admin' }));
  });

  it('calls onChange with requires_feature when a feature is selected', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={null} onChange={onChange} />);
    const featureSelect = screen.getByTestId('select-requires-feature');
    fireEvent.change(featureSelect, { target: { value: 'events' } });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ requires_feature: 'events' }));
  });

  it('displays existing min_role value as the selected option', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={{ min_role: 'tenant_admin' }} onChange={vi.fn()} />);
    const roleSelect = screen.getByTestId('select-min-role') as HTMLSelectElement;
    expect(roleSelect.value).toBe('tenant_admin');
  });

  it('displays existing requires_feature value as the selected option', async () => {
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={{ requires_feature: 'groups' }} onChange={vi.fn()} />);
    const featureSelect = screen.getByTestId('select-requires-feature') as HTMLSelectElement;
    expect(featureSelect.value).toBe('groups');
  });

  it('calls onChange with null when feature is cleared (empty selection)', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    render(<VisibilityRulesEditor value={{ requires_feature: 'events' }} onChange={onChange} />);
    const featureSelect = screen.getByTestId('select-requires-feature');
    // Select empty option → requires_feature becomes undefined → if no other rules, null
    fireEvent.change(featureSelect, { target: { value: '' } });
    expect(onChange).toHaveBeenCalledWith(null);
  });

  it('preserves other rules when one rule is updated', async () => {
    const onChange = vi.fn();
    const { VisibilityRulesEditor } = await import('./VisibilityRulesEditor');
    const initial: VisibilityRules = { requires_auth: true, min_role: 'user' };
    render(<VisibilityRulesEditor value={initial} onChange={onChange} />);
    const featureSelect = screen.getByTestId('select-requires-feature');
    fireEvent.change(featureSelect, { target: { value: 'blog' } });
    const called = onChange.mock.calls[0][0] as VisibilityRules;
    expect(called.requires_auth).toBe(true);
    expect(called.min_role).toBe('user');
    expect(called.requires_feature).toBe('blog');
  });
});
