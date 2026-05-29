# Project NEXUS Sales Site Redesign Design

## Status

Approved direction from brainstorming:

- Visual direction: Premium Product OS
- Audience structure: Dual Front Door
- Proof strategy: Hybrid Proof System
- Technical approach: full redesign on the current stack, with static pre-rendering as a follow-up polish pass

## Objective

Redesign the Project NEXUS sales site into a premium civic SaaS experience that can credibly sell to public sector, funders, enterprise buyers, and grassroots community organisers.

The new site should feel like a serious product company without losing the community movement behind the platform. It should make two buying paths immediately clear:

- Start a timebank: a low-cost managed Community Timebanking lane from EUR29/month.
- Run a civic network: the full Project NEXUS platform for multi-tenant civic infrastructure, federation, volunteering, governance, accessibility, support, and scale.

## Non-Goals

- Do not migrate to Next.js, Astro, or another framework in this redesign pass.
- Do not build a CMS.
- Do not add a backend for the sales site beyond the existing API order/enquiry endpoint.
- Do not deploy automatically.
- Do not redesign the main app, accessible frontend, or legacy PHP views.
- Do not introduce framer-motion.

## Stack Decision

Keep the current sales-site stack:

- React 19
- Vite
- TypeScript
- HeroUI v3
- Tailwind CSS 4
- Lucide React

This is the right stack for the redesign because the site is primarily static marketing content plus a quote/order form. HeroUI v3 and Tailwind 4 are enough to achieve a premium product UI. A framework migration would add deployment and routing complexity before the design has earned it.

Add static pre-rendering after the visual redesign for these routes:

- `/`
- `/features`
- `/hosting`
- legal pages under `/legal/...`

Pre-rendering should improve SEO, social previews, and first-load HTML while preserving the current Vite deployment model.

## Brand Positioning

Primary positioning:

> Project NEXUS is the operating system for modern community infrastructure: affordable enough for a local timebank, serious enough for public-sector civic networks.

Tone:

- Premium but not cold.
- Civic and trustworthy, not bureaucratic.
- Community-rooted, not naive.
- Open-source and transparent, not hobbyist.
- Commercially credible, not overpromising support capacity.

## Visual System

Use a dark premium product aesthetic with a teal and indigo accent system. The visual language should feel more like a polished civic operating system than a generic SaaS landing page.

Principles:

- Use product cockpit surfaces instead of decorative hero graphics.
- Make the first viewport useful, not just atmospheric.
- Reduce repeated generic card grids by introducing stronger section-specific layouts.
- Keep cards for individual items, quote choices, repeated modules, and contained product surfaces.
- Avoid marketing fluff, oversized decorative gradients, or one-note color palettes.
- Use real NEXUS concepts in UI mockups: time credits, offers, requests, active members, federation status, moderation, volunteer hours, impact, and support cover.

Core visual ingredients:

- Sticky premium header with clear nav and primary pricing CTA.
- Dual-front-door hero with two high-quality path panels.
- Product cockpit visual that looks like software rather than a static illustration.
- Compact proof metrics with strong labels.
- Clean pricing ladder and guided quote builder.
- Better mobile rhythm with no cramped cards or clipped text.

## Site Architecture

### Home Page

Purpose: explain what NEXUS is, who it serves, and which buying path to take.

Recommended structure:

1. Hero: dual front door
   - Headline: "Community infrastructure, from local timebanks to civic networks."
   - Supporting copy: one concise paragraph explaining time credits, volunteering, civic participation, federation, and managed hosting.
   - Path panel 1: "Start a timebank" with EUR29/month entry, core timebanking, local trust, and launch path.
   - Path panel 2: "Run a civic network" with multi-tenancy, federation, governance, accessibility, support, and scale.
   - Product cockpit visual showing real platform concepts.

2. Trust strip
   - Open source
   - Live community
   - 60+ modules
   - 11 languages
   - Accessible frontend
   - Managed hosting partner information

3. Hybrid proof section
   - Product UI proof on one side.
   - Community impact/outcome proof on the other.
   - Shows that this is both serious infrastructure and a human project.

4. Platform system overview
   - Time credits and exchange
   - Community life
   - Participation and volunteering
   - Operations and governance
   - Federation and networks

5. Built for two scales
   - Local community/timebank
   - Civic network/public-sector programme
   - Compare not as a hard pricing table, but as buyer journey guidance.

6. Final CTA
   - "Start with pricing"
   - "Explore features"
   - "View source"

### Features Page

Purpose: show product breadth without overwhelming buyers.

Recommended structure:

1. Hero: "Everything inside the platform."
   - Position the page as a product map, not a raw list.

2. Buyer feature map near the top
   - Community Edition boundary
   - Full platform breadth
   - Network/federation capabilities
   - Procurement/integration capabilities

3. Capability bands
   - Core Platform
   - Member Experience
   - Content and Communication
   - AI and Recommendations
   - Operations and Trust

Each band should have a compact visual header, short description, and scannable module rows.

4. Federation proof section
   - Keep federation prominent because it differentiates NEXUS from ordinary volunteer/community tools.

5. Open-source trust section
   - Repository, AGPL, transparency, self-hosting, auditability.

### Pricing / Hosting Page

Purpose: guide buyers into the right commercial lane and make pricing feel credible.

Recommended structure:

