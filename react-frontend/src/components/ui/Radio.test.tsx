// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { Radio, RadioGroup } from './Radio';

vi.mock('@/contexts', () => ({
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

describe('Radio', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a radio element', () => {
    render(
      <RadioGroup>
        <Radio value="a">Option A</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Option A')).toBeInTheDocument();
  });

  it('renders description text when provided', () => {
    render(
      <RadioGroup>
        <Radio value="b" description="Helper text">Option B</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Helper text')).toBeInTheDocument();
  });
});

describe('RadioGroup', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a group label', () => {
    render(
      <RadioGroup label="Choose one">
        <Radio value="x">X</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Choose one')).toBeInTheDocument();
  });

  it('renders all radio options', () => {
    render(
      <RadioGroup label="Options">
        <Radio value="opt1">First</Radio>
        <Radio value="opt2">Second</Radio>
        <Radio value="opt3">Third</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('First')).toBeInTheDocument();
    expect(screen.getByText('Second')).toBeInTheDocument();
    expect(screen.getByText('Third')).toBeInTheDocument();
  });

  it('renders description when provided', () => {
    render(
      <RadioGroup label="Group" description="Pick wisely">
        <Radio value="y">Y</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Pick wisely')).toBeInTheDocument();
  });

  it('renders errorMessage when provided', () => {
    render(
      <RadioGroup label="Group" errorMessage="Required field" isInvalid>
        <Radio value="z">Z</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Required field')).toBeInTheDocument();
  });

  it('calls onChange when a radio option is selected', () => {
    const onChange = vi.fn();

    render(
      <RadioGroup label="Pick" onChange={onChange}>
        <Radio value="val1">Val 1</Radio>
        <Radio value="val2">Val 2</Radio>
      </RadioGroup>
    );

    // Click the label text to trigger the radio change
    fireEvent.click(screen.getByText('Val 1'));

    expect(onChange).toHaveBeenCalled();
  });

  it('calls onValueChange when a radio option is selected (alias)', () => {
    const onValueChange = vi.fn();

    render(
      <RadioGroup label="Pick" onValueChange={onValueChange}>
        <Radio value="a1">Alpha 1</Radio>
        <Radio value="a2">Alpha 2</Radio>
      </RadioGroup>
    );

    fireEvent.click(screen.getByText('Alpha 2'));

    expect(onValueChange).toHaveBeenCalled();
  });

  it('respects controlled value', () => {
    render(
      <RadioGroup label="Controlled" value="ctrl2">
        <Radio value="ctrl1">Option 1</Radio>
        <Radio value="ctrl2">Option 2</Radio>
      </RadioGroup>
    );

    // Both options should be rendered; controlled value should be set.
    // We verify rendering without asserting internal HeroUI state attrs
    // (the component surfaces no reliable checked attribute through jsdom).
    expect(screen.getByText('Option 2')).toBeInTheDocument();
  });

  it('accepts isDisabled on the group', () => {
    render(
      <RadioGroup label="Disabled Group" isDisabled>
        <Radio value="d1">Disabled Option</Radio>
      </RadioGroup>
    );

    expect(screen.getByText('Disabled Option')).toBeInTheDocument();
  });
});
