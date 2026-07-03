// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

// GrapesJS is imperative and needs a real canvas/iframe — mock it so the
// component's lifecycle (init on mount, destroy on unmount, 'update' wiring)
// can be asserted in jsdom. vi.hoisted keeps the mocks available inside the
// hoisted vi.mock factory.
const { editorMock, initMock } = vi.hoisted(() => {
  const editorMock = {
    on: vi.fn(),
    off: vi.fn(),
    destroy: vi.fn(),
    loadProjectData: vi.fn(),
    getProjectData: vi.fn(() => ({ pages: [] })),
    runCommand: vi.fn(() => ({ html: '<p>built</p>' })),
    AssetManager: { add: vi.fn() },
  };
  return { editorMock, initMock: vi.fn(() => editorMock) };
});

vi.mock('grapesjs', () => ({ default: { init: initMock } }));
vi.mock('grapesjs-mjml', () => ({ default: vi.fn() }));
vi.mock('grapesjs/dist/css/grapes.min.css', () => ({}));
vi.mock('@/contexts', () => ({ useToast: () => ({ error: vi.fn(), success: vi.fn() }) }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('../api/adminApi', () => ({ adminNewsletters: { uploadImage: vi.fn() } }));

import { NewsletterBuilder } from './NewsletterBuilder';

describe('NewsletterBuilder', () => {
  beforeEach(() => {
    initMock.mockClear();
    editorMock.on.mockClear();
    editorMock.off.mockClear();
    editorMock.destroy.mockClear();
    editorMock.loadProjectData.mockClear();
  });

  it('initializes GrapesJS on mount and subscribes to update', () => {
    render(<NewsletterBuilder html="" onChange={vi.fn()} />);
    expect(initMock).toHaveBeenCalledTimes(1);
    expect(editorMock.on).toHaveBeenCalledWith('update', expect.any(Function));
  });

  it('restores a saved design from design_json', () => {
    render(<NewsletterBuilder html="" designJson='{"pages":[1]}' onChange={vi.fn()} />);
    expect(editorMock.loadProjectData).toHaveBeenCalledWith({ pages: [1] });
  });

  it('destroys the editor on unmount', () => {
    const { unmount } = render(<NewsletterBuilder html="" onChange={vi.fn()} />);
    unmount();
    expect(editorMock.destroy).toHaveBeenCalled();
  });

  it('renders an error fallback when init throws', () => {
    initMock.mockImplementationOnce(() => {
      throw new Error('boom');
    });
    render(<NewsletterBuilder html="" onChange={vi.fn()} />);
    expect(
      screen.getByText('The visual builder could not load. Please use the HTML mode instead.'),
    ).toBeInTheDocument();
  });
});
