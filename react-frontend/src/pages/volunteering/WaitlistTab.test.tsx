// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for WaitlistTab
 */

import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@/test/test-utils";
import React from "react";
vi.mock("framer-motion", () => ({
  motion: new Proxy({}, {
    get: (_target: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...props }: Record<string, unknown>, ref: React.Ref<HTMLElement>) => {
        const motionPropNames = ["variants","initial","animate","exit","transition","whileHover","whileTap","whileInView","layout","layoutId","viewport"];
        const clean: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(props)) {
          if (!motionPropNames.includes(k)) clean[k] = v;
        }
        const tag = typeof prop === "string" ? prop : "div";
        return React.createElement(tag, { ...clean, ref }, children);
      });
    },
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
  useAnimation: () => ({ start: () => Promise.resolve() }),
  useInView: () => true,
  useMotionValue: (initial: number) => ({ get: () => initial, set: () => {} }),
  useTransform: () => ({ get: () => 0 }),
  useSpring: () => ({ get: () => 0 }),
}));

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (_key: string, fallback: string, _opts?: object) => {
      if (fallback && _opts) {
        // handle interpolation like Position {{position}}
        return fallback.replace(/\{\{(\w+)\}\}/g, (_: string, k: string) => String((_opts as Record<string,unknown>)[k] ?? ""));
      }
      return fallback ?? _key;
    },
  }),
}));

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock("@/contexts", () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock("@/components/ui", () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock("@/components/feedback", () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock("@/lib/logger", () => ({
  logError: vi.fn(),
}));

import { WaitlistTab } from "./WaitlistTab";
import { api } from "@/lib/api";

const mockEntry = {
  id: 1,
  position: 2,
  shift: {
    id: 10,
    start_time: "2026-03-15T10:00:00Z",
    end_time: "2026-03-15T14:00:00Z",
    capacity: 5,
  },
  opportunity: {
    id: 3,
    title: "Community Kitchen Helper",
    location: "Cork City",
  },
  organization: {
    id: 7,
    name: "Food For All",
    logo_url: null,
  },
  joined_at: "2026-03-08T09:00:00Z",
};

describe("WaitlistTab", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders the heading", () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<WaitlistTab />);
    expect(screen.getByText("My Waitlists")).toBeInTheDocument();
  });

  it("shows empty state when there are no waitlist entries", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<WaitlistTab />);
    await waitFor(() => {
      expect(screen.getByText("No waitlist entries")).toBeInTheDocument();
    });
  });

  it("renders waitlist entry card with opportunity title, org name, and position chip", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockEntry] });
    render(<WaitlistTab />);
    await waitFor(() => {
      expect(screen.getByText("Community Kitchen Helper")).toBeInTheDocument();
    });
    expect(screen.getByText("Food For All")).toBeInTheDocument();
    expect(screen.getByText("Position 2")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Leave/i })).toBeInTheDocument();
  });

  it("opens confirmation modal when Leave button is pressed", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockEntry] });
    render(<WaitlistTab />);
    await waitFor(() => screen.getByRole("button", { name: /Leave/i }));

    fireEvent.click(screen.getByRole("button", { name: /Leave/i }));
    await waitFor(() => {
      expect(
        screen.getByText(/Are you sure you want to leave this waitlist/),
      ).toBeInTheDocument();
    });
  });

  it("calls DELETE endpoint when leave is confirmed", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockEntry] });
    vi.mocked(api.delete).mockResolvedValue({ success: true });
    render(<WaitlistTab />);
    await waitFor(() => screen.getByRole("button", { name: /Leave/i }));

    // Open the confirmation modal
    fireEvent.click(screen.getByRole("button", { name: /Leave/i }));
    await waitFor(() => screen.getByText(/Are you sure you want to leave this waitlist/));

    // Confirm via the danger Leave button in the modal footer
    const leaveButtons = screen.getAllByRole("button", { name: /Leave/i });
    fireEvent.click(leaveButtons[leaveButtons.length - 1]);
    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith("/v2/volunteering/shifts/10/waitlist");
    });
  });

  it("shows error state when API fails", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<WaitlistTab />);
    await waitFor(() => {
      expect(
        screen.getByText("Unable to load your waitlist entries. Please try again."),
      ).toBeInTheDocument();
    });
  });
});
