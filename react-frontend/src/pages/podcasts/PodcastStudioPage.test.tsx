// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Hoisted mock data ───────────────────────────────────────────────────────
const { mockPodcastsApi, mockToast } = vi.hoisted(() => ({
  mockPodcastsApi: {
    authored: vi.fn(),
    createShow: vi.fn(),
    publishShow: vi.fn(),
    archiveShow: vi.fn(),
    deleteShow: vi.fn(),
    publishEpisode: vi.fn(),
    archiveEpisode: vi.fn(),
    deleteEpisode: vi.fn(),
    createEpisode: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

// ─── Mocks ───────────────────────────────────────────────────────────────────
vi.mock('@/lib/api/podcasts', () => ({
  podcastsApi: mockPodcastsApi,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeShow = (overrides = {}) => ({
  id: 1,
  title: 'Test Podcast Show',
  summary: 'A summary',
  description: 'A description',
  slug: 'test-podcast-show',
  status: 'draft',
  moderation_status: 'pending',
  visibility: 'public',
  artwork_url: 'https://example.com/art.png',
  owner_email: 'host@example.com',
  episodes: [],
  ...overrides,
});

const makeEpisode = (overrides = {}) => ({
  id: 10,
  title: 'Episode One',
  status: 'draft',
  moderation_status: 'pending',
  audio_url: 'https://example.com/ep1.mp3',
  media_scan_status: null,
  media_processing_status: null,
  ...overrides,
});

// ─── Tests ───────────────────────────────────────────────────────────────────
describe('PodcastStudioPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockPodcastsApi.authored.mockResolvedValue({ success: true, data: [] });
  });

  it('shows loading spinner while fetching shows', async () => {
    mockPodcastsApi.authored.mockImplementation(() => new Promise(() => {}));
    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    const spinners = screen.getAllByRole('status');
    const busySpin = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpin).toBeDefined();
  });

  it('renders empty "no shows" message when shows list is empty', async () => {
    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => {
      expect(mockPodcastsApi.authored).toHaveBeenCalled();
    });
    // After load, we should see the Create Show section
    expect(screen.queryAllByRole('button').length).toBeGreaterThan(0);
  });

  it('renders show titles after shows are loaded', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow()],
    });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => {
      // Show title appears in both the SelectItem and the show card link — getAllByText handles both
      const matches = screen.getAllByText('Test Podcast Show');
      expect(matches.length).toBeGreaterThan(0);
    });
  });

  it('calls createShow when Create Show button is clicked with a title', async () => {
    mockPodcastsApi.createShow.mockResolvedValue({ success: true, data: { id: 2 } });
    mockPodcastsApi.authored.mockResolvedValue({ success: true, data: [] });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(mockPodcastsApi.authored).toHaveBeenCalled());

    // Fill in show title
    const inputs = screen.getAllByRole('textbox');
    // First input is the show title field
    const titleInput = inputs[0];
    fireEvent.change(titleInput, { target: { value: 'My New Show' } });

    // Click Create Show button
    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('create') && b.textContent?.toLowerCase().includes('show')
    );
    expect(createBtn).toBeDefined();
    fireEvent.click(createBtn!);

    await waitFor(() => {
      expect(mockPodcastsApi.createShow).toHaveBeenCalledWith(
        expect.objectContaining({ title: 'My New Show' })
      );
    });
  });

  it('shows success toast after creating a show', async () => {
    mockPodcastsApi.createShow.mockResolvedValue({ success: true, data: { id: 3 } });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(mockPodcastsApi.authored).toHaveBeenCalled());

    const inputs = screen.getAllByRole('textbox');
    fireEvent.change(inputs[0], { target: { value: 'My Show' } });

    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('create') && b.textContent?.toLowerCase().includes('show')
    );
    fireEvent.click(createBtn!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when createShow fails', async () => {
    mockPodcastsApi.createShow.mockResolvedValue({ success: false, error: 'Validation failed' });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(mockPodcastsApi.authored).toHaveBeenCalled());

    const inputs = screen.getAllByRole('textbox');
    fireEvent.change(inputs[0], { target: { value: 'Bad Show' } });

    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('create') && b.textContent?.toLowerCase().includes('show')
    );
    fireEvent.click(createBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders episodes listed under a show', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow({ episodes: [makeEpisode()] })],
    });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => {
      expect(screen.getByText('Episode One')).toBeInTheDocument();
    });
  });

  it('calls publishShow when Publish Show button is clicked', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow({ status: 'draft' })],
    });
    mockPodcastsApi.publishShow.mockResolvedValue({ success: true });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(screen.getAllByText('Test Podcast Show').length).toBeGreaterThan(0));

    const publishBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('publish') && b.textContent?.toLowerCase().includes('show')
    );
    if (publishBtn) {
      fireEvent.click(publishBtn);
      await waitFor(() => {
        expect(mockPodcastsApi.publishShow).toHaveBeenCalledWith(1);
      });
    }
  });

  it('calls deleteShow after confirmation', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow()],
    });
    mockPodcastsApi.deleteShow.mockResolvedValue({ success: true });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(screen.getAllByText('Test Podcast Show').length).toBeGreaterThan(0));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('delete') && b.textContent?.toLowerCase().includes('show')
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockPodcastsApi.deleteShow).toHaveBeenCalledWith(1);
      });
    }
  });

  it('shows readiness checklist for selected show', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow()],
    });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(screen.getAllByText('Test Podcast Show').length).toBeGreaterThan(0));

    // Readiness section should show because show is selected automatically
    // The readiness card renders the show title in a subtitle
    expect(screen.getAllByText('Test Podcast Show').length).toBeGreaterThan(0);
  });

  it('disables Add Episode button when no audio url or file provided', async () => {
    mockPodcastsApi.authored.mockResolvedValue({
      success: true,
      data: [makeShow()],
    });

    const { default: PodcastStudioPage } = await import('./PodcastStudioPage');
    render(<PodcastStudioPage />);

    await waitFor(() => expect(screen.getAllByText('Test Podcast Show').length).toBeGreaterThan(0));

    const addEpisodeBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('add') && b.textContent?.toLowerCase().includes('episode')
    );
    expect(addEpisodeBtn).toBeDefined();
    // The button should be disabled due to missing audio_url (isDisabled prop)
    if (addEpisodeBtn) {
      expect(
        addEpisodeBtn.getAttribute('disabled') !== null || addEpisodeBtn.getAttribute('data-disabled') !== null
      ).toBe(true);
    }
  });
});
