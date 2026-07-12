// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
import Settings2 from 'lucide-react/icons/settings-2';

vi.mock('@/contexts', () => createMockContexts());

import ModuleCard from './ModuleCard';
import type { ModuleDefinition } from './moduleRegistry';

// A minimal module with some config options (not comingSoon)
const baseMod: ModuleDefinition = {
  id: 'listings',
  name: 'Listings',
  description: 'Service offers and requests marketplace',
  icon: Settings2,
  type: 'core',
  configSource: 'listing_config',
  configOptions: [
    { key: 'opt1', label: 'Option 1', description: 'Desc', type: 'boolean', defaultValue: true, category: 'general' },
    { key: 'opt2', label: 'Option 2', description: 'Desc', type: 'boolean', defaultValue: false, category: 'general', comingSoon: true },
  ],
};

// A module with no config options
const emptyOptsMod: ModuleDefinition = {
  ...baseMod,
  id: 'feed',
  name: 'Feed',
  description: 'Activity feed',
  type: 'feature',
  configOptions: [],
};

describe('ModuleCard', () => {
  const onToggle = vi.fn();
  const onConfigure = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the module name', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    expect(screen.getByText('Listings')).toBeInTheDocument();
  });

  it('renders the module description', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    expect(screen.getByText('Service offers and requests marketplace')).toBeInTheDocument();
  });

  it('renders a Switch (toggle) element', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    // HeroUI Switch renders a checkbox role
    expect(screen.getByRole('switch')).toBeInTheDocument();
  });

  it('calls onToggle when the switch is clicked', async () => {
    const user = userEvent.setup();
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    const sw = screen.getByRole('switch');
    await user.click(sw);
    expect(onToggle).toHaveBeenCalledWith('listings', expect.any(Boolean));
  });

  it('disables the switch when toggling=true', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling onToggle={onToggle} onConfigure={onConfigure} />
    );
    expect(screen.getByRole('switch')).toBeDisabled();
  });

  it('shows the configure button when configOptions exist', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    // configure button text comes from i18n key 'config.configure'
    expect(screen.getByRole('button', { name: /configure/i })).toBeInTheDocument();
  });

  it('calls onConfigure when the configure button is pressed', async () => {
    const user = userEvent.setup();
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    await user.click(screen.getByRole('button', { name: /configure/i }));
    expect(onConfigure).toHaveBeenCalledWith(baseMod);
  });

  it('allows the footer controls to wrap without overlapping', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    const footer = screen.getByRole('button', { name: /configure/i }).parentElement;
    expect(footer).toHaveClass('flex-wrap');
    expect(screen.getByRole('button', { name: /configure/i })).toHaveClass('shrink-0');
  });

  it('hides the configure button when no configOptions', () => {
    render(
      <ModuleCard module={emptyOptsMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    expect(screen.queryByRole('button', { name: /configure/i })).not.toBeInTheDocument();
  });

  it('shows a stage chip when module.stage is set', () => {
    const betaMod: ModuleDefinition = { ...baseMod, stage: 'beta' };
    render(
      <ModuleCard module={betaMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    // The chip renders the i18n key 'config.stage_beta'
    expect(screen.getByText(/beta/i)).toBeInTheDocument();
  });

  it('shows "core" chip for core modules', () => {
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    expect(screen.getByText(/core/i)).toBeInTheDocument();
  });

  it('applies reduced opacity when module is disabled', () => {
    const { container } = render(
      <ModuleCard module={baseMod} enabled={false} toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    // The CardBody carries `opacity-60` when disabled
    const cardBody = container.querySelector('.opacity-60');
    expect(cardBody).not.toBeNull();
  });

  it('shows only live (non-comingSoon) options in the count chip', () => {
    // baseMod has 2 options, 1 comingSoon → liveCount = 1
    render(
      <ModuleCard module={baseMod} enabled toggling={false} onToggle={onToggle} onConfigure={onConfigure} />
    );
    // The chip should mention "1" option
    expect(screen.getByText(/1/)).toBeInTheDocument();
  });
});
