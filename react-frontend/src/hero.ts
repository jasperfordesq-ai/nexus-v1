// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HeroUI v2 Tailwind plugin configuration.
 *
 * This file bridges the NEXUS brand design tokens into HeroUI's internal
 * design system so that HeroUI components (buttons, inputs, modals, badges,
 * selection states, focus rings, etc.) use the same palette as tokens.css
 * rather than HeroUI's generic defaults.
 *
 * Colour palette sources:
 *  - Primary  → Tailwind indigo (6366f1 dark / 4f46e5 light)
 *  - Secondary → Tailwind purple (a855f7 dark / 9333ea light)
 *  - Success  → Tailwind green  (22c55e dark / 16a34a light)
 *  - Warning  → Tailwind amber  (f59e0b dark / d97706 light)
 *  - Danger   → Tailwind red    (ef4444 dark / dc2626 light)
 *
 * Background / foreground / content1-4 are set here but are also explicitly
 * overridden in tokens.css via --heroui-* CSS variables for fine-grained
 * HSL control. The tokens.css values take precedence (they're later in the
 * cascade) and are the authoritative surface colours.
 *
 * Border radius is synchronised with tokens.css:
 *   --radius-sm: 8px  → HeroUI small
 *   --radius-md: 12px → HeroUI medium
 *   --radius-lg: 16px → HeroUI large
 */

import { heroui } from "@heroui/theme";

export default heroui({
  addCommonColors: true,   // keeps Tailwind's full color palette available
  defaultTheme: "dark",    // matches tokens.css default

  themes: {
    // ─────────────────────────────────────────────
    // DARK THEME  (default)
    // ─────────────────────────────────────────────
    dark: {
      layout: {
        radius: {
          small: "8px",
          medium: "12px",
          large: "16px",
        },
        disabledOpacity: "0.5",
      },
      colors: {
        // Surfaces — fine-tuned values also in tokens.css --heroui-* overrides
        background: "#0a0a0f",
        foreground: "#ededed",
        focus:      "#6366f1",
        divider:    "#1e1e2e",
        overlay:    "#000000",
        content1:   "#12121e",  // hsl(240 10% 8%)
        content2:   "#1c1c2e",  // hsl(240 10% 12%)
        content3:   "#252539",  // hsl(240 10% 16%)
        content4:   "#2e2e45",  // hsl(240 10% 20%)

        // Primary — Indigo
        primary: {
          50:       "#eef2ff",
          100:      "#e0e7ff",
          200:      "#c7d2fe",
          300:      "#a5b4fc",
          400:      "#818cf8",
          500:      "#6366f1",
          600:      "#4f46e5",
          700:      "#4338ca",
          800:      "#3730a3",
          900:      "#312e81",
          DEFAULT:  "#6366f1",
          foreground: "#ffffff",
        },

        // Secondary — Purple
        secondary: {
          50:       "#faf5ff",
          100:      "#f3e8ff",
          200:      "#e9d5ff",
          300:      "#d8b4fe",
          400:      "#c084fc",
          500:      "#a855f7",
          600:      "#9333ea",
          700:      "#7e22ce",
          800:      "#6b21a8",
          900:      "#581c87",
          DEFAULT:  "#a855f7",
          foreground: "#ffffff",
        },

        // Success — Green
        success: {
          50:       "#f0fdf4",
          100:      "#dcfce7",
          200:      "#bbf7d0",
          300:      "#86efac",
          400:      "#4ade80",
          500:      "#22c55e",
          600:      "#16a34a",
          700:      "#15803d",
          800:      "#166534",
          900:      "#14532d",
          DEFAULT:  "#22c55e",
          foreground: "#ffffff",
        },

        // Warning — Amber
        warning: {
          50:       "#fffbeb",
          100:      "#fef3c7",
          200:      "#fde68a",
          300:      "#fcd34d",
          400:      "#fbbf24",
          500:      "#f59e0b",
          600:      "#d97706",
          700:      "#b45309",
          800:      "#92400e",
          900:      "#78350f",
          DEFAULT:  "#f59e0b",
          foreground: "#000000",  // dark text on amber
        },

        // Danger — Red
        danger: {
          50:       "#fef2f2",
          100:      "#fee2e2",
          200:      "#fecaca",
          300:      "#fca5a5",
          400:      "#f87171",
          500:      "#ef4444",
          600:      "#dc2626",
          700:      "#b91c1c",
          800:      "#991b1b",
          900:      "#7f1d1d",
          DEFAULT:  "#ef4444",
          foreground: "#ffffff",
        },
      },
    },

    // ─────────────────────────────────────────────
    // LIGHT THEME
    // ─────────────────────────────────────────────
    light: {
      layout: {
        radius: {
          small: "8px",
          medium: "12px",
          large: "16px",
        },
        disabledOpacity: "0.5",
      },
      colors: {
        // Surfaces — fine-tuned values also in tokens.css --heroui-* overrides
        background: "#f8fafc",
        foreground: "#1e293b",
        focus:      "#4f46e5",
        divider:    "#e2e8f0",
        overlay:    "#000000",
        content1:   "#ffffff",
        content2:   "#f5f8fc",
        content3:   "#edf1f6",
        content4:   "#e4eaf1",

        // Primary — Indigo (one step darker for better contrast on white)
        primary: {
          50:       "#eef2ff",
          100:      "#e0e7ff",
          200:      "#c7d2fe",
          300:      "#a5b4fc",
          400:      "#818cf8",
          500:      "#6366f1",
          600:      "#4f46e5",
          700:      "#4338ca",
          800:      "#3730a3",
          900:      "#312e81",
          DEFAULT:  "#4f46e5",
          foreground: "#ffffff",
        },

        // Secondary — Purple
        secondary: {
          50:       "#faf5ff",
          100:      "#f3e8ff",
          200:      "#e9d5ff",
          300:      "#d8b4fe",
          400:      "#c084fc",
          500:      "#a855f7",
          600:      "#9333ea",
          700:      "#7e22ce",
          800:      "#6b21a8",
          900:      "#581c87",
          DEFAULT:  "#9333ea",
          foreground: "#ffffff",
        },

        // Success — Green
        success: {
          50:       "#f0fdf4",
          100:      "#dcfce7",
          200:      "#bbf7d0",
          300:      "#86efac",
          400:      "#4ade80",
          500:      "#22c55e",
          600:      "#16a34a",
          700:      "#15803d",
          800:      "#166534",
          900:      "#14532d",
          DEFAULT:  "#16a34a",
          foreground: "#ffffff",
        },

        // Warning — Amber
        warning: {
          50:       "#fffbeb",
          100:      "#fef3c7",
          200:      "#fde68a",
          300:      "#fcd34d",
          400:      "#fbbf24",
          500:      "#f59e0b",
          600:      "#d97706",
          700:      "#b45309",
          800:      "#92400e",
          900:      "#78350f",
          DEFAULT:  "#d97706",
          foreground: "#000000",
        },

        // Danger — Red
        danger: {
          50:       "#fef2f2",
          100:      "#fee2e2",
          200:      "#fecaca",
          300:      "#fca5a5",
          400:      "#f87171",
          500:      "#ef4444",
          600:      "#dc2626",
          700:      "#b91c1c",
          800:      "#991b1b",
          900:      "#7f1d1d",
          DEFAULT:  "#dc2626",
          foreground: "#ffffff",
        },
      },
    },
  },
});
