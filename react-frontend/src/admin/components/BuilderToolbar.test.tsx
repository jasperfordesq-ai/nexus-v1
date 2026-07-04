// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen, userEvent } from '@/test/test-utils';
import { BuilderToolbar, type BuilderDevice } from './BuilderToolbar';

// Identity `t` so aria-labels are the raw keys — easy to target.
const t = (k: string) => k;

function setup(overrides: Record<string, unknown> = {}) {
  const handlers = {
    onUndo: vi.fn(),
    onRedo: vi.fn(),
    onSetDevice: vi.fn(),
    onToggleBorders: vi.fn(),
    onViewCode: vi.fn(),
    onClear: vi.fn(),
  };
  render(
    <BuilderToolbar
      ready
      readOnly={false}
      device={'Desktop' as BuilderDevice}
      showBorders={false}
      canUndo
      canRedo
      t={t}
      {...handlers}
      {...overrides}
    />,
  );
  return handlers;
}

const label = (k: string) => `newsletter_content_editor.${k}`;

describe('BuilderToolbar', () => {
  it('renders labelled controls (no icon-less blank squares)', () => {
    setup();
    for (const key of ['tip_undo', 'tip_redo', 'tip_device_desktop', 'tip_device_mobile', 'tip_borders', 'tip_code', 'tip_clear']) {
      expect(screen.getByRole('button', { name: label(key) })).toBeInTheDocument();
    }
  });

  it('drives editor actions from our own buttons', async () => {
    const user = userEvent.setup();
    const h = setup();
    await user.click(screen.getByRole('button', { name: label('tip_undo') }));
    expect(h.onUndo).toHaveBeenCalledTimes(1);
    await user.click(screen.getByRole('button', { name: label('tip_device_desktop') }));
    expect(h.onSetDevice).toHaveBeenCalledWith('Desktop');
    await user.click(screen.getByRole('button', { name: label('tip_code') }));
    expect(h.onViewCode).toHaveBeenCalledTimes(1);
  });

  it('disables undo when there is nothing to undo', () => {
    setup({ canUndo: false });
    expect(screen.getByRole('button', { name: label('tip_undo') })).toBeDisabled();
  });

  it('freezes mutating controls when readOnly (already-sent newsletter)', () => {
    setup({ readOnly: true });
    expect(screen.getByRole('button', { name: label('tip_clear') })).toBeDisabled();
    expect(screen.getByRole('button', { name: label('tip_undo') })).toBeDisabled();
  });
});
