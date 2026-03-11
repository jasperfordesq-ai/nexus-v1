// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CreateOpportunityPage
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
    t: (key: string, fallback?: string | Record<string, unknown>) => {
      const translations: Record<string, string> = {
        "volunteering.create_opportunity_title": "Post Volunteer Opportunity",
        "volunteering.create_opportunity_subtitle": "Create a new volunteering opportunity for your organisation",
        "volunteering.heading": "Volunteering",
        "volunteering.no_approved_orgs_title": "No Approved Organisation",
        "volunteering.no_approved_orgs_description": "You need an approved organisation to post volunteer opportunities.",
        "volunteering.register_org_link": "Register an Organisation",
        "volunteering.form_cancel": "Cancel",
        "volunteering.form_title_label": "Title",
        "volunteering.form_desc_label": "Description",
        "volunteering.form_submit": "Publish Opportunity",
        "volunteering.form_location_label": "Location",
        "volunteering.form_skills_label": "Skills Required",
        "volunteering.form_capacity_label": "Capacity",
        "volunteering.form_start_date": "Start Date",
        "volunteering.form_end_date": "End Date",
        "volunteering.form_remote": "Remote / Online",
        "volunteering.form_category": "Category",
        "volunteering.form_org_label": "Organisation",
      };
      if (typeof fallback === "string") return translations[key] ?? fallback;
      return translations[key] ?? key;
    },
    i18n: { language: "en", changeLanguage: vi.fn() },
  }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
  initReactI18next: { type: "3rdParty", init: vi.fn() },
}));

vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual("react-router-dom");
  return {
    ...actual,
    useNavigate: () => vi.fn(),
  };
});

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true, data: { id: 99 } }),
  },
}));

vi.mock("@/contexts", () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: "Test Tenant", slug: "test" },
    tenantPath: (p: string) => "/test" + p,
    hasFeature: vi.fn(() => true),
  })),
}));

vi.mock("@/hooks", () => ({
  usePageTitle: vi.fn(),
}));

vi.mock("@/components/ui", () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock("@/components/navigation", () => ({
  Breadcrumbs: ({ items }: { items: { label: string; href?: string }[] }) => (
    <nav data-testid="breadcrumbs">
      {items.map((item, i) => (
        <span key={i}>{item.label}</span>
      ))}
    </nav>
  ),
}));

vi.mock("@/components/feedback", () => ({
  LoadingScreen: ({ message }: { message: string }) => (
    <div data-testid="loading-screen">{message}</div>
  ),
}));

vi.mock("@/components/location", () => ({
  PlaceAutocompleteInput: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
  }) => (
    <input
      aria-label={label}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      data-testid="place-autocomplete"
    />
  ),
}));

vi.mock("@/lib/logger", () => ({
  logError: vi.fn(),
}));

import { CreateOpportunityPage } from "./CreateOpportunityPage";
import { api } from "@/lib/api";

const mockApprovedOrg = {
  id: 1,
  name: "Helping Hands",
  status: "approved",
  member_role: "owner",
};

describe("CreateOpportunityPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows loading screen while organisations are being fetched", () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<CreateOpportunityPage />);
    expect(screen.getByTestId("loading-screen")).toBeInTheDocument();
  });

  it("shows no-approved-orgs message when user has no approved organisations", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CreateOpportunityPage />);
    await waitFor(() => {
      expect(screen.queryByTestId("loading-screen")).not.toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /Register.*Organisation/i })).toBeInTheDocument();
  });

  it("renders the form when user has an approved organisation", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockApprovedOrg] });
    render(<CreateOpportunityPage />);
    await waitFor(() => {
      expect(screen.queryByTestId("loading-screen")).not.toBeInTheDocument();
    });
    expect(screen.getAllByText("Post Volunteer Opportunity").length).toBeGreaterThan(0);
    expect(screen.getByDisplayValue("Helping Hands")).toBeInTheDocument();
  });

  it("does not call POST when form is submitted with no title", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockApprovedOrg] });
    render(<CreateOpportunityPage />);
    await waitFor(() => screen.getAllByText("Post Volunteer Opportunity").length > 0);

    const submitBtn = screen.getByRole("button", { name: /Publish Opportunity|Submit|Save/i });
    fireEvent.click(submitBtn);
    await waitFor(() => {
      expect(api.post).not.toHaveBeenCalled();
    });
  });

  it("calls POST with correct payload after valid form submission", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockApprovedOrg] });
    vi.mocked(api.post).mockResolvedValue({ success: true, data: { id: 42 } });
    render(<CreateOpportunityPage />);
    await waitFor(() => screen.getAllByText("Post Volunteer Opportunity").length > 0);

    // Find title input and fill it
    const inputs = screen.getAllByRole("textbox");
    const titleInput = inputs.find(
      (el) => el.getAttribute("placeholder") && /title|opportunit/i.test(el.getAttribute("placeholder") ?? ""),
    );
    if (titleInput) {
      fireEvent.change(titleInput, { target: { value: "Help at the food bank" } });
    } else {
      // Fallback: find by label text
      const allInputs = document.querySelectorAll("input[type=text], input:not([type])");
      allInputs.forEach((inp) => {
        if ((inp as HTMLInputElement).readOnly === false) {
          fireEvent.change(inp, { target: { value: "Help at the food bank" } });
        }
      });
    }

    const submitBtn = screen.getByRole("button", { name: /Publish Opportunity|Submit|Save/i });
    fireEvent.click(submitBtn);
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        "/v2/volunteering/opportunities",
        expect.objectContaining({ organization_id: 1 }),
      );
    });
  });

  it("fetches organisations from the correct endpoint on mount", async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CreateOpportunityPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith("/v2/volunteering/my-organisations");
    });
  });
});
