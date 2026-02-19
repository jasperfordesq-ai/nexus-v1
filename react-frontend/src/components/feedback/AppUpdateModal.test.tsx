// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { AppUpdateModal } from './AppUpdateModal';

vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({ children, initial, animate, exit, transition, variants, whileHover, whileTap, ...rest }: any) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  };
  return {
    motion: new Proxy({}, handler),
    AnimatePresence: ({ children }: any) => children,
    MotionConfig: ({ children }: any) => children,
  };
});

const mockUpdateInfo = {
  currentVersion: '2.0.0',
  clientVersion: '1.5.0',
  forceUpdate: false,
  updateUrl: 'https://example.com/download',
  updateMessage: 'A new version is available.',
  releaseNotes: {
    features: ['New dashboard', 'Dark mode improvements'],
    fixes: ['Bug fix for login'],
  },
};

describe('AppUpdateModal', () => {
  it('renders update modal with version info', () => {
    render(<AppUpdateModal updateInfo={mockUpdateInfo} onDismiss={vi.fn()} />);
    expect(screen.getByText('Update Available')).toBeInTheDocument();
    expect(screen.getByText('A new version is available.')).toBeInTheDocument();
  });

  it('displays version numbers', () => {
    render(<AppUpdateModal updateInfo={mockUpdateInfo} onDismiss={vi.fn()} />);
    expect(screen.getByText(/Version 2.0.0.*you have 1.5.0/)).toBeInTheDocument();
  });

  it('shows release notes', () => {
    render(<AppUpdateModal updateInfo={mockUpdateInfo} onDismiss={vi.fn()} />);
    expect(screen.getByText('New dashboard')).toBeInTheDocument();
    expect(screen.getByText('Dark mode improvements')).toBeInTheDocument();
    expect(screen.getByText('Bug fix for login')).toBeInTheDocument();
  });

  it('shows Later button when not force update', () => {
    render(<AppUpdateModal updateInfo={mockUpdateInfo} onDismiss={vi.fn()} />);
    expect(screen.getByText('Later')).toBeInTheDocument();
  });

  it('hides Later button when force update', () => {
    const forceUpdate = { ...mockUpdateInfo, forceUpdate: true };
    render(<AppUpdateModal updateInfo={forceUpdate} onDismiss={vi.fn()} />);
    expect(screen.queryByText('Later')).not.toBeInTheDocument();
  });

  it('shows Download Update button', () => {
    render(<AppUpdateModal updateInfo={mockUpdateInfo} onDismiss={vi.fn()} />);
    expect(screen.getByText('Download Update')).toBeInTheDocument();
  });
});