1. Hero: "Two ways to start."
   - Community Timebanking lane.
   - Full Platform Hosting lane.
   - Clear from-prices and buyer fit.

2. Pricing ladder
   - Community Edition, Plus, Pro.
   - Spark, Community, Growth, Scale, Network, Enterprise Custom.
   - Show capacity, tenant count, storage/email, and support caveat in a calm comparison format.

3. Market position
   - Keep the concise competitor-positioning section.
   - Message: cheap-to-middle by category, strongest value on breadth.

4. Community Edition details
   - Make it obvious the entry plan is lower-cost because it is narrower.

5. Support model
   - Keep solo-led default and contract-funded support cover explicit.
   - Avoid hard P1 promises unless tied to major-client support retainer.

6. Guided quote builder
   - Improve hierarchy and visual polish.
   - Summary panel should feel like a procurement estimate.
   - Keep product line, capacity, support, maintenance, launch help, add-ons, and order enquiry flow.

7. Open-source/self-hosting close
   - Make clear that self-hosting remains possible under AGPL and managed hosting buys reliability, support, upgrades, and delivery.

### Legal Pages

Purpose: maintain trust and legal clarity.

Changes should be light:

- Align visual system with redesigned shell.
- Keep text readable and professional.
- Preserve all existing legal content.

## Component Plan

Create or refine shared primitives so pages feel consistent:

- `SalesHero`: premium hero layout with optional product cockpit.
- `PathwayCard`: dual-front-door action panels.
- `ProofMetric`: high-contrast proof number/label rows.
- `ProductCockpit`: static product UI composition using real NEXUS concepts.
- `CapabilityBand`: grouped feature catalogue section.
- `PricingLadder`: compact plan comparison component.
- `SupportModelSection`: support caveat and retainer explanation.

Use HeroUI v3 components for:

- Buttons
- Chips
- Cards where appropriate
- Quote builder controls that benefit from accessible interaction semantics

Use Tailwind CSS for:

- Layout
- Responsive grids
- Spacing
- Product cockpit visual composition
- Theme-specific polish

## Data Flow

Keep existing data-driven patterns:

- Pricing data remains in `sales-site/src/data/pricing.ts`.
- Module catalogue remains in `sales-site/src/data/modules.ts`.
- Legal content remains in `sales-site/src/data/legal.ts`.
- Quote calculations remain in `sales-site/src/lib/pricingEngine.ts`.
- Sales order submission remains in `sales-site/src/lib/salesOrderApi.ts`.

No new backend is required for the redesign.

## Error Handling

The visual redesign should preserve existing order form behaviour:

- Validate required contact fields.
- Submit through the existing sales order API.
- Show success and error states clearly.
- Avoid mailto-based primary order flow.
- Keep custom quote states explicit when pricing cannot be calculated.

The quote builder should continue to handle:

- Enterprise Custom above 100,000 active members.
- Dedicated managed server as custom-pricing mode.
- Support retainers as explicit recurring line items.

## Accessibility

Requirements:

- Preserve skip link.
- Preserve semantic headings and page landmarks.
- Keep keyboard-accessible buttons and quote controls.
- Use sufficient contrast for dark backgrounds.
- Avoid text embedded only in images.
- Avoid layout shifts from dynamic labels.
- Respect reduced-motion preferences.
- Keep mobile text readable without overlap.

## SEO And Pre-Rendering

The redesign can launch on the current Vite SPA.

After visual implementation, add static pre-rendering so generated HTML includes real page content for key routes. The pre-render pass should not require a Next.js migration.

Pre-rendered routes:

- `/`
- `/features`
- `/hosting`
- `/legal/terms`
- `/legal/privacy`
- `/legal/cookies`
- `/legal/acceptable-use`
- `/legal/data-processing`

Acceptance criteria for pre-rendering:

- Built `dist` contains useful HTML for each route.
- Meta titles/descriptions remain route-appropriate.
- Client navigation still works after hydration.
- Existing tests and production build pass.

## Testing Plan

Add or update tests to cover:

- The dual-front-door hero appears on the homepage.
- The homepage includes both "Start a timebank" and "Run a civic network" paths.
- The product cockpit uses real NEXUS concepts.
- The pricing page keeps market positioning and support caveats.
- The features page keeps module catalogue grouping.
- The order flow still uses backend sales order submission.
- No public copy advertises unsupported round-the-clock support.

Run:

- `cd sales-site && npm test`
- `cd sales-site && npm run build`

Visual QA:

- Desktop viewport around 1280px.
- Mobile viewport around 390px.
- Check hero, pricing, quote builder, and footer.
- Confirm no horizontal overflow.
- Confirm text does not overlap or clip.

## Implementation Order

1. Refine shared sales primitives and design tokens.
2. Build the dual-front-door homepage.
3. Redesign the pricing page around lane selection, market positioning, support model, and quote builder polish.
4. Redesign the features page as a product catalogue with capability bands.
5. Lightly align legal pages and footer.
6. Run tests and build.
7. Browser QA desktop and mobile.
8. Add static pre-rendering in a follow-up pass.

## Confirmed Decisions

The following are intentionally decided for implementation:

- Use current React/Vite/HeroUI/Tailwind stack.
- Do not migrate to Next.js.
- Use Premium Product OS visual direction.
- Use Dual Front Door audience architecture.
- Use Hybrid Proof System.
- Treat static pre-rendering as a follow-up technical polish pass after the visual redesign.
